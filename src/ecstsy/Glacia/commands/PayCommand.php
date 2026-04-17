<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PayCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new PlayerArgument("player", false));
        $this->registerArgument(1, new IntegerArgument("amount", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $target = $args["player"] ?? null;
        $amount = $args["amount"] ?? null;

        if (!$target instanceof Player || $amount === null || $amount <= 0) {
            $sender->sendMessage(C::colorize("&r&b&l◆ &fUsage: /pay <player> <amount> &b&l◆"));
            return;
        }

        $senderSession = Loader::getPlayerManager()->getSession($sender);
        $targetSession = Loader::getPlayerManager()->getSession($target);

        if ($senderSession === null || $targetSession === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid"));
            return;
        }

        if ($senderSession->getBalance() < $amount) {
            $sender->sendMessage(C::colorize("&r&b&l◆ &cYou don't have enough balance to pay &b$" . number_format($amount) . " &b&l◆"));
            return;
        }

        $senderSession->removeBalance($amount);
        $targetSession->addBalance($amount);

        $ice = "&b&l❄";
        $senderName = str_replace("\n", " ", $sender->getNameTag());
        $targetName = str_replace("\n", " ", $target->getNameTag());

        $sender->sendMessage(C::colorize("{$ice} &fYou sent &b$" . number_format($amount) . " &fto &b{$targetName} {$ice}"));
        $target->sendMessage(C::colorize("{$ice} &b{$senderName} &fsent you &b$" . number_format($amount) . " {$ice}"));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}