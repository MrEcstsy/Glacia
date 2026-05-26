<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\claim;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\economy\EconomyManager;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\claims\FactionClaim;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ClaimSubCommand extends BaseSubCommand
{
    /** @var array<string, array{faction_id:string, world:string, chunk_x:int, chunk_z:int, expires_at:int}> */
    private static array $coreClaimConfirmations = [];

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("type", true));
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
            $sender->sendMessage(C::colorize("&r&l&c(X) &r&cYou do not have permission to claim land."));
            return;
        }

        $pos = $sender->getPosition();
        $chunkX = $pos->getFloorX() >> 4;
        $chunkZ = $pos->getFloorZ() >> 4;

        $claimManager = ClaimManager::getInstance();
        $existing = $claimManager->getClaimAtPosition($pos);

        if ($existing !== null && $existing->getFaction() === null) {
            $claimManager->deleteClaim($existing);
            $existing = null;
        }

        if ($existing !== null) {
            $owner = $existing->getFaction();
            if ($owner !== null && $owner->getFactionId() === $factionId) {
                $sender->sendMessage(C::colorize("&r&l&c(X) &r&cThis chunk is already claimed by your faction."));
            } else {
                $sender->sendMessage(C::colorize("&r&l&c(X) &r&cThis chunk is already claimed by &b" . ($owner?->getName() ?? "Unknown")));
            }
            return;
        }

        $faction = $session->getFaction();
        if ($faction === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $claimCount = $faction->getClaimCount();
        $requestedType = FactionClaim::normalizeType((string) ($args["type"] ?? FactionClaim::TYPE_NORMAL));
        $claimType = $claimCount <= 0 ? FactionClaim::TYPE_CORE : $requestedType;

        if ($claimType === FactionClaim::TYPE_CORE && $role !== "leader") {
            $sender->sendMessage(C::colorize("&r&l&c(X) &r&cOnly the faction leader can place the first Core Chunk."));
            return;
        }

        if ($claimType === FactionClaim::TYPE_CORE && $claimCount <= 0) {
            $confirmKey = $sender->getUniqueId()->toString();
            $pending = self::$coreClaimConfirmations[$confirmKey] ?? null;
            $worldName = $sender->getWorld()->getFolderName();
            $now = time();
            if (
                $pending === null ||
                $pending["expires_at"] < $now ||
                $pending["faction_id"] !== $factionId ||
                $pending["world"] !== $worldName ||
                $pending["chunk_x"] !== $chunkX ||
                $pending["chunk_z"] !== $chunkZ
            ) {
                self::$coreClaimConfirmations[$confirmKey] = [
                    "faction_id" => $factionId,
                    "world" => $worldName,
                    "chunk_x" => $chunkX,
                    "chunk_z" => $chunkZ,
                    "expires_at" => $now + 20,
                ];
                $sender->sendMessage(C::colorize("&r&eCore Chunk tip: &7this will become your faction's main core claim. Protect it well. Type &f/f claim &7again here to confirm."));
                return;
            }

            unset(self::$coreClaimConfirmations[$confirmKey]);
        }

        if ($claimCount <= 0 && $requestedType !== FactionClaim::TYPE_NORMAL) {
            $sender->sendMessage(C::colorize("&r&l&c(X) &r&cYour first faction claim must be your Core Chunk. Use &f/f claim&c."));
            return;
        }

        if ($claimCount >= intdiv($faction->getFactionPower(), 25)) {
            $sender->sendMessage(C::colorize("&r&l&c(X) &r&cYou have reached the maximum amount of claims for your faction."));
            return;
        }

        if ($claimType !== FactionClaim::TYPE_PREMIUM && $claimManager->isPremiumWildernessCandidate($sender->getWorld(), $chunkX, $chunkZ)) {
            $sender->sendMessage(C::colorize($claimCount <= 0
                ? "&r&l&c(X) &r&cYour Core Chunk cannot be placed in a premium Warzone-border chunk."
                : "&r&l&c(X) &r&cThis wilderness chunk borders Warzone and must be claimed with &f/f claim premium&c."
            ));
            return;
        }

        if ($claimType === FactionClaim::TYPE_NORMAL && $claimManager->mustNormalClaimBeAdjacent() && $claimCount > 0) {
            if (!$claimManager->hasAdjacentFactionClaim($factionId, $sender->getWorld(), $chunkX, $chunkZ)) {
                $sender->sendMessage(C::colorize("&r&l&c(X) &r&cNormal faction claims must touch one of your existing claims."));
                return;
            }
        }

        if ($claimType === FactionClaim::TYPE_PREMIUM) {
            $limit = $claimManager->getPremiumClaimLimit();
            if ($limit > 0 && $claimManager->getClaimCountForFactionByType($factionId, FactionClaim::TYPE_PREMIUM) >= $limit) {
                $sender->sendMessage(C::colorize("&r&l&c(X) &r&cYour faction has reached the premium claim limit."));
                return;
            }
            if (!$claimManager->isPremiumWildernessCandidate($sender->getWorld(), $chunkX, $chunkZ)) {
                $sender->sendMessage(C::colorize("&r&l&c(X) &r&cPremium claims must be wilderness chunks directly beside Warzone."));
                return;
            }

            $cost = $claimManager->getPremiumClaimCost($factionId);
            if (!EconomyManager::take($sender, $cost)) {
                $sender->sendMessage(C::colorize("&r&l&c(X) &r&cYou need &f$" . number_format($cost) . " &cto claim this premium chunk."));
                return;
            }
        }

        $claimManager->createClaim($factionId, $sender->getWorld(), $chunkX, $chunkZ, $claimType);
        GlaciaUtils::logFactionAudit("Claim", [
            "player" => $sender->getName(),
            "faction" => $faction->getName(),
            "faction_id" => $factionId,
            "world" => $sender->getWorld()->getFolderName(),
            "chunk_x" => $chunkX,
            "chunk_z" => $chunkZ,
            "type" => $claimType,
        ]);

        $label = match ($claimType) {
            FactionClaim::TYPE_CORE => "Core Chunk",
            FactionClaim::TYPE_RAID => "Raid Claim",
            FactionClaim::TYPE_PREMIUM => "Premium Claim",
            default => "chunk",
        };
        $sender->sendMessage(C::colorize("&r&l&a(OK) &r&2You have claimed the {$label} &a{$chunkX}, {$chunkZ}&2."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
