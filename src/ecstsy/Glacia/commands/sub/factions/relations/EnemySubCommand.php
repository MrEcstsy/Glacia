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

final class EnemySubCommand extends BaseSubCommand {

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

        $role = $session->getFactionRole();

        if (!in_array($role, ["leader", "officer"], true)) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.permission_denied"));
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
            $sender->sendMessage(GlaciaUtils::format("faction.error.cant_enemy_self"));
            return;
        }

        RelationManager::getInstance()->setRelation($ownId, $targetId, "enemy");
        RelationManager::getInstance()->setRelation($targetId, $ownId, "enemy");

        $ownFaction = $session->getFaction();
        $ownName = $ownFaction->getName();
        $targetName = $targetFaction->getName();

        $messageOwn = C::colorize("&c&lEnemy! &r&7Your faction is now &cenemied&r&7 towards &4{$targetName}&7.");
        $messageOther = C::colorize("&c&lEnemy! &r&7Your faction is now &cenemied&r&7 towards &4{$ownName}&7.");

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
