<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\cannon;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\world\Position;

/**
 * misc.yml {@code cannon-rules} — raid cannon tuning (TNT caps, altitude, underwater fragile bypass).
 * Legacy {@code cosmic-cannon} merges underneath so older configs keep working.
 */
final class CannonRulesConfig {

    private static bool $loaded = false;
    private static bool $enabled = true;
    /** Explosion epicenter floor Y strictly greater than this disables full explosions (often used near build/roof limits). */
    private static int $suppressExplosionYAbove = 255;
    private static bool $waterVulnerableBypass = true;
    private static int $maxPrimedTnt = 500;
    private static int $maxFallingSandPerChunk = 64;

    public static function warmCache(): void {
        self::$loaded = false;
        self::read();
    }

    private static function read(): void {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $legacy = (array) ($cfg?->getNested("cosmic-cannon", []) ?? []);
        /** @var array<string, mixed> $cc cannon-rules overrides legacy keys when both exist */
        $cc = array_merge($legacy, (array) ($cfg?->getNested("cannon-rules", []) ?? []));

        self::$enabled = (bool) ($cc["enabled"] ?? true);
        self::$suppressExplosionYAbove = max(-64, min(511, (int) ($cc["suppress-explosion-when-y-above"] ?? 255)));
        self::$waterVulnerableBypass = (bool) ($cc["water-vulnerable-blocks-bypass"] ?? true);
        self::$maxPrimedTnt = max(1, min(50000, (int) ($cc["max-primed-tnt-server-wide"] ?? 500)));
        self::$maxFallingSandPerChunk = max(1, min(8192, (int) ($cc["max-glacia-falling-sand-per-chunk"] ?? 64)));
    }

    public static function enabled(): bool {
        self::read();

        return self::$enabled;
    }

    public static function suppressExplosionFullyAt(Position $center): bool {
        if (!self::enabled()) {
            return false;
        }

        return $center->getFloorY() > self::$suppressExplosionYAbove;
    }

    public static function waterVulnerableBypassEnabled(): bool {
        self::read();

        return self::$waterVulnerableBypass;
    }

    public static function getMaxPrimedTnt(): int {
        self::read();

        return self::$maxPrimedTnt;
    }

    public static function getMaxFallingSandPerChunk(): int {
        self::read();

        return self::$maxFallingSandPerChunk;
    }

    /** @internal Re-read after /greload */
    public static function reload(): void {
        self::$loaded = false;
    }
}
