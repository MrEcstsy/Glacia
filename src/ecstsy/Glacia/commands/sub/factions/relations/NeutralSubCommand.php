<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\relations;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class NeutralSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("target", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) return;

        $session = Loader::getPlayerManager()->getSession($sender);
        $ownId = $session?->getFactionId();

        if ($ownId === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $input = $args["target"];
        $targetFaction = FactionManager::getInstance()->getFactionByName($input);
        
        if ($targetFaction === null) {
            $p = PlayerUtils::getPlayerByPrefix($input);
            $targetFaction = $p !== null ? Loader::getPlayerManager()->getSession($p)?->getFaction() : null;
        }

        if ($targetFaction === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => $input]));
            return;
        }

        $targetId = $targetFaction->getFactionId();

        if ($targetId === $ownId) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.cant_neutral_self"));
            return;
        }

        RelationManager::getInstance()->clearRelation($ownId,    $targetId);
        RelationManager::getInstance()->clearRelation($targetId, $ownId);

        $ownFaction = $session->getFaction(); 
        $ownName = $ownFaction->getName();
        $targetName = $targetFaction->getName();
        $messageOwn = C::colorize("&r&l&fNeutral &r&8| &7You are now neutral with &f{$targetName}&7.");
        $messageOther = C::colorize("&r&l&fNeutral &r&8| &7You are now neutral with &f{$ownName}&7.");

        foreach ($ownFaction->getOnlineMembers() as $member) {
            $member->sendMessage($messageOwn);
        }

        foreach ($targetFaction->getOnlineMembers() as $member) {
            $member->sendMessage($messageOther);
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
