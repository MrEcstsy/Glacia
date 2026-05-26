<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\redstone;

use ecstsy\Glacia\entity\GlaciaPrimedTNT;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use muqsit\invmenu\InvMenu;
use pocketmine\entity\Location;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\TNT;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\World;
use function array_rand;
use function is_array;
use function is_string;
use function method_exists;
use function strtolower;
use function stripos;

final class RedstoneDispenserManager
{
    /** Must match GlaciaPrimedTNT::TAG_GLACIA_DISPENSER_LAUNCHED (string literal avoids older plugin copies missing the constant). */
    private const GLACIA_DISPENSER_LAUNCHED_NBT = "glacia_dispenser_launched";
    /** Align dispenser TNT detonation to shared tick buckets so rows fired slightly apart still explode together. */
    private const TNT_DETONATION_SYNC_BUCKET_TICKS = 4;

    private const KEY_ROOT = "glacia_redstone";
    private const KEY_KIND = "kind";
    private const KEY_ITEMS = "items";

    /** @deprecated No longer persisted; strips from disk reads. */
    private const KEY_POWERED_LEGACY = "powered";
    private const KEY_LAST_FIRE = "last_fire";

    /** Last HIGH/LOW fed by RedstoneManager for this coords (never written to LevelDB/chunk overlays). */
    /** @var array<string, array<int, bool>> */
    private static array $logicalPowered = [];

    public static function isDispenserBlock(Block $block): bool
    {
        return self::resolveKind($block) === "dispenser";
    }

    public static function isDropperBlock(Block $block): bool
    {
        return self::resolveKind($block) === "dropper";
    }

    public static function isDispensingBlock(Block $block): bool
    {
        return self::resolveKind($block) !== null;
    }

    /**
     * Fills every slot of a Glacia dispenser/dropper with stacked vanilla TNT blocks (practice / cannon setups).
     */
    public static function fillAllSlotsWithVanillaTnt(Block $block): bool
    {
        $template = VanillaBlocks::TNT()->asItem();
        return self::fillAllSlots($block, $template, min(64, $template->getMaxStackSize()));
    }

    public static function fillAllSlots(Block $block, Item $template, int $count): bool
    {
        $kind = self::resolveKind($block);
        if ($kind === null) {
            return false;
        }

        $pos = $block->getPosition();
        $world = $pos->getWorld();
        $x = $pos->getFloorX();
        $y = $pos->getFloorY();
        $z = $pos->getFloorZ();

        $stack = clone $template;
        $stack->setCount(max(1, min($count, $template->getMaxStackSize())));

        $contents = [];
        for ($slot = 0; $slot < 9; ++$slot) {
            $contents[$slot] = clone $stack;
        }

        self::saveInventoryContents($world, $x, $y, $z, $kind, $contents);
        return true;
    }

    public static function open(Player $player, Block $block): void
    {
        $kind = self::resolveKind($block);
        if ($kind === null) {
            return;
        }

        $menu = InvMenu::create(GlaciaUtils::TYPE_DISPENSER);
        $menu->setName($kind === "dropper" ? "Dropper" : "Dispenser");
        $inventory = $menu->getInventory();

        foreach (self::decodeStoredItems($block) as $slot => $item) {
            $inventory->setItem($slot, $item);
        }

        $world = $block->getPosition()->getWorld();
        $x = $block->getPosition()->getFloorX();
        $y = $block->getPosition()->getFloorY();
        $z = $block->getPosition()->getFloorZ();

        $menu->setInventoryCloseListener(static function (Player $player, \pocketmine\inventory\Inventory $inventory) use ($world, $x, $y, $z, $kind): void {
            RedstoneDispenserManager::saveInventoryContents($world, $x, $y, $z, $kind, $inventory->getContents());
        });
        $menu->send($player);
    }

    public static function setPowered(Block $block, bool $powered): void
    {
        $kind = self::resolveKind($block);
        if ($kind === null) {
            return;
        }

        $world = $block->getPosition()->getWorld();
        $x = $block->getPosition()->getFloorX();
        $y = $block->getPosition()->getFloorY();
        $z = $block->getPosition()->getFloorZ();

        $previous = self::readLogicalPowered($world, $x, $y, $z);

        self::syncTriggeredVisual($block, $powered);

        if ($previous === $powered) {
            return;
        }

        $state = self::getState($block, $kind);

        $shouldDispense = false;
        if ($powered) {
            $cooldown = RedstoneManager::getInstance()->getDispenserCooldownTicks();
            $currentTick = Loader::getInstance()->getServer()->getTick();
            $lastFire = (int) ($state[self::KEY_LAST_FIRE] ?? -$cooldown);
            if ($lastFire > $currentTick) {
                // Server tick resets to 0 on restart; persisted tick-based cooldown must be invalidated.
                $lastFire = -$cooldown;
            }
            if (($currentTick - $lastFire) >= $cooldown) {
                $state[self::KEY_LAST_FIRE] = $currentTick;
                $shouldDispense = true;
            }
        }

        self::writeState($block, $state);

        if ($shouldDispense) {
            self::dispense($block, $kind, $state);
        }

        self::writeLogicalPowered($world, $x, $y, $z, $powered);
    }

    private static function syncTriggeredVisual(Block $block, bool $powered): void
    {
        if (!method_exists($block, "setTriggered")) {
            return;
        }

        $current = method_exists($block, "isTriggered") ? $block->isTriggered() : null;
        if ($current === $powered) {
            return;
        }

        $updated = clone $block;
        $updated->{'setTriggered'}($powered);
        $updated->getPosition()->getWorld()->setBlock($updated->getPosition(), $updated);
    }

    public static function clearLogicalPoweredWorld(string $folderName): void
    {
        unset(self::$logicalPowered[$folderName]);
    }

    /** Call when dispensing block breaks / explodes so a replacement at same coords isn't stuck "already HIGH". */
    public static function clearLogicalPoweredBlock(World $world, int $x, int $y, int $z): void
    {
        $folder = $world->getFolderName();
        $h = World::blockHash($x, $y, $z);
        unset(self::$logicalPowered[$folder][$h]);
        if ((self::$logicalPowered[$folder] ?? []) === []) {
            unset(self::$logicalPowered[$folder]);
        }
    }

    private static function readLogicalPowered(World $world, int $x, int $y, int $z): bool
    {
        $folder = $world->getFolderName();
        $h = World::blockHash($x, $y, $z);

        return isset(self::$logicalPowered[$folder][$h]);
    }

    private static function writeLogicalPowered(World $world, int $x, int $y, int $z, bool $high): void
    {
        $folder = $world->getFolderName();
        $h = World::blockHash($x, $y, $z);
        if ($high) {
            self::$logicalPowered[$folder][$h] = true;
        } else {
            unset(self::$logicalPowered[$folder][$h]);
            if ((self::$logicalPowered[$folder] ?? []) === []) {
                unset(self::$logicalPowered[$folder]);
            }
        }
    }

    public static function onBroken(Block $block): void
    {
        if (!self::isDispensingBlock($block)) {
            return;
        }

        $world = $block->getPosition()->getWorld();
        self::clearLogicalPoweredBlock(
            $world,
            $block->getPosition()->getFloorX(),
            $block->getPosition()->getFloorY(),
            $block->getPosition()->getFloorZ()
        );

        $dropPos = $block->getPosition()->add(0.5, 0.5, 0.5);

        foreach (self::decodeStoredItems($block) as $item) {
            $world->dropItem($dropPos, $item);
        }

        Loader::getBlockData()->remove($block);
    }

    public static function queueDestroyCheck(World $world, int $x, int $y, int $z, int $delay = 1): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(
            static function () use ($world, $x, $y, $z): void {
                $block = $world->getBlockAt($x, $y, $z);
                if (RedstoneDispenserManager::isDispensingBlock($block)) {
                    return;
                }

                $data = Loader::getBlockData()->getAt($world, $x, $y, $z);
                if (!is_array($data) || !isset($data[self::KEY_ROOT]) || !is_array($data[self::KEY_ROOT])) {
                    return;
                }

                $root = $data[self::KEY_ROOT];
                foreach ((array) ($root[self::KEY_ITEMS] ?? []) as $encoded) {
                    if (!is_string($encoded) || $encoded === "") {
                        continue;
                    }

                    $world->dropItem(new Vector3($x + 0.5, $y + 0.5, $z + 0.5), ItemUtils::decodeItem($encoded));
                }

                Loader::getBlockData()->removeAt($world, $x, $y, $z);
            }
        ), max(1, $delay));
    }

    public static function onExploded(World $world, int $x, int $y, int $z): void
    {
        $block = $world->getBlockAt($x, $y, $z);
        if (self::isDispensingBlock($block)) {
            self::onBroken($block);
            return;
        }

        $data = Loader::getBlockData()->getAt($world, $x, $y, $z);
        if (!is_array($data) || !isset($data[self::KEY_ROOT]) || !is_array($data[self::KEY_ROOT])) {
            return;
        }

        $root = $data[self::KEY_ROOT];
        foreach ((array) ($root[self::KEY_ITEMS] ?? []) as $encoded) {
            if (!is_string($encoded) || $encoded === "") {
                continue;
            }

            $world->dropItem(new Vector3($x + 0.5, $y + 0.5, $z + 0.5), ItemUtils::decodeItem($encoded));
        }

        Loader::getBlockData()->removeAt($world, $x, $y, $z);
    }

    public static function getFacing(Block $block): int
    {
        if (method_exists($block, "getFacing")) {
            /** @var int $facing */
            $facing = $block->getFacing();
            return $facing;
        }

        return Facing::NORTH;
    }

    /**
     * @param array<int, Item> $contents
     */
    private static function saveInventoryContents(World $world, int $x, int $y, int $z, string $kind, array $contents): void
    {
        $stored = [];
        foreach ($contents as $slot => $item) {
            if ($item->isNull()) {
                continue;
            }

            $stored[(int) $slot] = ItemUtils::encodeItem($item);
        }

        $current = Loader::getBlockData()->getAt($world, $x, $y, $z);
        $root = is_array($current) && isset($current[self::KEY_ROOT]) && is_array($current[self::KEY_ROOT]) ? $current[self::KEY_ROOT] : [];
        $root[self::KEY_KIND] = $kind;
        $root[self::KEY_ITEMS] = $stored;
        unset($root[self::KEY_POWERED_LEGACY]);
        $root[self::KEY_LAST_FIRE] = (int) ($root[self::KEY_LAST_FIRE] ?? 0);

        Loader::getBlockData()->setAt($world, $x, $y, $z, [
            self::KEY_ROOT => $root
        ]);
    }

    /**
     * @return array<int, Item>
     */
    private static function decodeStoredItems(Block $block): array
    {
        $root = self::getRootData($block);
        $decoded = [];

        foreach ((array) ($root[self::KEY_ITEMS] ?? []) as $slot => $encoded) {
            if (!is_string($encoded) || $encoded === "") {
                continue;
            }

            $decoded[(int) $slot] = ItemUtils::decodeItem($encoded);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function dispense(Block $block, string $kind, array $state): void
    {
        $world = $block->getPosition()->getWorld();
        $x = $block->getPosition()->getFloorX();
        $y = $block->getPosition()->getFloorY();
        $z = $block->getPosition()->getFloorZ();
        $items = (array) ($state[self::KEY_ITEMS] ?? []);
        if ($items === []) {
            return;
        }

        $candidates = [];
        foreach ($items as $slot => $encoded) {
            if (is_string($encoded) && $encoded !== "") {
                $candidates[(int) $slot] = $encoded;
            }
        }

        if ($candidates === []) {
            return;
        }

        $slot = (int) array_rand($candidates);
        $stack = ItemUtils::decodeItem($candidates[$slot]);
        $single = clone $stack;
        $single->setCount(1);

        $remaining = clone $stack;
        $remaining->pop();
        if ($remaining->isNull()) {
            unset($items[$slot]);
        } else {
            $items[$slot] = ItemUtils::encodeItem($remaining);
        }

        $state[self::KEY_ITEMS] = $items;
        unset($state[self::KEY_POWERED_LEGACY]);
        Loader::getBlockData()->setAt($world, $x, $y, $z, [
            self::KEY_ROOT => $state
        ]);

        $facing = self::getFacing($block);

        if ($kind === "dispenser" && self::shouldPrimeTnt($single)) {
            $spawnVec = self::getAdjacentFrontCellSpawnCenter($block, $facing);
            $stackPieces = max(1, $stack->getCount());
            $stackMult = RedstoneManager::getInstance()->getPrimedDispenserStackVelocityMultiplier($stackPieces);
            $motion = self::dispenserAdjacentPrimedTntMotion($facing)->multiply($stackMult);

            $primedSpawnNbt = CompoundTag::create()
                ->setByte(self::GLACIA_DISPENSER_LAUNCHED_NBT, 1);
            $primed = new GlaciaPrimedTNT(Location::fromObject($spawnVec, $world), $primedSpawnNbt);
            if (\method_exists($primed, "setDispenserLaunched")) {
                $primed->setDispenserLaunched(true);
            }
            $sourceClaim = ClaimManager::getInstance()->getClaimAtPosition($block->getPosition());
            if ($sourceClaim !== null && $sourceClaim->getFactionId() !== null) {
                $primed->setRaidSourceFactionId($sourceClaim->getFactionId());
            }
            $fuseTicks = RedstoneManager::getInstance()->getTntFuseTicks();
            $primed->setFuse($fuseTicks);
            if (\method_exists($primed, "setExplodeAtTick")) {
                $baseTick = Loader::getInstance()->getServer()->getTick();
                $primed->setExplodeAtTick(self::alignToDetonationBucket($baseTick + $fuseTicks));
            }
            $primed->setWorksUnderwater(RedstoneManager::getInstance()->tntWorksUnderwater());
            if (!RedstoneManager::getInstance()->isPrimedDispenserTntGravityEnabled()) {
                $primed->setHasGravity(false);
            }
            // Cannon physics (gravity/drag/water-drag multipliers) follow dispenser.cannon-mode — stack mult only scales launch speed.
            $primed->setCannonMode(RedstoneManager::getInstance()->isCannonTntEnabled());
            $primed->setMotion($motion);

            $type = self::resolveCustomTntType($single);
            if ($type !== "") {
                $primed->setTNTType($type);
            }

            $primed->spawnToAll();
            return;
        }

        $spawnVec = self::getSpawnVector($block, $facing);
        $motion = self::faceMotion($facing);
        $world->dropItem($spawnVec, $single, $motion, 10);
    }

    private static function shouldPrimeTnt(Item $item): bool
    {
        if ($item instanceof ItemBlock && $item->getBlock() instanceof TNT) {
            return true;
        }

        return self::resolveCustomTntType($item) !== "";
    }

    private static function resolveCustomTntType(Item $item): string
    {
        $tag = $item->getNamedTag();
        $glacia = $tag->getCompoundTag("Glacia");
        if ($glacia !== null) {
            $type = $glacia->getString("glaciaItem", "");
            if (CustomTNTIds::isCustomTNT($type)) {
                return $type;
            }
        }

        $type = $tag->getString("glaciaItem", "");
        return CustomTNTIds::isCustomTNT($type) ? $type : "";
    }

    /**
     * @return array<string, mixed>
     */
    private static function getState(Block $block, string $kind): array
    {
        $root = self::getRootData($block);
        $root[self::KEY_KIND] = $kind;
        $root[self::KEY_ITEMS] = (array) ($root[self::KEY_ITEMS] ?? []);
        unset($root[self::KEY_POWERED_LEGACY]);
        $root[self::KEY_LAST_FIRE] = (int) ($root[self::KEY_LAST_FIRE] ?? 0);
        return $root;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function writeState(Block $block, array $state): void
    {
        unset($state[self::KEY_POWERED_LEGACY]);
        Loader::getBlockData()->set($block, [
            self::KEY_ROOT => $state
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function getRootData(Block $block): array
    {
        $data = Loader::getBlockData()->get($block);
        if (!is_array($data)) {
            return [];
        }

        $root = $data[self::KEY_ROOT] ?? null;
        if (!is_array($root)) {
            return [];
        }

        unset($root[self::KEY_POWERED_LEGACY]);
        return $root;
    }

    private static function resolveKind(Block $block): ?string
    {
        $name = strtolower($block->getName());
        if (stripos($name, "dropper") !== false) {
            return "dropper";
        }
        if (stripos($name, "dispenser") !== false) {
            return "dispenser";
        }

        $class = $block::class;
        if ($class === "diamondgold\\DummyItemsBlocks\\block\\Dispenser") {
            $root = self::getRootData($block);
            $storedKind = $root[self::KEY_KIND] ?? null;
            if ($storedKind === "dispenser" || $storedKind === "dropper") {
                return $storedKind;
            }

            return "dispenser";
        }

        return null;
    }

    private static function faceMotion(int $face): Vector3
    {
        $manager = RedstoneManager::getInstance();
        $horizontal = $manager->getTntLaunchHorizontal();
        $verticalBoost = $manager->getTntLaunchVerticalBoost();
        $vertical = $manager->getTntLaunchVertical();

        return match ($face) {
            Facing::DOWN => new Vector3(0.0, -$vertical, 0.0),
            Facing::UP => new Vector3(0.0, $vertical, 0.0),
            Facing::NORTH => new Vector3(0.0, $verticalBoost, -$horizontal),
            Facing::SOUTH => new Vector3(0.0, $verticalBoost, $horizontal),
            Facing::WEST => new Vector3(-$horizontal, $verticalBoost, 0.0),
            Facing::EAST => new Vector3($horizontal, $verticalBoost, 0.0),
            default => new Vector3(0.0, 0.0, 0.0),
        };
    }

    /**
     * Spawn center inside the single block cell in front of the dispenser’s output face (not partway inside
     * the dispenser with a huge velocity that clears several blocks).
     */
    private static function getAdjacentFrontCellSpawnCenter(Block $block, int $face): Vector3
    {
        $corner = $block->getPosition()->getSide($face);

        return new Vector3(
            $corner->getFloorX() + 0.5,
            $corner->getFloorY() + 0.5,
            $corner->getFloorZ() + 0.5
        );
    }

    private static function dispenserAdjacentPrimedTntMotion(int $face): Vector3
    {
        $p = RedstoneManager::getInstance()->getPrimedDispenserLaunchBase();

        return match ($face) {
            Facing::DOWN => new Vector3(0.0, -$p, 0.0),
            Facing::UP => new Vector3(0.0, $p, 0.0),
            Facing::NORTH => new Vector3(0.0, 0.0, -$p),
            Facing::SOUTH => new Vector3(0.0, 0.0, $p),
            Facing::WEST => new Vector3(-$p, 0.0, 0.0),
            Facing::EAST => new Vector3($p, 0.0, 0.0),
            default => new Vector3(0.0, 0.0, 0.0),
        };
    }

    private static function getSpawnVector(Block $block, int $face): Vector3
    {
        $position = $block->getPosition();
        $offset = RedstoneManager::getInstance()->getTntSpawnOffset();

        return match ($face) {
            Facing::DOWN => new Vector3($position->getX() + 0.5, $position->getY() - $offset, $position->getZ() + 0.5),
            Facing::UP => new Vector3($position->getX() + 0.5, $position->getY() + 0.5 + $offset, $position->getZ() + 0.5),
            Facing::NORTH => new Vector3($position->getX() + 0.5, $position->getY() + 0.5, $position->getZ() + 0.5 - $offset),
            Facing::SOUTH => new Vector3($position->getX() + 0.5, $position->getY() + 0.5, $position->getZ() + 0.5 + $offset),
            Facing::WEST => new Vector3($position->getX() + 0.5 - $offset, $position->getY() + 0.5, $position->getZ() + 0.5),
            Facing::EAST => new Vector3($position->getX() + 0.5 + $offset, $position->getY() + 0.5, $position->getZ() + 0.5),
            default => new Vector3($position->getX() + 0.5, $position->getY() + 0.5, $position->getZ() + 0.5),
        };
    }

    private static function alignToDetonationBucket(int $targetTick): int
    {
        $bucket = self::TNT_DETONATION_SYNC_BUCKET_TICKS;
        if ($bucket <= 1) {
            return $targetTick;
        }

        $remainder = $targetTick % $bucket;
        return $remainder === 0 ? $targetTick : ($targetTick + ($bucket - $remainder));
    }
}
