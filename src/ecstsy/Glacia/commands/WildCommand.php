<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldException;

final class WildCommand extends BaseCommand {

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
        $minRadius = max(1, (int) ($config?->get("wild-min-radius", 6000) ?? 6000));
        $maxRadius = max($minRadius, (int) ($config?->get("wild-max-radius", 10500) ?? 10500));
        $attempts = max(1, (int) ($config?->get("wild-attempts", 18) ?? 18));

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
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new class($player, $world, $minRadius, $x, $z, $distance) extends Task {

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
                    if (!WildCommand::teleportAtCandidate($this->player, $this->world, $this->minRadius, $this->x, $this->z, $this->distance, false) && $this->player->isConnected()) {
                        $this->player->sendMessage(C::colorize("&r&cFailed to find a safe wilderness location. Try again."));
                    }
                }
            }, 40);
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
        try {
            $safe = $world->getSafeSpawn(new Vector3($x, $spawn->getY(), $z));
        } catch (WorldException) {
            return null;
        }

        if ($safe->distanceSquared($spawn) < ($minRadius * $minRadius)) {
            return null;
        }

        return $safe;
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
