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

final class ClaimSubCommand extends BaseSubCommand
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
            $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cYou do not have permission to claim land."));
            return;
        }

        $pos = $sender->getPosition();
        $chunkX = $pos->getFloorX() >> 4;
        $chunkZ = $pos->getFloorZ() >> 4;

        $claimManager = ClaimManager::getInstance();
        $existing = $claimManager->getClaimAtPosition($pos);

        if ($existing !== null) {
            $owner = $existing->getFaction();
            if ($owner !== null && $owner->getFactionId() === $factionId) {
                $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cThis chunk is already claimed by your faction."));
            } else {
                $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cThis chunk is already claimed by &b" . $existing->getFaction()->getName()));
            }
            return;
        }

        $faction = $session->getFaction();
        $currentPower = $faction->getFactionPower();

        if ($faction->getClaimCount() >= intdiv($currentPower, 25)) {
            $sender->sendMessage(c::colorize("&r&l&c(✘) &r&cYou have reached the maximum amount of claims for your faction."));
            return;
        }

        $claimManager->createClaim($factionId, $sender->getWorld(), $chunkX, $chunkZ);
        $sender->sendMessage(C::colorize("&r&l&a(✓) &r&2You have claimed the chunk &a{$chunkX}, {$chunkZ}&2."));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}