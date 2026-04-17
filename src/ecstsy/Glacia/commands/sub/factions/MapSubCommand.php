<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\regions\RegionManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class MapSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $radius = 5;
        $centerX = $sender->getPosition()->getFloorX() >> 4;
        $centerZ = $sender->getPosition()->getFloorZ() >> 4;

        $yaw = $sender->getLocation()->getYaw();
        $directions = [
            "N" => ($yaw >= 315 || $yaw < 45),
            "E" => ($yaw >= 45 && $yaw < 135),
            "S" => ($yaw >= 135 && $yaw < 225),
            "W" => ($yaw >= 225 && $yaw < 315),
        ];
        $dirColors = [
            "N" => "§b", // Cyan
            "E" => "§e", // Yellow
            "S" => "§c", // Red
            "W" => "§a", // Green
        ];
        $dirString = "";
        foreach ($directions as $dir => $active) {
            $dirString .= ($active ? $dirColors[$dir] : "§7") . $dir . " ";
        }

        $map = "§l§8[ " . trim($dirString) . "]§r\n";

        $legend = "§a■ Your Claim §2■ Safezone §4■ Warzone §c■ Enemy §d■ Ally §7■ Wilderness §f■ Neutral\n";

        for ($dz = -$radius; $dz <= $radius; $dz++) {
            for ($dx = -$radius; $dx <= $radius; $dx++) {
                $chunkX = $centerX + $dx;
                $chunkZ = $centerZ + $dz;

                $claim = ClaimManager::getInstance()->getClaim($sender->getWorld()->getFolderName(), $chunkX, $chunkZ);
                $region = RegionManager::getInstance()->getRegionAtChunk($chunkX, $chunkZ, $sender->getWorld()->getFolderName());

                if ($region !== null) {
                    $color = "§4"; 
                } elseif ($claim !== null) {
                    $claimFacId = $claim->getFactionId();
                    $playerSession = \ecstsy\Glacia\player\PlayerManager::getInstance()->getSession($sender);
                    $playerFaction = $playerSession?->getFaction();
                    $playerFacId = $playerFaction?->getFactionId();

                    if ($playerFacId !== null) {
                        if ($claimFacId === $playerFacId) {
                            $relation = "own";
                        } else {
                            $relation = RelationManager::getInstance()->getRelation($playerFacId, $claimFacId);
                        }
                    } else {
                        $relation = "neutral";
                    }

                    $color = GlaciaUtils::getRelationColor($relation);
                } else {
                    $color = "§7"; 
                }

                if ($dx === 0 && $dz === 0) {
                    $map .= "§l§6■§r"; 
                } else {
                    $map .= $color . "■";
                }
            }
            $map .= "\n";
        }

        $sender->sendMessage($map . $legend);
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}