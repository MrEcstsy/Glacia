<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\shop;

use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use ecstsy\Glacia\server\economy\EconomyManager;
use ecstsy\Glacia\utils\screens\shop\ShopRepository;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ShopBuySubCommand extends BaseSubCommand {

    protected function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("item"));
        $this->registerArgument(1, new IntegerArgument("amount", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            return;
        }

        $query = trim((string) ($args["item"] ?? ""));
        $amount = max(1, (int) ($args["amount"] ?? 1));

        if ($query === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /shop buy <item> [amount]"));
            return;
        }

        $itemData = ShopRepository::findItem($query);
        if ($itemData === null) {
            $sender->sendMessage(C::colorize("&r&cThat shop item could not be found."));
            return;
        }

        $item = ShopRepository::createItem((string) $itemData["id"]);
        if ($item === null) {
            $sender->sendMessage(C::colorize("&r&cThat shop item could not be created."));
            return;
        }

        $maxFit = self::getMaxFit($sender, $item);
        if ($maxFit <= 0) {
            $sender->sendMessage(C::colorize("&r&cYou do not have enough inventory space."));
            return;
        }

        $amount = min($amount, $maxFit);
        $buy = (float) $itemData["buy"];
        $totalCost = round($buy * $amount, 2);

        if (!EconomyManager::has($sender, $totalCost)) {
            $sender->sendMessage(C::colorize("&r&cNot enough money or your balance could not be loaded."));
            return;
        }

        if (!EconomyManager::take($sender, $totalCost)) {
            $sender->sendMessage(C::colorize("&r&cCould not process your purchase."));
            return;
        }

        $item->setCount($amount);
        $sender->getInventory()->addItem($item);

        $name = (string) ($itemData["name"] ?? $itemData["id"]);
        $sender->sendMessage(C::colorize("&r&aPurchased &f{$amount}x {$name} &r&afor &b$" . number_format($totalCost, 2) . "&a."));
    }

    private static function getMaxFit(Player $player, \pocketmine\item\Item $item): int {
        $inv = $player->getInventory();
        $count = 0;

        for ($slot = 0; $slot < $inv->getSize(); $slot++) {
            $slotItem = $inv->getItem($slot);

            if ($slotItem->isNull()) {
                $count += $item->getMaxStackSize();
            } elseif ($slotItem->canStackWith($item)) {
                $count += ($item->getMaxStackSize() - $slotItem->getCount());
            }
        }

        return $count;
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
