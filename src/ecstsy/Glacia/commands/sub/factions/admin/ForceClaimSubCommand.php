<?php
declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\admin;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\utils\AdminLogger;
use ecstsy\Glacia\utils\DiscordLogger;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ForceClaimSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("faction", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) return;

        $factionName = $args["faction"] ?? "";
        $fm = FactionManager::getInstance();
        $faction = $fm->getFactionByName($factionName) ?? $fm->getFaction($factionName);

        if ($faction === null) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_exists", ["name" => $factionName]));
            return;
        }

        $pos = $sender->getPosition();
        $chunkX = $pos->getFloorX() >> 4;
        $chunkZ = $pos->getFloorZ() >> 4;

        $claimManager = ClaimManager::getInstance();
        $existing = $claimManager->getClaimAtPosition($pos);
        if ($existing !== null) {
            $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cThis chunk is already claimed by &b" . $existing->getFaction()->getName()));
            return;
        }

        $claimManager->createClaim($faction->getFactionId(), $sender->getWorld(), $chunkX, $chunkZ);
        $sender->sendMessage(C::colorize("&r&l&a(✓) &r&2You have force-claimed the chunk &a{$chunkX}, {$chunkZ} &2for the faction &a" . $faction->getName()));

        $logMsg = GlaciaUtils::formatAdminLog("Force Claim", [
            "admin" => $sender->getName(),
            "faction" => $faction->getName(),
            "chunk" => "{$chunkX}, {$chunkZ}",
            "world" => $sender->getWorld()->getFolderName()
        ]);

        DiscordLogger::send($logMsg);
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}
