<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\CoinFlipInstance;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class CoinFlipCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new IntegerArgument('amount', true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        $amount = isset($args['amount']) ? $args['amount'] : null;

        if ($amount === null) {
            $sender->sendForm(CoinFlipInstance::getCoinFlipList($sender));
            return;
        }

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