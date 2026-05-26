<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\claim;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\claims\FactionClaim;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class UnClaimSubCommand extends BaseSubCommand
{
    public function prepare(): void
    {
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
            $sender->sendMessage(C::colorize("&r&l&c(X) &r&cYou do not have permission to unclaim land."));
            return;
        }

        $pos = $sender->getPosition();
        $claimManager = ClaimManager::getInstance();
        $claim = $claimManager->getClaimAtPosition($pos);

        if ($claim === null) {
            $sender->sendMessage(C::colorize("&r&l&c(X) &r&cThis chunk is not claimed by your faction."));
            return;
        }

        if ($claim->getFactionId() !== $factionId) {
            $sender->sendMessage(C::colorize("&r&l&c(X) &r&cThis chunk does not belong to your faction."));
            return;
        }

        $faction = $session->getFaction();
        if ($claim->isCore() && $faction !== null && $faction->getClaimCount() > 1) {
            $sender->sendMessage(C::colorize("&r&l&c(X) &r&cYou cannot unclaim your Core Chunk while your faction still owns other claims."));
            return;
        }
        if ($claim->isRaid() && $claimManager->areRaidClaimsPermanent()) {
            $sender->sendMessage(C::colorize("&r&l&c(X) &r&cRaid claims are permanent on this realm."));
            return;
        }

        $cx = $claim->getChunkX();
        $cz = $claim->getChunkZ();
        $type = $claim->getClaimType();
        $claimManager->deleteClaim($claim);

        GlaciaUtils::logFactionAudit("Unclaim", [
            "player" => $sender->getName(),
            "faction" => $faction !== null ? $faction->getName() : "",
            "faction_id" => $factionId,
            "world" => $sender->getWorld()->getFolderName(),
            "chunk_x" => $cx,
            "chunk_z" => $cz,
            "type" => $type,
        ]);

        $label = match ($type) {
            FactionClaim::TYPE_CORE => "Core Chunk",
            FactionClaim::TYPE_RAID => "Raid Claim",
            FactionClaim::TYPE_PREMIUM => "Premium Claim",
            default => "chunk",
        };
        $sender->sendMessage(C::colorize("&r&l&a(OK) &r&2You have unclaimed the {$label} &a{$cx}, {$cz}"));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
