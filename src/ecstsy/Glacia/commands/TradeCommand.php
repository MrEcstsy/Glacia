<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\trade\TradeManager;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TradeCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new PlayerArgument("target", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        /** @var Player|null $target */
        $target = $args["target"] ?? null;
        if (!$target instanceof Player || !$target->isOnline()) {
            $sender->sendMessage(C::colorize("&r&cThat player is not online."));
            return;
        }

        if ($target->getUniqueId()->toString() === $sender->getUniqueId()->toString()) {
            $sender->sendMessage(C::colorize("&r&cYou cannot trade with yourself."));
            return;
        }

        TradeManager::handleTradeCommand($sender, $target);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}

