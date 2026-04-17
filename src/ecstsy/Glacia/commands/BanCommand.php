<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class BanCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
        $this->registerArgument(1, new TextArgument("reason", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $targetName = trim((string) $args["player"]);
        $reason = trim((string) ($args["reason"] ?? ""));

        if (strcasecmp($sender->getName(), $targetName) === 0) {
            $sender->sendMessage(C::colorize("&r&cYou cannot ban yourself."));
            return;
        }

        $banList = Server::getInstance()->getNameBans();
        if ($banList->isBanned($targetName)) {
            $sender->sendMessage(C::colorize("&r&e{$targetName} is already banned."));
            return;
        }

        $banList->addBan($targetName, $reason !== "" ? $reason : null, null, $sender->getName());

        $onlineTarget = PlayerUtils::getPlayerByPrefix($targetName);
        if ($onlineTarget !== null && strcasecmp($onlineTarget->getName(), $targetName) === 0) {
            $onlineTarget->kick($reason !== "" ? $reason : "Banned by an admin.");
            $targetName = $onlineTarget->getName();
        }

        $sender->sendMessage(C::colorize("&r&aBanned {$targetName}&7." . ($reason !== "" ? " &fReason: &7{$reason}" : "")));

        GlaciaUtils::logAdminAction("Ban Player", [
            "admin" => $sender->getName(),
            "target" => $targetName,
            "reason" => $reason !== "" ? $reason : "No reason provided"
        ]);
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}
