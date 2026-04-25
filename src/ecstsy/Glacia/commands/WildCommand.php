<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use pocketmine\world\World;

final class WildCommand extends BaseCommand {

    private const DEFAULT_MIN_RADIUS = 6000;
    private const DEFAULT_MAX_RADIUS = 7000;
    private const DEFAULT_ATTEMPTS = 28;
    private const HARD_MAX_RADIUS = 7000;
    private const NEARBY_LAND_SEARCH_RADIUS = 8;
    private const POPULATION_RETRY_DELAY = 40;

    /** @var int[] */
    private const UNSAFE_FOOTING = [
        BlockTypeIds::WATER,
        BlockTypeIds::LAVA,
        BlockTypeIds::CACTUS,
        BlockTypeIds::MAGMA,
        BlockTypeIds::FIRE,
        BlockTypeIds::SOUL_FIRE,
        BlockTypeIds::SWEET_BERRY_BUSH,
        BlockTypeIds::COBWEB
    ];

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $world = $sender->getServer()->getWorldManager()->getDefaultWorld();
        if ($world === null) {
            $sender->sendMessage(C::colorize("&r&cThe default world is not loaded."));
            return;
        }

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $minRadius = max(1, (int) ($config?->get("wild-min-radius", self::DEFAULT_MIN_RADIUS) ?? self::DEFAULT_MIN_RADIUS));
        $configuredMaxRadius = (int) ($config?->get("wild-max-radius", self::DEFAULT_MAX_RADIUS) ?? self::DEFAULT_MAX_RADIUS);
        $maxRadius = max($minRadius, min(self::HARD_MAX_RADIUS, $configuredMaxRadius));
        $attempts = max(1, (int) ($config?->get("wild-attempts", self::DEFAULT_ATTEMPTS) ?? self::DEFAULT_ATTEMPTS));

        $sender->sendMessage(C::colorize("&r&bSearching the wilderness for a safe location..."));
        self::searchAndTeleport($sender, $world, $minRadius, $maxRadius, $attempts);
    }

    public static function searchAndTeleport(Player $player, World $world, int $minRadius, int $maxRadius, int $attempts): void
    {
        if (!$player->isConnected()) {
            return;
        }

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            [$x, $z, $distance] = self::pickCandidate($world, $minRadius, $maxRadius);
            if (self::teleportAtCandidate($player, $world, $minRadius, $x, $z, $distance, true)) {
                return;
            }
        }

        $player->sendMessage(C::colorize("&r&cFailed to find a safe wilderness location. Try again."));
    }

    public static function teleportAtCandidate(Player $player, World $world, int $minRadius, int $x, int $z, int $distance, bool $allowPopulateRetry): bool
    {
        if (!$player->isConnected()) {
            return true;
        }

        $chunkX = $x >> 4;
        $chunkZ = $z >> 4;
        if (!$world->isChunkLoaded($chunkX, $chunkZ)) {
            if (!$allowPopulateRetry) {
                return false;
            }

            $world->orderChunkPopulation($chunkX, $chunkZ, null);
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(
                new class($player, $world, $minRadius, $x, $z, $distance) extends Task {

                    public function __construct(
                        private Player $player,
                        private World $world,
                        private int $minRadius,
                        private int $x,
                        private int $z,
                        private int $distance
                    ) {}

                    public function onRun(): void
                    {
                        if (
                            !WildCommand::teleportAtCandidate($this->player, $this->world, $this->minRadius, $this->x, $this->z, $this->distance, false) &&
                            $this->player->isConnected()
                        ) {
                            $this->player->sendMessage(C::colorize("&r&cFailed to find a safe wilderness location. Try again."));
                        }
                    }
                },
                self::POPULATION_RETRY_DELAY
            );
            return true;
        }

        $safe = self::resolveSafeSpawn($world, $x, $z, $minRadius);
        if ($safe === null) {
            return false;
        }

        GlaciaUtils::timedTeleport($player, $safe->asVector3(), 5);
        $player->sendMessage(C::colorize("&r&bWilderness location found &7(" . number_format($distance) . " blocks from spawn)&b."));
        return true;
    }

    private static function pickCandidate(World $world, int $minRadius, int $maxRadius): array
    {
        $spawn = $world->getSpawnLocation();
        $spawnX = (int) round($spawn->getX());
        $spawnZ = (int) round($spawn->getZ());

        $angle = mt_rand(0, 62831) / 10000;
        $distance = mt_rand($minRadius, $maxRadius);
        $x = (int) round($spawnX + cos($angle) * $distance);
        $z = (int) round($spawnZ + sin($angle) * $distance);

        return [$x, $z, $distance];
    }

    private static function resolveSafeSpawn(World $world, int $x, int $z, int $minRadius): ?Position
    {
        $spawn = $world->getSpawnLocation();

        $surfaceY = self::resolveSurfaceY($world, $x, $z);
        if ($surfaceY === null) {
            return null;
        }

        $candidate = new Position($x + 0.5, $surfaceY, $z + 0.5, $world);
        if ($candidate->distanceSquared($spawn) < ($minRadius * $minRadius)) {
            return null;
        }

        if (self::isValidSpawnPosition($candidate)) {
            return $candidate;
        }

        return self::findNearbySafePosition($candidate, self::NEARBY_LAND_SEARCH_RADIUS, $spawn, $minRadius);
    }

    private static function resolveSurfaceY(World $world, int $x, int $z): ?int
    {
        $topY = $world->getHighestBlockAt($x, $z);
        if ($topY <= 0) {
            return null;
        }

        $topBlock = $world->getBlockAt($x, $topY, $z);
        if (!self::isUnsafeTopBlock($topBlock)) {
            return $topY + 1;
        }

        for ($y = $topY; $y >= max(1, $topY - 12); --$y) {
            $ground = $world->getBlockAt($x, $y, $z);
            if (!$ground->isSolid() || self::isUnsafeGround($ground)) {
                continue;
            }

            $feet = $world->getBlockAt($x, $y + 1, $z);
            $head = $world->getBlockAt($x, $y + 2, $z);
            if (self::isPassableForSpawn($feet) && self::isPassableForSpawn($head)) {
                return $y + 1;
            }
        }

        return null;
    }

    private static function findNearbySafePosition(Position $origin, int $radius, Position $spawn, int $minRadius): ?Position
    {
        $world = $origin->getWorld();
        $originX = $origin->getFloorX();
        $originZ = $origin->getFloorZ();

        for ($r = 1; $r <= $radius; $r++) {
            for ($dx = -$r; $dx <= $r; $dx++) {
                for ($dz = -$r; $dz <= $r; $dz++) {
                    if (max(abs($dx), abs($dz)) !== $r) {
                        continue;
                    }

                    $x = $originX + $dx;
                    $z = $originZ + $dz;
                    if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                        continue;
                    }

                    $surfaceY = self::resolveSurfaceY($world, $x, $z);
                    if ($surfaceY === null) {
                        continue;
                    }

                    $candidate = new Position($x + 0.5, $surfaceY, $z + 0.5, $world);
                    if ($candidate->distanceSquared($spawn) < ($minRadius * $minRadius)) {
                        continue;
                    }

                    if (self::isValidSpawnPosition($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private static function isValidSpawnPosition(Position $position): bool
    {
        $world = $position->getWorld();
        $x = $position->getFloorX();
        $y = $position->getFloorY();
        $z = $position->getFloorZ();

        if ($y <= 1) {
            return false;
        }

        $ground = $world->getBlockAt($x, $y - 1, $z);
        $feet = $world->getBlockAt($x, $y, $z);
        $head = $world->getBlockAt($x, $y + 1, $z);

        if (!$ground->isSolid() || self::isUnsafeGround($ground)) {
            return false;
        }

        if (!self::isPassableForSpawn($feet) || !self::isPassableForSpawn($head)) {
            return false;
        }

        if (ClaimManager::getInstance()->getClaimAtPosition($position) !== null) {
            return false;
        }

        if (Loader::getRegionManager()->getRegionAt($position) !== null) {
            return false;
        }

        return true;
    }

    private static function isPassableForSpawn(Block $block): bool
    {
        return !$block->isSolid() && !($block instanceof Liquid) && !self::isUnsafeBlockId($block->getTypeId());
    }

    private static function isUnsafeGround(Block $block): bool
    {
        return $block instanceof Liquid || !$block->isSolid() || $block->isTransparent() || self::isUnsafeBlockId($block->getTypeId());
    }

    private static function isUnsafeTopBlock(Block $block): bool
    {
        return $block instanceof Liquid || self::isUnsafeBlockId($block->getTypeId());
    }

    private static function isUnsafeBlockId(int $typeId): bool
    {
        return in_array($typeId, self::UNSAFE_FOOTING, true);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
