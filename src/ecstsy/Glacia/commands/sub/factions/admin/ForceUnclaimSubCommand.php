<?php
declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\admin;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ForceUnclaimSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) return;

        $pos = $sender->getPosition();
        $claimManager = ClaimManager::getInstance();
        $claim = $claimManager->getClaimAtPosition($pos);

        if ($claim === null) {
            $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cThere is no claim on this chunk."));
            return;
        }

        $faction = $claim->getFaction(); 
        $chunkX = $claim->getChunkX();   
        $chunkZ = $claim->getChunkZ();

        $claimManager->deleteClaim($claim);
        $sender->sendMessage(C::colorize("&r&l&a(✓) &r&2You have force-unclaimed the chunk &a{$chunkX}, {$chunkZ} &2for the faction &a" . $faction->getName()));

        GlaciaUtils::logAdminAction("Force Unclaim", [
            "admin" => $sender->getName(),
            "faction" => $faction->getName(),
            "chunk" => "{$chunkX}, {$chunkZ}",
            "world" => $sender->getWorld()->getFolderName()
        ]);
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}
