<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\enchantments\manager\SoulGemSessionManager;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class RemoveConduitCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        $hand = $sender->getInventory()->getItemInHand();
        $removed = SoulGemSessionManager::removeConduitFromItem($hand);
        if ($removed === null) {
            $sender->sendMessage(C::colorize("&r&cYour held item does not have a merged conduit."));
            return;
        }

        $sender->getInventory()->setItemInHand($removed["item"]);
        foreach ([$removed["conduit"], $removed["gem"]] as $reward) {
            if ($reward === null) {
                continue;
            }
            foreach ($sender->getInventory()->addItem($reward) as $leftover) {
                $sender->getWorld()->dropItem($sender->getPosition(), $leftover);
            }
        }

        $sender->sendMessage(C::colorize("&r&aRemoved the conduit from your held item."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
