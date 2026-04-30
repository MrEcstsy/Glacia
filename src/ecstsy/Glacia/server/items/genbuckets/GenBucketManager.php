<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\genbuckets;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\world\World;

final class GenBucketManager {

    private static bool $enabled = true;
    private static int $maxLength = 256;
    private static int $blocksPerStep = 2;
    private static int $stepIntervalTicks = 2;
    private static int $useCooldownSeconds = 1;
    private static bool $requireSneakToStart = true;
    private static bool $allowWaterPass = true;
    private static bool $stopOnGeneratedBreak = true;
    /** @var array<string, int> */
    private static array $costs = [];
    private static bool $oceanBusterEnabled = true;
    private static int $oceanBusterCost = 500000;
    private static string $oceanBusterClaimedPermission = "glacia.sponge.use";
    /** @var string[] */
    private static array $oceanBusterOverworlds = ["world"];

    public static function loadFromConfig(): void
    {
        self::$enabled = true;
        self::$maxLength = 256;
        self::$blocksPerStep = 2;
        self::$stepIntervalTicks = 2;
        self::$useCooldownSeconds = 1;
        self::$requireSneakToStart = true;
        self::$allowWaterPass = true;
        self::$stopOnGeneratedBreak = true;
        self::$costs = [];
        self::$oceanBusterEnabled = true;
        self::$oceanBusterCost = 500000;
        self::$oceanBusterClaimedPermission = "glacia.sponge.use";
        self::$oceanBusterOverworlds = ["world"];

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "genbuckets.yml");
        if ($config === null) {
            return;
        }

        self::$enabled = (bool) $config->get("enabled", true);
        self::$maxLength = max(1, (int) $config->get("max-length", 256));
        self::$blocksPerStep = max(1, (int) $config->get("blocks-per-step", 2));
        self::$stepIntervalTicks = max(1, (int) $config->get("step-interval-ticks", 2));
        self::$useCooldownSeconds = max(0, (int) $config->get("use-cooldown-seconds", 1));
        self::$requireSneakToStart = (bool) $config->get("require-sneak-to-start", true);
        self::$allowWaterPass = (bool) $config->get("allow-water-pass", true);
        self::$stopOnGeneratedBreak = (bool) $config->get("stop-on-generated-break", true);

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

    public static function getUseCooldownSeconds(): int
    {
        return self::$useCooldownSeconds;
    }

    public static function requireSneakToStart(): bool
    {
        return self::$requireSneakToStart;
    }

    public static function allowWaterPass(): bool
    {
        return self::$allowWaterPass;
    }

    public static function stopOnGeneratedBreak(): bool
    {
        return self::$stopOnGeneratedBreak;
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
            default => null,
        };
    }
}
