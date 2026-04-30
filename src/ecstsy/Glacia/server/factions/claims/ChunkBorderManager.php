<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\claims;

use ecstsy\Glacia\Loader;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\particle\BlockBreakParticle;

final class ChunkBorderManager
{
    /** @var array<string, TaskHandler> */
    private static array $active = [];

    public static function toggle(Player $player): bool
    {
        $key = $player->getUniqueId()->toString();
        if (isset(self::$active[$key])) {
            self::$active[$key]->cancel();
            unset(self::$active[$key]);
            return false;
        }

        self::$active[$key] = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function () use ($player, $key): void {
                if (!$player->isConnected()) {
                    self::disable($key);
                    return;
                }
                self::renderBorders($player);
            }),
            12
        );
        return true;
    }

    private static function disable(string $key): void
    {
        if (!isset(self::$active[$key])) {
            return;
        }
        self::$active[$key]->cancel();
        unset(self::$active[$key]);
    }

    private static function renderBorders(Player $player): void
    {
        $world = $player->getWorld();
        $pos = $player->getPosition();
        $minX = ((int) floor($pos->x) >> 4) << 4;
        $minZ = ((int) floor($pos->z) >> 4) << 4;
        $maxX = $minX + 16;
        $maxZ = $minZ + 16;
        $y = max(1, (int) floor($pos->y));

        $particle = new BlockBreakParticle(VanillaBlocks::REDSTONE());
        for ($i = 0; $i <= 16; $i++) {
            $world->addParticle(new Vector3($minX + $i + 0.5, $y, $minZ + 0.5), $particle, [$player]);
            $world->addParticle(new Vector3($minX + $i + 0.5, $y, $maxZ + 0.5), $particle, [$player]);
            $world->addParticle(new Vector3($minX + 0.5, $y, $minZ + $i + 0.5), $particle, [$player]);
            $world->addParticle(new Vector3($maxX + 0.5, $y, $minZ + $i + 0.5), $particle, [$player]);
        }
    }
}

