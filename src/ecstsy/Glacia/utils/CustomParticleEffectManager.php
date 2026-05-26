<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\Loader;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;
use pocketmine\world\World;

final class CustomParticleEffectManager
{
    public const ASTRAL_BEAM = "glacia:astral_beam";
    public const ASTRAL_BEAM_CORE = "glacia:astral_beam_core";
    public const ASTRAL_BEAM_SPARK = "glacia:astral_beam_spark";
    public const PATH_BEACON = "glacia:path_beacon";
    public const WARNING_RING = "glacia:warning_ring";
    public const ASTRAL_PULSE = "glacia:astral_pulse";
    public const HYDRA_FROST = "glacia:hydra_frost";
    public const HYDRA_FROST_SHARD = "glacia:hydra_frost_shard";
    public const HYDRA_FROST_MIST = "glacia:hydra_frost_mist";
    public const HYDRA_VOID = "glacia:hydra_void";
    public const HYDRA_VOID_CORE = "glacia:hydra_void_core";
    public const HYDRA_VOID_SHARD = "glacia:hydra_void_shard";
    public const HYDRA_WARNING = "glacia:hydra_warning";
    public const HYDRA_WARNING_ARC = "glacia:hydra_warning_arc";

    /** @var array<string, int> */
    private static array $trailTokens = [];

    private function __construct()
    {
    }

    public static function sendToPlayer(Player $player, Vector3 $position, string $particleName): void
    {
        if (!$player->isOnline()) {
            return;
        }

        $packet = new SpawnParticleEffectPacket();
        $packet->position = $position;
        $packet->particleName = $particleName;
        $player->getNetworkSession()->sendDataPacket($packet);
    }

    public static function sendToNearby(World $world, Vector3 $position, string $particleName, float $viewerRadius = 32.0): void
    {
        $radiusSq = max(1.0, $viewerRadius * $viewerRadius);
        foreach ($world->getPlayers() as $player) {
            if (!$player->isOnline()) {
                continue;
            }

            $delta = $player->getPosition()->subtractVector($position);
            $distanceSq = ($delta->x * $delta->x) + ($delta->y * $delta->y) + ($delta->z * $delta->z);
            if ($distanceSq > $radiusSq) {
                continue;
            }

            self::sendToPlayer($player, $position, $particleName);
        }
    }

    public static function line(
        World $world,
        Vector3 $from,
        Vector3 $to,
        string $particleName,
        int $density = 24,
        float $viewerRadius = 32.0
    ): void {
        if ($density <= 0) {
            return;
        }

        $dx = ($to->x - $from->x) / $density;
        $dy = ($to->y - $from->y) / $density;
        $dz = ($to->z - $from->z) / $density;
        for ($i = 0; $i <= $density; $i++) {
            self::sendToNearby(
                $world,
                new Vector3(
                    $from->x + ($dx * $i),
                    $from->y + ($dy * $i),
                    $from->z + ($dz * $i)
                ),
                $particleName,
                $viewerRadius
            );
        }
    }

    /**
     * @param list<string> $particleNames
     */
    public static function lineVariants(
        World $world,
        Vector3 $from,
        Vector3 $to,
        array $particleNames,
        int $density = 24,
        float $viewerRadius = 32.0
    ): void {
        if ($density <= 0 || $particleNames === []) {
            return;
        }

        $dx = ($to->x - $from->x) / $density;
        $dy = ($to->y - $from->y) / $density;
        $dz = ($to->z - $from->z) / $density;
        $variantCount = count($particleNames);

        for ($i = 0; $i <= $density; $i++) {
            self::sendToNearby(
                $world,
                new Vector3(
                    $from->x + ($dx * $i),
                    $from->y + ($dy * $i),
                    $from->z + ($dz * $i)
                ),
                $particleNames[$i % $variantCount],
                $viewerRadius
            );
        }
    }

    public static function ring(
        World $world,
        Vector3 $center,
        float $radius,
        string $particleName,
        int $count = 24,
        float $yOffset = 0.15,
        float $viewerRadius = 32.0
    ): void {
        if ($count <= 0 || $radius <= 0.0) {
            return;
        }

        $step = (2.0 * M_PI) / $count;
        for ($i = 0; $i < $count; $i++) {
            $angle = $i * $step;
            self::sendToNearby(
                $world,
                new Vector3(
                    $center->x + (cos($angle) * $radius),
                    $center->y + $yOffset,
                    $center->z + (sin($angle) * $radius)
                ),
                $particleName,
                $viewerRadius
            );
        }
    }

    /**
     * @param list<string> $particleNames
     */
    public static function ringVariants(
        World $world,
        Vector3 $center,
        float $radius,
        array $particleNames,
        int $count = 24,
        float $yOffset = 0.15,
        float $viewerRadius = 32.0
    ): void {
        if ($count <= 0 || $radius <= 0.0 || $particleNames === []) {
            return;
        }

        $step = (2.0 * M_PI) / $count;
        $variantCount = count($particleNames);
        for ($i = 0; $i < $count; $i++) {
            $angle = $i * $step;
            self::sendToNearby(
                $world,
                new Vector3(
                    $center->x + (cos($angle) * $radius),
                    $center->y + $yOffset,
                    $center->z + (sin($angle) * $radius)
                ),
                $particleNames[$i % $variantCount],
                $viewerRadius
            );
        }
    }

    public static function burst(
        World $world,
        Vector3 $center,
        string $particleName,
        int $count = 14,
        float $horizontalSpread = 0.8,
        float $verticalSpread = 0.7,
        float $viewerRadius = 32.0
    ): void {
        if ($count <= 0) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $ox = ((mt_rand(-100, 100) / 100.0) * $horizontalSpread);
            $oy = ((mt_rand(0, 100) / 100.0) * $verticalSpread);
            $oz = ((mt_rand(-100, 100) / 100.0) * $horizontalSpread);
            self::sendToNearby(
                $world,
                new Vector3($center->x + $ox, $center->y + $oy, $center->z + $oz),
                $particleName,
                $viewerRadius
            );
        }
    }

    /**
     * @param list<string> $particleNames
     */
    public static function burstVariants(
        World $world,
        Vector3 $center,
        array $particleNames,
        int $count = 14,
        float $horizontalSpread = 0.8,
        float $verticalSpread = 0.7,
        float $viewerRadius = 32.0
    ): void {
        if ($count <= 0 || $particleNames === []) {
            return;
        }

        $variantCount = count($particleNames);
        for ($i = 0; $i < $count; $i++) {
            $ox = ((mt_rand(-100, 100) / 100.0) * $horizontalSpread);
            $oy = ((mt_rand(0, 100) / 100.0) * $verticalSpread);
            $oz = ((mt_rand(-100, 100) / 100.0) * $horizontalSpread);
            self::sendToNearby(
                $world,
                new Vector3($center->x + $ox, $center->y + $oy, $center->z + $oz),
                $particleNames[$i % $variantCount],
                $viewerRadius
            );
        }
    }

    public static function column(
        World $world,
        Vector3 $base,
        string $particleName,
        float $height = 2.4,
        int $density = 8,
        float $viewerRadius = 32.0
    ): void {
        if ($density <= 0 || $height <= 0.0) {
            return;
        }

        self::line(
            $world,
            $base,
            $base->add(0, $height, 0),
            $particleName,
            $density,
            $viewerRadius
        );
    }

    /**
     * @param list<string> $particleNames
     */
    public static function columnVariants(
        World $world,
        Vector3 $base,
        array $particleNames,
        float $height = 2.4,
        int $density = 8,
        float $viewerRadius = 32.0
    ): void {
        if ($density <= 0 || $height <= 0.0 || $particleNames === []) {
            return;
        }

        self::lineVariants(
            $world,
            $base,
            $base->add(0, $height, 0),
            $particleNames,
            $density,
            $viewerRadius
        );
    }

    public static function stackedRing(
        World $world,
        Vector3 $center,
        float $radius,
        string $particleName,
        int $layers = 3,
        int $count = 24,
        float $layerStep = 0.24,
        float $viewerRadius = 32.0
    ): void {
        if ($layers <= 0) {
            return;
        }

        for ($layer = 0; $layer < $layers; $layer++) {
            self::ring(
                $world,
                $center,
                $radius,
                $particleName,
                $count,
                0.08 + ($layer * $layerStep),
                $viewerRadius
            );
        }
    }

    public static function pulse(
        World $world,
        Vector3 $center,
        string $ringParticle,
        string $burstParticle,
        float $radius,
        int $ringCount = 18,
        int $burstCount = 10,
        float $viewerRadius = 32.0
    ): void {
        self::stackedRing($world, $center, $radius, $ringParticle, 2, $ringCount, 0.22, $viewerRadius);
        self::burst($world, $center->add(0, 0.16, 0), $burstParticle, $burstCount, max(0.25, $radius * 0.22), 0.45, $viewerRadius);
    }

    public static function startTrailTo(Player $player, Position $destination, int $seconds = 14): bool
    {
        if (!$player->isOnline() || $player->getWorld() !== $destination->getWorld()) {
            return false;
        }

        $key = strtolower($player->getUniqueId()->toString());
        $token = (self::$trailTokens[$key] ?? 0) + 1;
        self::$trailTokens[$key] = $token;

        $scheduler = Loader::getInstance()->getScheduler();
        $frames = max(6, $seconds * 2);

        for ($frame = 0; $frame < $frames; $frame++) {
            $scheduler->scheduleDelayedTask(new ClosureTask(
                static function () use ($player, $destination, $key, $token): void {
                    if (
                        !$player->isOnline() ||
                        $player->isClosed() ||
                        (self::$trailTokens[$key] ?? 0) !== $token ||
                        $player->getWorld() !== $destination->getWorld()
                    ) {
                        return;
                    }

                    self::renderTrailFrame($player, $destination);
                }
            ), $frame * 10);
        }

        $scheduler->scheduleDelayedTask(new ClosureTask(
            static function () use ($key, $token): void {
                if ((self::$trailTokens[$key] ?? 0) === $token) {
                    unset(self::$trailTokens[$key]);
                }
            }
        ), ($frames * 10) + 2);

        return true;
    }

    private static function renderTrailFrame(Player $player, Position $destination): void
    {
        $current = $player->getPosition();
        $dx = $destination->getX() - $current->getX();
        $dz = $destination->getZ() - $current->getZ();
        $flatDistance = sqrt(($dx * $dx) + ($dz * $dz));

        if ($flatDistance <= 0.35) {
            self::ring($player->getWorld(), $destination->add(0, 0.05, 0), 1.2, self::WARNING_RING, 12, 0.12, 48.0);
            self::burst($player->getWorld(), $destination->add(0, 0.4, 0), self::PATH_BEACON, 8, 0.25, 0.35, 48.0);
            return;
        }

        $origin = $current->add(0, 0.28, 0);
        $shownDistance = min($flatDistance, 22.0);
        $normalX = $dx / $flatDistance;
        $normalZ = $dz / $flatDistance;
        $heightDelta = $destination->getY() - $current->getY();
        $tipHeight = max(0.18, min(1.25, 0.35 + ($heightDelta * 0.08)));

        $tip = new Vector3(
            $origin->x + ($normalX * $shownDistance),
            $origin->y + $tipHeight,
            $origin->z + ($normalZ * $shownDistance)
        );

        self::line(
            $player->getWorld(),
            new Vector3($origin->x, $origin->y, $origin->z),
            $tip,
            self::PATH_BEACON,
            max(8, (int) round($shownDistance / 1.35)),
            48.0
        );
        self::burst($player->getWorld(), $tip, self::PATH_BEACON, 6, 0.22, 0.30, 48.0);

        if ($current->distance($destination) <= 32.0) {
            self::ring($player->getWorld(), $destination->add(0, 0.05, 0), 1.35, self::WARNING_RING, 14, 0.12, 48.0);
            self::line(
                $player->getWorld(),
                $destination->add(0, 0.15, 0),
                $destination->add(0, 2.1, 0),
                self::ASTRAL_BEAM,
                6,
                48.0
            );
        }
    }
}
