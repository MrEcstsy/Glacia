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

final class TakeSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("name", false));
        $this->registerArgument(1, new RawStringArgument("amount", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $player = isset($args["name"]) ? PlayerUtils::getPlayerByPrefix($args["name"]) : null;
        $amount = isset($args["amount"]) ? (float) GeneralUtils::parseRandomNumber((string) $args["amount"]) : null;

        if ($player === null) {
            $sender->sendMessage(C::colorize("&r&cError: &4Player not found."));
            return;
        }

        if ($amount === null || $amount <= 0) {
            $sender->sendMessage(C::colorize("&r&cError: &4Amount not specified."));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($player);

        $session->removeBalance($amount);
        $sender->sendMessage(C::colorize("&r&a$" . number_format($amount, 2) . " has been removed from " . $player->getNameTag() . "'s balance."));
        $player->sendMessage(C::colorize("&r&a$" . number_format($amount, 2) . " has been removed from your balance."));
    }

    public function getPermission(): string
    {
        return "glacia.eco.take";
    }
}
