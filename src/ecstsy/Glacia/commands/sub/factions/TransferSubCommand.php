<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class TransferSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", false));
    }
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $sess = Loader::getPlayerManager()->getSession($sender);
        $fac  = $sess->getFaction();
        if (!$fac || $sess->getFactionRole() !== "leader") {
            $sender->sendMessage(GlaciaUtils::format("faction.error.permission_denied"));
            return;
        }
        $newL = Loader::getPlayerManager()->getSessionByName($args["player"]);
        if (!$newL || $newL->getFactionId() !== $fac->getFactionId()) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_in_your_faction", ["player" => $args["player"]]));
            return;
        }
        $fac->transferLeadership($fac->getFactionId(), $newL->getUuid());
        $sender->sendMessage(C::colorize("&r&l&a(✓) &r&aYou transferred leadership to &2" . $args["player"] . "&a."));
        $newL->getPlayer()?->sendMessage(C::colorize("&r&l&a(✓) &r&aYou are now the leader of &2" . $fac->getName() . "&a."));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
