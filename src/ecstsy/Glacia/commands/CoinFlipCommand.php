<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\CoinFlipInstance;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class CoinFlipCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument('arg1', true));
        $this->registerArgument(1, new RawStringArgument('arg2', true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $arg1 = trim((string) ($args['arg1'] ?? ""));
        $arg2 = trim((string) ($args['arg2'] ?? ""));

        if ($arg1 === "") {
            $sender->sendForm(CoinFlipInstance::getCoinFlipList($sender));
            return;
        }

        if (in_array(strtolower($arg1), ["stats", "stat", "record", "records"], true)) {
            if ($arg2 === "") {
                CoinFlipInstance::showStats($sender);
                return;
            }

            $target = PlayerUtils::getPlayerByPrefix($arg2);
            if (!$target instanceof Player) {
                $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => $arg2]));
                return;
            }

            CoinFlipInstance::showStats($sender, $target);
            return;
        }

        $amount = GlaciaUtils::parseShorthandNumber($arg1);
        if ($amount < 1000) {
            $sender->sendMessage(GlaciaUtils::format("coinflip.error.invalid_amount", [], "error"));
            return;
        }

        if (CoinFlipInstance::hasSubmittedCoinFlip($sender)) {
            $sender->sendMessage(GlaciaUtils::format("coinflip.error.already_submitted", [], "error"));
            return;
        }

        $sender->sendForm(CoinFlipInstance::getCoinFlipColorOption($sender, $amount));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
