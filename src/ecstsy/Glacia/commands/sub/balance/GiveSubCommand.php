<?php

namespace ecstsy\Glacia\commands\sub\balance;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class GiveSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("name", false));
        $this->registerArgument(1, new RawStringArgument("amount", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $lookup = (string)($args["name"] ?? "");
        $amount = isset($args["amount"]) ? (float) GeneralUtils::parseRandomNumber((string) $args["amount"]) : null;

        $online = $lookup !== "" ? PlayerUtils::getPlayerByPrefix($lookup) : null;
        $session = $online !== null ? Loader::getPlayerManager()->getSession($online) : Loader::getPlayerManager()->getSessionByPrefix($lookup);

        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cError: &4Player not found."));
            return;
        }

        if ($amount === null || $amount <= 0) {
            $sender->sendMessage(C::colorize("&r&cError: &4Amount not specified."));
            return;
        }

        if ($session->getBalance() + $amount > 9000000000) {
            $sender->sendMessage(C::colorize("&r&cError: &4You cannot give more money than the server allows."));
            return;
        }

        $session->addBalance($amount);
        $sender->sendMessage(C::colorize("&r&aAdded &f$" . number_format($amount, 2) . "&a to &f" . $session->getUsername() . "&a."));

        $player = $session->getPlayer();
        if ($player instanceof Player && $player !== $sender) {
            $player->sendMessage(C::colorize("&r&aYou received &f$" . number_format($amount, 2) . "&a."));
        }
    }

    public function getPermission(): ?string
    {
        return "glacia.eco.give";
    }
}
