<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\disguises;

use ecstsy\Glacia\Loader;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\FloatMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use function strtolower;

final class DisguiseManager
{
    /** @var array<string, array{id: string, runtimeId: int, viewers: array<string, true>}> */
    private array $active = [];

    /** @var array<int, string> */
    private array $runtimeToUuid = [];

    /** @var array<string, array<string, mixed>> */
    private static array $registry = [
        "cow" => [
            "display" => "Cow",
            "entity_id" => EntityIds::COW,
            "description" => "Blend into the herd with a harmless farm disguise.",
        ],
        "pig" => [
            "display" => "Pig",
            "entity_id" => EntityIds::PIG,
            "description" => "Appear as a pink farm animal.",
        ],
        "wolf" => [
            "display" => "Wolf",
            "entity_id" => EntityIds::WOLF,
            "description" => "Roam around disguised as a loyal wolf.",
        ],
        "sheep" => [
            "display" => "Sheep",
            "entity_id" => EntityIds::SHEEP,
            "description" => "Take on the shape of a common sheep.",
        ],
        "chicken" => [
            "display" => "Chicken",
            "entity_id" => EntityIds::CHICKEN,
            "description" => "Waddle around as a tiny farm bird.",
            "scale" => 0.88,
        ],
        "baby_chicken" => [
            "display" => "Baby Chicken",
            "entity_id" => EntityIds::CHICKEN,
            "description" => "A tiny baby chicken disguise with extra chaos.",
            "scale" => 0.52,
            "baby" => true,
        ],
        "zombie" => [
            "display" => "Zombie",
            "entity_id" => EntityIds::ZOMBIE,
            "description" => "Creep through the world as a shambling undead.",
        ],
        "skeleton" => [
            "display" => "Skeleton",
            "entity_id" => EntityIds::SKELETON,
            "description" => "Appear as a rattling skeleton warrior.",
        ],
        "creeper" => [
            "display" => "Creeper",
            "entity_id" => EntityIds::CREEPER,
            "description" => "Wear the look of a silent explosive stalker.",
        ],
        "spider" => [
            "display" => "Spider",
            "entity_id" => EntityIds::SPIDER,
            "description" => "Skitter around disguised as a giant spider.",
        ],
        "blaze" => [
            "display" => "Blaze",
            "entity_id" => EntityIds::BLAZE,
            "description" => "Burn bright as a floating blaze disguise.",
        ],
        "wither" => [
            "display" => "Wither",
            "entity_id" => EntityIds::WITHER,
            "description" => "Drift through the world as a scaled-down Wither.",
            "scale" => 0.42,
        ],
        "ghast" => [
            "display" => "Ghast",
            "entity_id" => EntityIds::GHAST,
            "description" => "Float ominously as a shrieking ghast.",
            "scale" => 0.65,
        ],
        "snowman" => [
            "display" => "Snowman",
            "entity_id" => EntityIds::SNOW_GOLEM,
            "description" => "Become a frosty snowman with a pumpkin stare.",
        ],
        "zombie_pigman" => [
            "display" => "Zombie Pigman",
            "entity_id" => EntityIds::ZOMBIE_PIGMAN,
            "description" => "Take on the look of a classic nether pigman.",
        ],
        "bat" => [
            "display" => "Bat",
            "entity_id" => EntityIds::BAT,
            "description" => "Flutter around disguised as a cave bat.",
        ],
        "ender_dragon" => [
            "display" => "Ender Dragon",
            "entity_id" => EntityIds::ENDER_DRAGON,
            "description" => "Channel a scaled-down Ender Dragon disguise.",
            "scale" => 0.24,
        ],
        "polar_bear" => [
            "display" => "Polar Bear",
            "entity_id" => EntityIds::POLAR_BEAR,
            "description" => "Lumber around as an icy polar bear.",
        ],
        "mega_knight" => [
            "display" => "Mega Knight",
            "entity_id" => EntityIds::IRON_GOLEM,
            "description" => "Suit up as a heavy Mega Knight-style bruiser.",
            "scale" => 0.9,
        ],
        "rainbow_sheep" => [
            "display" => "Rainbow Sheep",
            "entity_id" => EntityIds::SHEEP,
            "description" => "A playful sheep disguise with a secret shimmer.",
            "special_name" => "jeb_",
        ],
        "upside_sheep" => [
            "display" => "Upside-Down Sheep",
            "entity_id" => EntityIds::SHEEP,
            "description" => "Flip expectations with an upside-down sheep disguise.",
            "special_name" => "Dinnerbone",
        ],
    ];

    public function __construct(private Loader $plugin)
    {
        $this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function (): void {
                $this->tick();
            }
        ), 2);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getRegistry(): array
    {
        return self::$registry;
    }

    public static function hasDisguise(string $id): bool
    {
        return isset(self::$registry[strtolower(trim($id))]);
    }

    public static function normalizeId(string $id): string
    {
        return strtolower(trim($id));
    }

    public static function getDisplayName(string $id): string
    {
        $id = self::normalizeId($id);
        return (string) (self::$registry[$id]["display"] ?? ucfirst(str_replace("_", " ", $id)));
    }

    public static function getDescription(string $id): string
    {
        $id = self::normalizeId($id);
        return (string) (self::$registry[$id]["description"] ?? "Unlock a new disguise.");
    }

    public static function getPermission(string $id): string
    {
        return "glacia.disguise." . self::normalizeId($id);
    }

    /**
     * @return string[]
     */
    public static function getIds(): array
    {
        return array_keys(self::$registry);
    }

    public function isDisguised(Player $player): bool
    {
        return isset($this->active[$player->getUniqueId()->toString()]);
    }

    public function getActiveDisguiseId(Player $player): ?string
    {
        return $this->active[$player->getUniqueId()->toString()]["id"] ?? null;
    }

    public function apply(Player $player, string $id): bool
    {
        $id = self::normalizeId($id);
        if (!isset(self::$registry[$id])) {
            return false;
        }

        $uuid = $player->getUniqueId()->toString();
        if (($this->active[$uuid]["id"] ?? null) === $id) {
            return true;
        }

        $this->clear($player);

        $runtimeId = Entity::nextRuntimeId();
        $this->active[$uuid] = [
            "id" => $id,
            "runtimeId" => $runtimeId,
            "viewers" => [],
        ];
        $this->runtimeToUuid[$runtimeId] = $uuid;

        $session = Loader::getPlayerManager()->getSession($player);
        $session?->setSetting("active_disguise", $id);

        $this->syncPlayer($player);
        return true;
    }

    public function clear(Player $player): void
    {
        $uuid = $player->getUniqueId()->toString();
        $entry = $this->active[$uuid] ?? null;
        if ($entry === null) {
            Loader::getPlayerManager()->getSession($player)?->setSetting("active_disguise", null);
            return;
        }

        foreach (array_keys($entry["viewers"]) as $viewerUuid) {
            $viewer = $this->getPlayerByUuidString($viewerUuid);
            if ($viewer instanceof Player && $viewer->isOnline()) {
                $viewer->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($entry["runtimeId"]));
                $viewer->showPlayer($player);
            }
        }

        unset($this->runtimeToUuid[$entry["runtimeId"]], $this->active[$uuid]);
        Loader::getPlayerManager()->getSession($player)?->setSetting("active_disguise", null);
    }

    public function handleAttackPacket(Player $attacker, int $runtimeId): bool
    {
        $uuid = $this->runtimeToUuid[$runtimeId] ?? null;
        if ($uuid === null) {
            return false;
        }

        $target = $this->plugin->getServer()->getPlayerByUUID(\Ramsey\Uuid\Uuid::fromString($uuid));
        if (!$target instanceof Player || !$target->isOnline() || $target->getWorld() !== $attacker->getWorld()) {
            return false;
        }

        if ($attacker->getId() === $target->getId()) {
            return true;
        }

        $attacker->attackEntity($target);
        return true;
    }

    public function removeFor(Player $player): void
    {
        if ($this->isDisguised($player)) {
            $this->clear($player);
        }
    }

    public function syncPlayer(Player $player): void
    {
        $uuid = $player->getUniqueId()->toString();
        $entry = $this->active[$uuid] ?? null;
        if ($entry === null || !$player->isOnline()) {
            return;
        }

        $profile = self::$registry[$entry["id"]] ?? null;
        if ($profile === null) {
            $this->clear($player);
            return;
        }

        $currentViewers = [];
        foreach ($player->getWorld()->getPlayers() as $viewer) {
            if ($viewer->getId() === $player->getId()) {
                continue;
            }

            $viewerUuid = $viewer->getUniqueId()->toString();
            $currentViewers[$viewerUuid] = true;

            if (!isset($entry["viewers"][$viewerUuid])) {
                $viewer->hidePlayer($player);
                $viewer->getNetworkSession()->sendDataPacket($this->createAddPacket($entry["runtimeId"], $player, $profile));
            }

            $viewer->getNetworkSession()->sendDataPacket(MoveActorAbsolutePacket::create(
                $entry["runtimeId"],
                $player->getPosition()->asVector3(),
                $player->getLocation()->pitch,
                $player->getLocation()->yaw,
                $player->getLocation()->yaw,
                MoveActorAbsolutePacket::FLAG_GROUND
            ));
        }

        foreach (array_keys($entry["viewers"]) as $viewerUuid) {
            if (isset($currentViewers[$viewerUuid])) {
                continue;
            }

            $viewer = $this->getPlayerByUuidString($viewerUuid);
            if ($viewer instanceof Player && $viewer->isOnline()) {
                $viewer->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($entry["runtimeId"]));
                $viewer->showPlayer($player);
            }
        }

        $entry["viewers"] = $currentViewers;
        $this->active[$uuid] = $entry;
    }

    public function refreshMetadata(Player $player): void
    {
        $uuid = $player->getUniqueId()->toString();
        $entry = $this->active[$uuid] ?? null;
        if ($entry === null) {
            return;
        }

        $profile = self::$registry[$entry["id"]] ?? null;
        if ($profile === null) {
            return;
        }

        $metadata = $this->buildMetadata($profile);
        $packet = SetActorDataPacket::create($entry["runtimeId"], $metadata, new PropertySyncData([], []), 0);
        foreach (array_keys($entry["viewers"]) as $viewerUuid) {
            $viewer = $this->getPlayerByUuidString($viewerUuid);
            if ($viewer instanceof Player && $viewer->isOnline()) {
                $viewer->getNetworkSession()->sendDataPacket(clone $packet);
            }
        }
    }

    private function tick(): void
    {
        foreach (array_keys($this->active) as $uuid) {
            $player = $this->getPlayerByUuidString($uuid);
            if (!$player instanceof Player || !$player->isOnline()) {
                $entry = $this->active[$uuid] ?? null;
                if (is_array($entry) && isset($entry["runtimeId"])) {
                    unset($this->runtimeToUuid[(int) $entry["runtimeId"]]);
                }
                unset($this->active[$uuid]);
                continue;
            }

            $this->syncPlayer($player);
        }
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function createAddPacket(int $runtimeId, Player $player, array $profile): AddActorPacket
    {
        return AddActorPacket::create(
            $runtimeId,
            $runtimeId,
            (string) $profile["entity_id"],
            $player->getPosition()->asVector3(),
            null,
            $player->getLocation()->pitch,
            $player->getLocation()->yaw,
            $player->getLocation()->yaw,
            $player->getLocation()->yaw,
            $this->buildSpawnAttributes(),
            $this->buildMetadata($profile),
            new PropertySyncData([], []),
            []
        );
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<int, mixed>
     */
    private function buildMetadata(array $profile): array
    {
        $flags =
            (1 << EntityMetadataFlags::SILENT) |
            (1 << EntityMetadataFlags::NO_AI);

        if ((bool) ($profile["baby"] ?? false)) {
            $flags |= (1 << EntityMetadataFlags::BABY);
        }

        $metadata = [
            EntityMetadataProperties::FLAGS => new LongMetadataProperty($flags),
            EntityMetadataProperties::SCALE => new FloatMetadataProperty((float) ($profile["scale"] ?? 1.0)),
        ];

        if (isset($profile["variant"])) {
            $metadata[EntityMetadataProperties::VARIANT] = new IntMetadataProperty((int) $profile["variant"]);
        }

        if (isset($profile["special_name"])) {
            $metadata[EntityMetadataProperties::NAMETAG] = new StringMetadataProperty((string) $profile["special_name"]);
        }

        return $metadata;
    }

    /**
     * The client behaves badly when living disguises are spawned without
     * baseline attributes. The dragon disguise in particular can fall into a
     * broken death-phase presentation and flood particles. Sending a normal
     * living attribute set keeps the fake actor stable.
     *
     * @return list<NetworkAttribute>
     */
    private function buildSpawnAttributes(): array
    {
        $factory = AttributeFactory::getInstance();
        $attributes = [
            $factory->mustGet(Attribute::HEALTH)->setValue(20.0),
            $factory->mustGet(Attribute::MOVEMENT_SPEED)->setValue(0.10),
            $factory->mustGet(Attribute::KNOCKBACK_RESISTANCE)->setValue(0.0),
            $factory->mustGet(Attribute::FOLLOW_RANGE)->setValue(16.0),
            $factory->mustGet(Attribute::ATTACK_DAMAGE)->setValue(1.0),
            $factory->mustGet(Attribute::ABSORPTION)->setValue(0.0),
        ];

        return array_map(
            static fn(Attribute $attribute): NetworkAttribute => new NetworkAttribute(
                $attribute->getId(),
                $attribute->getMinValue(),
                $attribute->getMaxValue(),
                $attribute->getValue(),
                $attribute->getDefaultValue(),
                []
            ),
            $attributes
        );
    }

    private function getPlayerByUuidString(string $uuid): ?Player
    {
        try {
            return $this->plugin->getServer()->getPlayerByUUID(\Ramsey\Uuid\Uuid::fromString($uuid));
        } catch (\Throwable) {
            return null;
        }
    }
}
