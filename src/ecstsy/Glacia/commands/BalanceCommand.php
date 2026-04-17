<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class BalanceCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument('name', true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }
        $name = isset($args['name']) ? $args['name'] : null;
        if ($name === null) {
            $session = Loader::getPlayerManager()->getSession($sender);
            $sender->sendMessage(GlaciaUtils::format("balance.info", ["balance" => number_format($session->getBalance(), 2)]));
            return;
        }

        $player = PlayerUtils::getPlayerByPrefix($name);

        if ($player === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => $name], "error"));
            return;
        }

        $targetSession = Loader::getPlayerManager()->getSession($player);
        $senderSession = Loader::getPlayerManager()->getSession($sender);
        $targetBalance = $targetSession->getBalance();
        $formatted = fmod($targetBalance, 1) == 0 ? number_format($targetBalance, 0) : number_format($targetBalance, 2);        

        $senderBalance = $senderSession->getBalance();

        $sender->sendMessage(GlaciaUtils::format("balance.info.other", ["name" => $player->getName(), "balance" => $formatted]));

        if ($senderBalance > $targetBalance) {
            $sender->sendMessage(GlaciaUtils::format("balance.richer-than"));
        } elseif ($senderBalance < $targetBalance) {
            $sender->sendMessage(GlaciaUtils::format("balance.poorer-than"));
        } else {
            $sender->sendMessage(GlaciaUtils::format("balance.equal"));
        }
    }


    public function getPermission(): string {
        return 'glacia.default';
    }
}