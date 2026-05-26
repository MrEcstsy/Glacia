<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\block\VanillaBlocks;

final class ZoneBarrierCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("radius", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $action = strtolower((string) ($args["action"] ?? "help"));
        if ($action !== "clear") {
            $sender->sendMessage(C::colorize("&r&cUsage: /zonebarrier clear <radius>"));
            $sender->sendMessage(C::colorize("&r&7Removes barrier blocks around you. Use this to clean up accidental zone bucket walls."));
            return;
        }

        $radius = max(1, min(96, (int) ($args["radius"] ?? 32)));
        $removed = $this->clearNearbyBarriers($sender, $radius);
        $sender->sendMessage(C::colorize("&r&aRemoved &f" . number_format($removed) . " &abarrier blocks within &f{$radius} &ablocks."));
    }

    private function clearNearbyBarriers(Player $player, int $radius): int
    {
        $world = $player->getWorld();
        $center = $player->getPosition();
        $centerX = $center->getFloorX();
        $centerZ = $center->getFloorZ();
        $radiusSq = $radius * $radius;
        $barrier = VanillaBlocks::BARRIER();
        $removed = 0;

        for ($x = $centerX - $radius; $x <= $centerX + $radius; $x++) {
            $dx = $x - $centerX;
            for ($z = $centerZ - $radius; $z <= $centerZ + $radius; $z++) {
                $dz = $z - $centerZ;
                if (($dx * $dx) + ($dz * $dz) > $radiusSq) {
                    continue;
                }

                for ($y = $world->getMinY(); $y < $world->getMaxY(); $y++) {
                    if (!$world->getBlockAt($x, $y, $z)->isSameState($barrier)) {
                        continue;
                    }

                    $world->setBlockAt($x, $y, $z, VanillaBlocks::AIR(), true);
                    $removed++;
                }
            }
        }

        return $removed;
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
