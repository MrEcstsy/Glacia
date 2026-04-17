<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class KickCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new PlayerArgument("player"));
        $this->registerArgument(1, new TextArgument("reason", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        /** @var Player $target */
        $target = $args["player"];
        $reason = trim((string) ($args["reason"] ?? ""));

        if ($sender->getName() === $target->getName()) {
            $sender->sendMessage(C::colorize("&r&cYou cannot kick yourself."));
            return;
        }

        $kickReason = $reason !== "" ? $reason : "Kicked by an admin.";
        $target->kick($kickReason);

        $sender->sendMessage(C::colorize("&r&aKicked {$target->getName()}&7." . ($reason !== "" ? " &fReason: &7{$reason}" : "")));

        GlaciaUtils::logAdminAction("Kick Player", [
            "admin" => $sender->getName(),
            "target" => $target->getName(),
            "reason" => $reason !== "" ? $reason : "No reason provided"
        ]);
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}
