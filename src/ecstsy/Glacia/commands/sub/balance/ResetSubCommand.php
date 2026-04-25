<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\balance;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ResetSubCommand extends BaseSubCommand {

    private const STARTING_BALANCE = 950.0;

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("name", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $lookup = (string)($args["name"] ?? "");
        if ($lookup === "") {
            $sender->sendMessage(C::colorize("&r&cError: &4Player not specified."));
            return;
        }

        $online = PlayerUtils::getPlayerByPrefix($lookup);
        $session = $online !== null ? Loader::getPlayerManager()->getSession($online) : Loader::getPlayerManager()->getSessionByPrefix($lookup);

        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cError: &4Player not found."));
            return;
        }

        $session->setBalance(self::STARTING_BALANCE);
        $sender->sendMessage(C::colorize("&r&aReset &f" . $session->getUsername() . "&a's balance to &f$" . number_format(self::STARTING_BALANCE, 2) . "&a."));

        $player = $session->getPlayer();
        if ($player instanceof Player && $player !== $sender) {
            $player->sendMessage(C::colorize("&r&eYour balance was reset to &f$" . number_format(self::STARTING_BALANCE, 2) . "&e."));
        }
    }

    public function getPermission(): string
    {
        return "glacia.eco.reset";
    }
}
