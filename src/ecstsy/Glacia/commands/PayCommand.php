<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PayCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new PlayerArgument("player", false));
        $this->registerArgument(1, new RawStringArgument("amount", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $target = $args["player"] ?? null;
        $amount = isset($args["amount"]) ? (int) GeneralUtils::parseRandomNumber((string) $args["amount"]) : null;

        if (!$target instanceof Player || $amount === null || $amount <= 0) {
            $sender->sendMessage(C::colorize("&r&cUsage: /pay <player> <amount>"));
            return;
        }

        $senderSession = Loader::getPlayerManager()->getSession($sender);
        $targetSession = Loader::getPlayerManager()->getSession($target);

        if ($senderSession === null || $targetSession === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => $target->getName()], "error"));
            return;
        }

        if ($senderSession->getBalance() < $amount) {
            $sender->sendMessage(C::colorize("&r&cYou don't have enough balance to pay &f$" . number_format($amount) . "&c."));
            return;
        }

        $senderSession->removeBalance($amount);
        $targetSession->addBalance($amount);

        $senderName = str_replace("\n", " ", $sender->getNameTag());
        $targetName = str_replace("\n", " ", $target->getNameTag());

        $sender->sendMessage(C::colorize("&r&a✔ &r&7You sent &f$" . number_format($amount) . " &7to &b{$targetName}&7."));
        $target->sendMessage(C::colorize("&r&a✔ &r&b{$senderName} &7sent you &f$" . number_format($amount) . "&7."));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
