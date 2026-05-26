<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\lootboxes;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\utils\RewardAnnouncer;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\type\graphic\BlockActorInvMenuGraphic;
use pocketmine\block\tile\Tile;
use pocketmine\block\VanillaBlocks;
use pocketmine\color\Color;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\ByteMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\sound\ChestCloseSound;
use pocketmine\world\sound\ChestOpenSound;
use pocketmine\world\sound\XpCollectSound;
use pocketmine\world\World;

final class LootboxChestAnimationScreen extends BaseScreen
{
    private const TOTAL_TICKS = 68;
    private const OPEN_TICK = 18;
    private const FIRST_BURST_TICK = 24;
    private const BURST_INTERVAL_TICKS = 6;
    private const ITEM_LIFETIME_TICKS = 18;
    private const MAX_BURSTS = 5;
    private const MAX_FORWARD_DISTANCE = 5;
    private const MAX_LATERAL_DISTANCE = 2;
    private const MAX_VERTICAL_SCAN = 6;
    private const MAX_ACTIVE_ANIMATIONS = 50;
    private const MAX_VIEWER_DISTANCE_SQUARED = 256.0;

    /** @var array<string, true> */
    private static array $active = [];
    private static int $nextRuntimeId = 900000;

    /** @var Item[] */
    private array $finalRewards;
    /** @var Item[] */
    private array $displayItems;
    /** @var array<int, array{runtimeId:int,startTick:int,lifeTicks:int,offsetX:float,offsetZ:float,item:Item}> */
    private array $fakeItems = [];
    private ?TaskHandler $task = null;
    private ?Vector3 $chestPos = null;
    private ?World $animationWorld = null;
    private bool $finished = false;
    private bool $opened = false;
    private bool $rewardsDelivered = false;

    /**
     * @param array<string, mixed> $definition
     */
    public function __construct(private Player $viewer, private array $definition)
    {
        $this->finalRewards = LootboxRepository::rollRewards($this->definition, $viewer);
        if ($this->finalRewards === []) {
            $this->finalRewards = [
                VanillaBlocks::BARRIER()->asItem()->setCustomName(C::colorize("&r&cMissing Reward"))
            ];
        }

        $this->displayItems = LootboxRepository::getDisplayItems($this->definition, $viewer);
        if ($this->displayItems === []) {
            $this->displayItems = $this->finalRewards;
        }
    }

    public function getMenu(): null
    {
        return null;
    }

    public function getForm(): null
    {
        return null;
    }

    public function display(Player $player): bool
    {
        $uuid = $player->getUniqueId()->toString();
        if (isset(self::$active[$uuid]) || count(self::$active) >= self::MAX_ACTIVE_ANIMATIONS) {
            return false;
        }

        $chestPos = self::findValidChestPosition($player);
        if ($chestPos === null) {
            return false;
        }

        self::$active[$uuid] = true;
        $this->chestPos = $chestPos;
        $this->animationWorld = $player->getWorld();

        $this->sendFakeChest();
        $this->playSpawnEffects();
        $this->startAnimation();
        return true;
    }

    private function startAnimation(): void
    {
        $tick = 0;
        $burstCount = min(self::MAX_BURSTS, max(3, count($this->finalRewards)));

        $this->task = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() use (&$tick, $burstCount): void {
                if ($this->shouldAbortAnimation()) {
                    $this->finish(true);
                    return;
                }

                $tick++;
                $this->playAmbientEffects($tick);

                if (!$this->opened && $tick >= self::OPEN_TICK) {
                    $this->opened = true;
                    $this->openChest();
                }

                if ($this->opened && $tick >= self::FIRST_BURST_TICK) {
                    $burstIndex = intdiv($tick - self::FIRST_BURST_TICK, self::BURST_INTERVAL_TICKS);
                    if (($tick - self::FIRST_BURST_TICK) % self::BURST_INTERVAL_TICKS === 0 && $burstIndex < $burstCount) {
                        $this->spawnFakeRewardBurst($burstIndex, $tick);
                    }
                }

                $this->updateFakeItems($tick);

                if ($tick >= self::TOTAL_TICKS) {
                    $this->finish();
                }
            }
        ), 1);
    }

    private function finish(bool $closedEarly = false): void
    {
        if ($this->finished) {
            return;
        }

        $this->finished = true;
        if ($this->task !== null) {
            $this->task->cancel();
            $this->task = null;
        }

        unset(self::$active[$this->viewer->getUniqueId()->toString()]);

        if ($this->chestPos !== null && $this->viewer->isConnected() && $this->animationWorld !== null) {
            if ($this->opened) {
                $this->animationWorld->addSound($this->chestPos->add(0.5, 0.5, 0.5), new ChestCloseSound(), [$this->viewer]);
                $this->sendChestAnimationEvent(0);
            }
            $this->restoreOriginalBlock();
            $this->playFinishEffects();
        }

        foreach ($this->fakeItems as $entry) {
            if ($this->viewer->isConnected()) {
                $this->viewer->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($entry["runtimeId"]));
            }
        }
        $this->fakeItems = [];

        if ($this->rewardsDelivered) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($this->viewer);
        $rewardCopies = array_map(static fn(Item $item): Item => clone $item, $this->finalRewards);

        $recipient = $session?->getPlayer();
        if ($closedEarly || !$recipient instanceof Player || !$recipient->isConnected()) {
            foreach ($rewardCopies as $reward) {
                $session?->addItemToCollection($reward);
            }
            $this->rewardsDelivered = true;
            return;
        }

        $leftovers = $recipient->getInventory()->addItem(...$rewardCopies);
        foreach ($leftovers as $leftover) {
            $session?->addItemToCollection($leftover);
        }
        if ($leftovers !== [] && $session === null) {
            $recipient->sendMessage(C::colorize("&r&eSome rewards could not be delivered because your collection is unavailable."));
        }
        $this->rewardsDelivered = true;

        RewardAnnouncer::announce(
            $recipient,
            "Lootbox",
            (string) ($this->definition["name"] ?? $this->definition["title"] ?? "Mystery"),
            $this->finalRewards
        );

        PlayerUtils::playSound($recipient, "random.levelup", 1.0, 1.0, 8);
    }

    private function sendFakeChest(): void
    {
        if ($this->chestPos === null) {
            return;
        }

        $facing = Facing::opposite(self::getPlayerFacingDirection($this->viewer));
        $block = VanillaBlocks::CHEST()->setFacing($facing);
        $position = BlockPosition::fromVector3($this->chestPos);
        $network = $this->viewer->getNetworkSession();

        $network->sendDataPacket(UpdateBlockPacket::create(
            $position,
            TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($block->getStateId()),
            UpdateBlockPacket::FLAG_NETWORK,
            UpdateBlockPacket::DATA_LAYER_NORMAL
        ));

        $tile = BlockActorInvMenuGraphic::createTile("Chest", null)
            ->setInt(Tile::TAG_X, $this->chestPos->getFloorX())
            ->setInt(Tile::TAG_Y, $this->chestPos->getFloorY())
            ->setInt(Tile::TAG_Z, $this->chestPos->getFloorZ());

        $network->sendDataPacket(BlockActorDataPacket::create($position, new CacheableNbt($tile)));
    }

    private function restoreOriginalBlock(): void
    {
        if ($this->chestPos === null || $this->animationWorld === null) {
            return;
        }

        foreach ($this->animationWorld->createBlockUpdatePackets([$this->chestPos]) as $packet) {
            $this->viewer->getNetworkSession()->sendDataPacket($packet);
        }
    }

    private function playSpawnEffects(): void
    {
        if ($this->chestPos === null) {
            return;
        }

        $center = $this->chestPos->add(0.5, 0.45, 0.5);
        $this->animationWorld?->addParticle($center, new BlockBreakParticle(VanillaBlocks::CHEST()));
        $this->animationWorld?->addSound($center, new XpCollectSound(), [$this->viewer]);
        PlayerUtils::playSound($this->viewer, "random.orb", 1.0, 0.9, 8);
        $this->spawnSwirlParticles($center, 0.45, new Color(120, 80, 255), 10);
    }

    private function playAmbientEffects(int $tick): void
    {
        if ($this->chestPos === null) {
            return;
        }

        $center = $this->chestPos->add(0.5, 0.8, 0.5);

        if ($tick % 4 === 0) {
            $this->spawnSwirlParticles($center, 0.52, new Color(75, 220, 255), 8, $tick / 8);
        }

        if ($tick % 8 === 0 && !$this->opened) {
            PlayerUtils::playSound($this->viewer, "note.pling", 0.65, 0.85 + ($tick / 80), 8);
        }
    }

    private function openChest(): void
    {
        if ($this->chestPos === null) {
            return;
        }

        $center = $this->chestPos->add(0.5, 0.5, 0.5);
        $this->sendChestAnimationEvent(1);
        $this->animationWorld?->addSound($center, new ChestOpenSound(), [$this->viewer]);
        PlayerUtils::playSound($this->viewer, "random.pop", 1.0, 0.85, 8);
        $this->spawnSwirlParticles($center->add(0, 0.35, 0), 0.6, new Color(255, 210, 90), 14);
    }

    private function sendChestAnimationEvent(int $eventData): void
    {
        if ($this->chestPos === null) {
            return;
        }

        $this->viewer->getNetworkSession()->sendDataPacket(BlockEventPacket::create(
            BlockPosition::fromVector3($this->chestPos),
            1,
            $eventData
        ));
    }

    private function spawnFakeRewardBurst(int $burstIndex, int $tick): void
    {
        if ($this->chestPos === null) {
            return;
        }

        $item = clone $this->displayItems[array_rand($this->displayItems)];
        $angle = (2 * M_PI * $burstIndex) / max(3, min(self::MAX_BURSTS, count($this->displayItems)));
        $offsetX = cos($angle) * 0.75;
        $offsetZ = sin($angle) * 0.75;
        $runtimeId = self::$nextRuntimeId++;

        $spawn = $this->chestPos->add(0.5, 1.05, 0.5);
        $this->viewer->getNetworkSession()->sendDataPacket(AddItemActorPacket::create(
            $runtimeId,
            $runtimeId,
            ItemStackWrapper::legacy($this->viewer->getNetworkSession()->getTypeConverter()->coreItemStackToNet($item)),
            $spawn,
            new Vector3(0.0, 0.18, 0.0),
            [],
            false
        ));
        $this->viewer->getNetworkSession()->sendDataPacket(SetActorDataPacket::create(
            $runtimeId,
            [
                EntityMetadataProperties::NAMETAG => new StringMetadataProperty(self::formatBurstItemName($item)),
                EntityMetadataProperties::ALWAYS_SHOW_NAMETAG => new ByteMetadataProperty(1),
            ],
            new PropertySyncData([], []),
            0
        ));

        $this->fakeItems[] = [
            "runtimeId" => $runtimeId,
            "startTick" => $tick,
            "lifeTicks" => self::ITEM_LIFETIME_TICKS,
            "offsetX" => $offsetX,
            "offsetZ" => $offsetZ,
            "item" => $item,
        ];

        PlayerUtils::playSound($this->viewer, "random.orb", 0.7, 1.0 + ($burstIndex * 0.08), 8);
        $this->animationWorld?->addParticle($spawn, new BlockBreakParticle(VanillaBlocks::GLASS()));
    }

    private function updateFakeItems(int $tick): void
    {
        if ($this->chestPos === null || !$this->viewer->isConnected()) {
            return;
        }

        foreach ($this->fakeItems as $index => $entry) {
            $age = $tick - $entry["startTick"];
            if ($age < 0) {
                continue;
            }

            $progress = $age / max(1, $entry["lifeTicks"]);
            if ($progress >= 1.0) {
                $this->viewer->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($entry["runtimeId"]));
                unset($this->fakeItems[$index]);
                continue;
            }

            $x = $this->chestPos->getX() + 0.5 + ($entry["offsetX"] * $progress);
            $z = $this->chestPos->getZ() + 0.5 + ($entry["offsetZ"] * $progress);
            $y = $this->chestPos->getY() + 1.0 + (sin($progress * M_PI) * 0.8);

            $this->viewer->getNetworkSession()->sendDataPacket(MoveActorAbsolutePacket::create(
                $entry["runtimeId"],
                new Vector3($x, $y, $z),
                0.0,
                0.0,
                0.0,
                0
            ));

            if ($age % 5 === 0) {
                $this->animationWorld?->addParticle(new Vector3($x, $y, $z), new DustParticle(new Color(255, 245, 170)));
            }
        }

        $this->fakeItems = array_values($this->fakeItems);
    }

    private function playFinishEffects(): void
    {
        if ($this->chestPos === null) {
            return;
        }

        $center = $this->chestPos->add(0.5, 0.85, 0.5);
        $this->spawnSwirlParticles($center, 0.7, new Color(255, 225, 110), 18);
        $this->spawnSwirlParticles($center, 0.35, new Color(130, 235, 255), 12, 0.8);
        PlayerUtils::playSound($this->viewer, "random.levelup", 1.0, 1.0, 8);
    }

    private function spawnSwirlParticles(Vector3 $center, float $radius, Color $color, int $count, float $phase = 0.0): void
    {
        for ($i = 0; $i < $count; $i++) {
            $angle = $phase + ((2 * M_PI * $i) / max(1, $count));
            $x = $center->x + (cos($angle) * $radius);
            $z = $center->z + (sin($angle) * $radius);
            $y = $center->y + (($i % 3) * 0.08);
            $this->animationWorld?->addParticle(new Vector3($x, $y, $z), new DustParticle($color));
        }
    }

    private function shouldAbortAnimation(): bool
    {
        if ($this->finished || !$this->viewer->isConnected() || $this->chestPos === null || $this->animationWorld === null) {
            return true;
        }

        if ($this->viewer->getWorld() !== $this->animationWorld) {
            return true;
        }

        return $this->viewer->getPosition()->distanceSquared($this->chestPos) > self::MAX_VIEWER_DISTANCE_SQUARED;
    }

    private static function formatBurstItemName(Item $item): string
    {
        $customName = trim($item->getCustomName());
        $name = $customName !== ""
            ? $customName
            : C::colorize("&r&f" . trim(C::clean($item->getName())));

        if ($name === "" || trim(C::clean($name)) === "") {
            $name = C::colorize("&r&bReward");
        }

        if ($item->getCount() > 1) {
            $name .= C::colorize(" &8x&f" . $item->getCount());
        }

        return C::colorize("&r" . $name);
    }

    private static function findValidChestPosition(Player $player): ?Vector3
    {
        $world = $player->getWorld();
        $base = $player->getPosition();
        $direction = self::getForwardVector($player);
        $startY = (int) floor($base->getY());
        $right = new Vector3(-$direction->z, 0.0, $direction->x);

        for ($distance = 1; $distance <= self::MAX_FORWARD_DISTANCE; $distance++) {
            for ($lateral = 0; $lateral <= self::MAX_LATERAL_DISTANCE; $lateral++) {
                $lateralOffsets = $lateral === 0 ? [0] : [$lateral, -$lateral];

                foreach ($lateralOffsets as $sideOffset) {
                    $step = $base
                        ->addVector($direction->multiply($distance))
                        ->addVector($right->multiply((float) $sideOffset));
                    $x = (int) floor($step->getX());
                    $z = (int) floor($step->getZ());

                    for ($y = $startY + 1; $y >= max(0, $startY - self::MAX_VERTICAL_SCAN); $y--) {
                        $ground = $world->getBlockAt($x, $y, $z);
                        if (!$ground->isSolid() || !$ground->isFullCube()) {
                            continue;
                        }

                        return new Vector3($x, $y + 1, $z);
                    }
                }
            }
        }

        return null;
    }

    private static function getForwardVector(Player $player): Vector3
    {
        $yawRad = deg2rad($player->getLocation()->getYaw());
        return new Vector3(-sin($yawRad), 0.0, cos($yawRad));
    }

    private static function getPlayerFacingDirection(Player $player): int
    {
        $yaw = fmod($player->getLocation()->getYaw(), 360.0);
        if ($yaw < 0) {
            $yaw += 360.0;
        }

        if ($yaw >= 45.0 && $yaw < 135.0) {
            return Facing::WEST;
        }
        if ($yaw >= 135.0 && $yaw < 225.0) {
            return Facing::NORTH;
        }
        if ($yaw >= 225.0 && $yaw < 315.0) {
            return Facing::EAST;
        }

        return Facing::SOUTH;
    }
}
