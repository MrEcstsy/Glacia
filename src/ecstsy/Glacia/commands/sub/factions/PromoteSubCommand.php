<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class PromoteSubCommand extends BaseSubCommand {
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
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_in_your_faction", ["player" => $tName]));
            return;
        }
        $theirRole = $tSess->getFactionRole();

        $yourIdx = array_search($yourRole, $roleOrder, true);
        $tIdx    = array_search($theirRole, $roleOrder, true);
        if ($yourIdx === false || $yourIdx <= $tIdx || $yourIdx < 2) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.permission_denied"));
            return;
        }

        $newRole = $roleOrder[min($tIdx + 1, $yourIdx)];
        $tSess->setFactionRole($newRole);
        $sender->sendMessage(C::colorize('&r&l&a(✓) &r&aYou promoted &2' . $tName . ' &r&ato &2' . $newRole . '&a.'));
        $tSess->getPlayer()?->sendMessage(C::colorize('&r&l&a(✓) &r&aYou have been promoted to &2' . $newRole . '&a.'));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
