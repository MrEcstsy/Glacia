<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class DemoteSubCommand extends BaseSubCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $sess = Loader::getPlayerManager()->getSession($sender);
        $fac = $sess->getFaction();
        if ($fac === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $roleOrder = ["recruit","member","officer","leader"];
        $yourRole = $sess->getFactionRole();
        $tName = $args["player"];
        $tSess = Loader::getPlayerManager()->getSessionByName($tName);
        if (!$tSess || $tSess->getFactionId() !== $fac->getFactionId()) {
            $sender->sendMessage(C::colorize(GlaciaUtils::format("player.error.not_in_your_faction", ["name" => $tName])));
            return;
        }
        $theirRole = $tSess->getFactionRole();

        $yourIdx = array_search($yourRole, $roleOrder, true);
        $tIdx    = array_search($theirRole, $roleOrder, true);
        if ($yourIdx === false || $yourIdx <= $tIdx || $yourIdx < 2) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.cant_demote"));
            return;
        }

        $newRole = $roleOrder[max($tIdx-1,0)];
        $tSess->setFactionRole($newRole);
        $sender->sendMessage(GlaciaUtils::format("faction.status.demote.sender", ["player" => $tSess->getUsername(), "role" => $newRole]));
        $tSess->getPlayer()?->sendMessage(GlaciaUtils::format("faction.status.demote.target", ["role" => $newRole], "none"));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
