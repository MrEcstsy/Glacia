<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\economy\EconomyManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\screens\shop\ShopRepository;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class PotsCommand extends BaseCommand
{
    private const SHOP_ITEM_ID = "strong_healing_splash_potion";

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_session"));
            return;
        }

        $itemData = ShopRepository::findItem(self::SHOP_ITEM_ID);
        $item = ShopRepository::createItem(self::SHOP_ITEM_ID);
        if ($itemData === null || $item === null) {
            $sender->sendMessage(GlaciaUtils::format("pots.error.not_configured"));
            return;
        }

        $maxFit = self::getMaxFit($sender, $item);
        if ($maxFit <= 0) {
            $sender->sendMessage(GlaciaUtils::format("pots.error.no_space"));
            return;
        }

        $pricePerPot = max(0.01, round((float) ($itemData["buy"] ?? 0.0), 2));
        $affordable = (int) floor($session->getBalance() / $pricePerPot);
        $amount = min($maxFit, $affordable);

        if ($amount <= 0) {
            $sender->sendMessage(GlaciaUtils::format("pots.error.not_enough_money", [
                "price" => number_format($pricePerPot, 2),
            ]));
            return;
        }

        $totalCost = round($amount * $pricePerPot, 2);
        if (!EconomyManager::take($sender, $totalCost)) {
            $sender->sendMessage(GlaciaUtils::format("pots.error.not_enough_money", [
                "price" => number_format($pricePerPot, 2),
            ]));
            return;
        }

        $item->setCount($amount);
        $leftovers = $sender->getInventory()->addItem($item);
        if ($leftovers !== []) {
            $refunded = 0;
            foreach ($leftovers as $leftover) {
                $refunded += $leftover->getCount();
            }

            if ($refunded > 0) {
                $session->addBalance(round($refunded * $pricePerPot, 2));
                $amount -= $refunded;
                $totalCost = round($amount * $pricePerPot, 2);
            }
        }

        if ($amount <= 0) {
            $sender->sendMessage(GlaciaUtils::format("pots.error.no_space"));
            return;
        }

        $sender->sendMessage(GlaciaUtils::format("pots.success.filled", [
            "count" => number_format($amount),
            "cost" => number_format($totalCost, 2),
        ]));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }

    private static function getMaxFit(Player $player, Item $item): int
    {
        $inventory = $player->getInventory();
        $count = 0;

        for ($slot = 0; $slot < $inventory->getSize(); $slot++) {
            $slotItem = $inventory->getItem($slot);
            if ($slotItem->isNull()) {
                $count += $item->getMaxStackSize();
                continue;
            }

            if ($slotItem->canStackWith($item)) {
                $count += max(0, $item->getMaxStackSize() - $slotItem->getCount());
            }
        }

        return $count;
    }
}
