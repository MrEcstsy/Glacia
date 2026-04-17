<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\claim;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class UnClaimSubCommand extends BaseSubCommand
{
    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);

        $factionId = $session->getFactionId();
        if ($factionId === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $role = $session->getFactionRole();
        if ($role !== "leader" && $role !== "officer") {
            $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cYou do not have permission to unclaim land."));
            return;
        }

        $pos    = $sender->getPosition();
        $claimManager = ClaimManager::getInstance();
        $claim        = $claimManager->getClaimAtPosition($pos);

        if ($claim === null) {
            $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cThis chunk is not claimed by your faction."));
            return;
        }

        if ($claim->getFactionId() !== $factionId) {
            $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cThis chunk does not belong to your faction."));
            return;
        }

        $claimManager->deleteClaim($claim);
        $sender->sendMessage(C::colorize("&r&l&a(✓) &r&2You have unclaimed the chunk &a" . $claim->getChunkX() . ", " . $claim->getChunkZ()));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
