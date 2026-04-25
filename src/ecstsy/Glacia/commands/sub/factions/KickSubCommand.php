<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class KickSubCommand extends BaseSubCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!($sender instanceof Player)) return;
        $sess = Loader::getPlayerManager()->getSession($sender);
        $fac = $sess->getFaction();
        if ($fac === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $targetName = $args["player"];
        if (strtolower($targetName) === strtolower($sender->getName())) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.cant_kick_self"));
            return;
        }        
        
        $tSess = Loader::getPlayerManager()->getSessionByPrefix($targetName);
        if ($tSess === null || $tSess->getFactionId() !== $fac->getFactionId()) {
            $sender->sendMessage(GlaciaUtils::format("player.error.not_in_your_faction", ["name" => $targetName]));
            return;
        }

        if ($tSess->getFactionRole() === "leader") {
            $sender->sendMessage(GlaciaUtils::format("faction.error.cant_kick_leader"));
            return;
        }

        $yourRole = $sess->getFactionRole();       
        $theirRole = $tSess->getFactionRole();
        $allowed = match($yourRole) {
            "leader"   => true,
            "officer"  => !in_array($theirRole, ["leader","officer"], true),
            default    => false,
        };
        if (!$allowed) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.permission_denied"));
            return;
        }

        $fac->kickMember($fac->getFactionId(), $tSess->getUuid());

        $sender->sendMessage("&r&l&a(✓) &r&aKicked &2{$targetName} &afrom your faction.");
        $tSess->getPlayer()?->sendMessage(C::colorize("&r&l&c(✘) &r&cYou were kicked from &4{$fac->getName()}&c."));

        foreach ($fac->getOnlineMembers() as $member) {
            if ($member->getName() !== $targetName) {
                $member->sendMessage(C::colorize("&r&l&c(✘) &r&4{$targetName} &cwas kicked from your faction."));
            }
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
