<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FlyCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $targetArg = isset($args["player"]) ? trim((string) $args["player"]) : "";
        $target = $targetArg !== "" ? PlayerUtils::getPlayerByPrefix($targetArg) : ($sender instanceof Player ? $sender : null);
        if ($target === null) {
            $sender->sendMessage(C::colorize("&r&cUsage: /fly [player]"));
            return;
        }

        if ($targetArg !== "" && (!$sender instanceof Player || $sender !== $target) && !$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cOnly admins can toggle fly for other players."));
            return;
        }

        GlaciaUtils::toggleFlight($target);
        if ($sender instanceof Player && $sender !== $target) {
            $sender->sendMessage(C::colorize("&r&aToggled flight for &f" . $target->getName() . "&a."));
        }
    }

    public function getPermission(): string {
        return "glacia.fly";
    }
}