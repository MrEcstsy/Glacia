<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\genbuckets;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\player\Player;
use pocketmine\world\World;

final class GenBucketManager {

    private static bool $enabled = true;
    private static int $maxLength = 256;
    private static int $blocksPerStep = 2;
    private static int $stepIntervalTicks = 2;
    private static int $useCooldownMillis = 500;
    private static bool $requireSneakToStart = true;
    /** Skip sneak check when the player can fly (creative / builder flight) so shift isn’t required mid-air. */
    private static bool $relaxSneakForFlightCapable = true;
    private static bool $allowWaterPass = true;
    private static bool $stopOnGeneratedBreak = true;
    /** How many gen lines one player may run at once (walls / pillars in parallel). */
    private static int $maxConcurrentChainsPerPlayer = 16;
    /** Play a short place sound every N scheduler ticks while generating (1 = every tick with placements). */
    private static int $stepSoundIntervalTicks = 3;
    private static bool $placementStepSoundsEnabled = true;
    /** @var array<string, int> */
    private static array $costs = [];
    private static bool $oceanBusterEnabled = true;
    private static int $oceanBusterCost = 500000;
    private static string $oceanBusterClaimedPermission = "glacia.sponge.use";
    /** @var string[] */
    private static array $oceanBusterOverworlds = ["world"];
    /** Lava-wall gen buckets (horizontal/vertical lava lines). Separate from normal liquid flow. */
    private static bool $lavaWallGenEnabled = true;

    public static function loadFromConfig(): void
    {
        self::$enabled = true;
        self::$maxLength = 256;
        self::$blocksPerStep = 2;
        self::$stepIntervalTicks = 2;
        self::$useCooldownMillis = 500;
        self::$requireSneakToStart = true;
        self::$relaxSneakForFlightCapable = true;
        self::$allowWaterPass = true;
        self::$stopOnGeneratedBreak = true;
        self::$maxConcurrentChainsPerPlayer = 16;
        self::$stepSoundIntervalTicks = 3;
        self::$placementStepSoundsEnabled = true;
        self::$costs = [];
        self::$oceanBusterEnabled = true;
        self::$oceanBusterCost = 500000;
        self::$oceanBusterClaimedPermission = "glacia.sponge.use";
        self::$oceanBusterOverworlds = ["world"];
        self::$lavaWallGenEnabled = true;

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "genbuckets.yml");
        if ($config === null) {
            return;
        }

        self::$enabled = (bool) $config->get("enabled", true);
        self::$maxLength = max(1, (int) $config->get("max-length", 256));
        self::$blocksPerStep = max(1, (int) $config->get("blocks-per-step", 2));
        self::$stepIntervalTicks = max(1, (int) $config->get("step-interval-ticks", 2));

        if ($config->exists("use-cooldown-ms")) {
            self::$useCooldownMillis = max(0, (int) $config->get("use-cooldown-ms", 0));
        } else {
            $legacySeconds = max(0, (int) $config->get("use-cooldown-seconds", 1));
            self::$useCooldownMillis = $legacySeconds * 1000;
        }

        self::$requireSneakToStart = (bool) $config->get("require-sneak-to-start", true);
        self::$relaxSneakForFlightCapable = (bool) $config->get("relax-sneak-for-flight-capable", true);
        self::$allowWaterPass = (bool) $config->get("allow-water-pass", true);
        self::$stopOnGeneratedBreak = (bool) $config->get("stop-on-generated-break", true);
        self::$maxConcurrentChainsPerPlayer = max(1, (int) $config->get("max-concurrent-chains-per-player", 16));
        self::$stepSoundIntervalTicks = max(1, (int) $config->get("step-sound-interval-ticks", 3));
        self::$placementStepSoundsEnabled = (bool) $config->get("placement-step-sounds-enabled", true);

        $costs = $config->get("costs", []);
        if (!is_array($costs)) {
            return;
        }

        foreach ($costs as $material => $cost) {
            $normalized = self::normalizeMaterial((string) $material);
            if ($normalized === "" || !is_numeric($cost)) {
                continue;
            }

            self::$costs[$normalized] = max(0, (int) $cost);
        }

        $ocean = (array) ($config->get("ocean-buster", []) ?? []);
        self::$oceanBusterEnabled = (bool) ($ocean["enabled"] ?? true);
        self::$oceanBusterCost = max(0, (int) ($ocean["cost"] ?? 500000));
        self::$oceanBusterClaimedPermission = trim((string) ($ocean["claimed-use-permission"] ?? "glacia.sponge.use"));
        $overworlds = (array) ($ocean["overworld-worlds"] ?? ["world"]);
        self::$oceanBusterOverworlds = array_values(array_filter(array_map(static fn(mixed $v): string => strtolower(trim((string) $v)), $overworlds), static fn(string $v): bool => $v !== ""));
        if (self::$oceanBusterOverworlds === []) {
            self::$oceanBusterOverworlds = ["world"];
        }

        $lavaWall = (array) ($config->get("lava-wall-gen", []) ?? []);
        self::$lavaWallGenEnabled = (bool) ($lavaWall["enabled"] ?? true);
    }

    public static function isLavaWallGenEnabled(): bool
    {
        return self::$lavaWallGenEnabled;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    public static function getMaxLength(): int
    {
        return self::$maxLength;
    }

    public static function getCost(string $material): int
    {
        return self::$costs[self::normalizeMaterial($material)] ?? 0;
    }

    public static function getBlocksPerStep(): int
    {
        return self::$blocksPerStep;
    }

    public static function getStepIntervalTicks(): int
    {
        return self::$stepIntervalTicks;
    }

    public static function getUseCooldownMillis(): int
    {
        return self::$useCooldownMillis;
    }

    /**
     * Legacy helper (ceil seconds).
     *
     * @deprecated Use {@see getUseCooldownMillis()}
     */
    public static function getUseCooldownSeconds(): int
    {
        return self::$useCooldownMillis === 0 ? 0 : (int) ceil(self::$useCooldownMillis / 1000);
    }

    public static function requireSneakToStart(): bool
    {
        return self::$requireSneakToStart;
    }

    /** True if sneak is not enforced (off, sneaking, or flight-capable with relax enabled). */
    public static function mayStartWithoutSneakConflict(Player $player): bool
    {
        if (!self::$requireSneakToStart) {
            return true;
        }
        if ($player->isSneaking()) {
            return true;
        }
        return self::$relaxSneakForFlightCapable
            && ($player->getAllowFlight() || $player->isFlying());
    }

    public static function allowWaterPass(): bool
    {
        return self::$allowWaterPass;
    }

    public static function stopOnGeneratedBreak(): bool
    {
        return self::$stopOnGeneratedBreak;
    }

    public static function getMaxConcurrentChainsPerPlayer(): int
    {
        return self::$maxConcurrentChainsPerPlayer;
    }

    public static function getStepSoundIntervalTicks(): int
    {
        return self::$stepSoundIntervalTicks;
    }

    public static function placementStepSoundsEnabled(): bool
    {
        return self::$placementStepSoundsEnabled;
    }

    public static function isOceanBusterEnabled(): bool
    {
        return self::$oceanBusterEnabled;
    }

    public static function getOceanBusterCost(): int
    {
        return self::$oceanBusterCost;
    }

    public static function getOceanBusterClaimedPermission(): string
    {
        return self::$oceanBusterClaimedPermission;
    }

    public static function isOceanBusterOverworld(World $world): bool
    {
        return in_array(strtolower($world->getFolderName()), self::$oceanBusterOverworlds, true);
    }

    public static function canReplaceWithGeneratedBlock(Block $block): bool
    {
        return $block->getTypeId() === VanillaBlocks::AIR()->getTypeId()
            || (self::$allowWaterPass && $block instanceof Liquid);
    }

    /**
     * Per-material rules: lava wall gens only overwrite air so water/lava mixes do not cobblespam mid-line.
     */
    public static function mayGenReplaceBlock(Block $block, string $material): bool
    {
        if (!self::isLavaWallGenEnabled() && self::normalizeMaterial($material) === "lava") {
            return false;
        }
        if (self::normalizeMaterial($material) === "lava") {
            return $block->getTypeId() === VanillaBlocks::AIR()->getTypeId();
        }

        return self::canReplaceWithGeneratedBlock($block);
    }

    public static function normalizeMaterial(string $material): string
    {
        return strtolower(trim($material));
    }

    public static function normalizeDirection(string $direction): string
    {
        return strtolower(trim($direction));
    }

    public static function getFacing(string $direction): ?int
    {
        return match (self::normalizeDirection($direction)) {
            "north" => Facing::NORTH,
            "east" => Facing::EAST,
            "south" => Facing::SOUTH,
            "west" => Facing::WEST,
            "up" => Facing::UP,
            "down" => Facing::DOWN,
            default => null,
        };
    }

    public static function getBlock(string $material): ?Block
    {
        return match (self::normalizeMaterial($material)) {
            "sand" => VanillaBlocks::SAND(),
            "cobble", "cobblestone" => VanillaBlocks::COBBLESTONE(),
            "obsidian" => VanillaBlocks::OBSIDIAN(),
            "lava", "lavawall" => self::$lavaWallGenEnabled ? VanillaBlocks::LAVA() : null,
            default => null,
        };
    }
}
