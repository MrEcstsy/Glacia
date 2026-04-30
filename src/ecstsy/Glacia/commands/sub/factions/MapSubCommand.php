<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\regions\RegionManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

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

        $worldFolder = $sender->getWorld()->getFolderName();
        $spawnRegion = RegionManager::getInstance()->getRegionByName("Spawn");

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

        $legend = "§a█ Your §2█ Safe §4█ War §c█ Enemy §d█ Ally §7█ Wild §f█ Neutral\n";

        for ($dz = -$radius; $dz <= $radius; $dz++) {
            for ($dx = -$radius; $dx <= $radius; $dx++) {
                $chunkX = $centerX + $dx;
                $chunkZ = $centerZ + $dz;

                $chunkCenter = new Vector3(($chunkX << 4) + 8, 64, ($chunkZ << 4) + 8);

                $claim = ClaimManager::getInstance()->getClaim($worldFolder, $chunkX, $chunkZ);
                $region = RegionManager::getInstance()->getRegionAtChunk($chunkX, $chunkZ, $worldFolder);
                // Overlapping Spawn + Warzone: show Spawn (safe) for map visuals.
                if ($spawnRegion !== null && $spawnRegion->getWorld() === $worldFolder && $spawnRegion->isWithinBounds($chunkCenter)) {
                    $region = $spawnRegion;
                }

                if ($region !== null) {
                    $color = match (strtolower($region->getName())) {
                        "spawn" => "§2",
                        "warzone", "war" => "§4",
                        default => "§4",
                    };
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

                // Slightly wider separation so Bedrock chat map is easier to scan.
                $block = $color . "\u{2588}" . "§r  ";
                if ($dx === 0 && $dz === 0) {
                    $map .= "§l§6★§r  ";
                } else {
                    $map .= $block;
                }
            }
            $map .= "\n";
        }

        $sender->sendMessage(TextFormat::colorize($map . $legend));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}