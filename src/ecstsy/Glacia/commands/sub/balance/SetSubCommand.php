<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\balance;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SetSubCommand extends BaseSubCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("name", false));
        $this->registerArgument(1, new RawStringArgument("amount", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $lookup = (string)($args["name"] ?? "");
        $amount = isset($args["amount"]) ? (float) GeneralUtils::parseRandomNumber((string) $args["amount"]) : -1;

        if ($lookup === "") {
            $sender->sendMessage(C::colorize("&r&cError: &4Player not specified."));
            return;
        }

        if ($amount < 0) {
            $sender->sendMessage(C::colorize("&r&cError: &4Amount must be zero or higher."));
            return;
        }

        $online = PlayerUtils::getPlayerByPrefix($lookup);
        $session = $online !== null ? Loader::getPlayerManager()->getSession($online) : Loader::getPlayerManager()->getSessionByPrefix($lookup);

        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cError: &4Player not found."));
            return;
        }

        $session->setBalance(min(9000000000, $amount));
        $sender->sendMessage(C::colorize("&r&aSet &f" . $session->getUsername() . "&a's balance to &f$" . number_format($session->getBalance(), 2) . "&a."));

        $player = $session->getPlayer();
        if ($player instanceof Player && $player !== $sender) {
            $player->sendMessage(C::colorize("&r&eYour balance was set to &f$" . number_format($session->getBalance(), 2) . "&e."));
        }
    }

    public function getPermission(): string
    {
        return "glacia.eco.set";
    }
}
