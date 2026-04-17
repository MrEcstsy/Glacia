<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\shop;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\CustomForm;

final class ShopConfirmScreen extends BaseScreen {

    public CustomForm $form;

    public function __construct(array $itemData)
    {
        $name = (string) ($itemData["name"] ?? $itemData["id"]);
        $buy = (float) $itemData["buy"];

        $this->form = new CustomForm(function(Player $player, $data) use ($itemData, $buy): void {
            if ($data === null) {
                return;
            }

            $amount = (int) $data[1];
            $buyMax = (bool) $data[2];
            $customAmount = trim((string) $data[3]);
            $session = PlayerManager::getInstance()->getSession($player);

            if ($session === null) {
                $player->sendMessage(C::RED . "Could not load your balance.");
                return;
            }

            $item = StringToItemParser::getInstance()->parse($itemData["id"]);
            if ($item === null) {
                return;
            }

            if ($buyMax) {
                $amount = self::getMaxFit($player, $item);
            } elseif ($customAmount !== "" && is_numeric($customAmount)) {
                $amount = (int) $customAmount;
            }

            if ($amount <= 0) {
                return;
            }

            $maxFit = self::getMaxFit($player, $item);
            if ($maxFit <= 0) {
                $player->sendMessage(C::RED . "You do not have enough inventory space.");
                return;
            }

            $amount = min($amount, $maxFit);
            $totalCost = round($buy * $amount, 2);

            if ($session->getBalance() < $totalCost) {
                $player->sendMessage(C::RED . "Not enough money.");
                return;
            }

            $session->removeBalance($totalCost);

            $item->setCount($amount);
            $player->getInventory()->addItem($item);

            $player->sendMessage(C::GREEN . "Purchased x$amount for $" . number_format($totalCost, 2));
        });

        $this->form->setTitle(C::colorize("&r&8Confirm Purchase"));
        $this->form->addLabel(C::colorize(
            "&r&f" . $name . "\n" .
            "&7Price: &a$" . number_format($buy, 2) . " each"
        ));
        $this->form->addSlider("Amount", 1, 64, 1, 1);
        $this->form->addToggle("Buy Max (Fill Inventory)", false);
        $this->form->addInput("Or enter exact amount", "Leave blank to use slider");
    }

    public static function displayForm(Player $player, array $itemData): void {
        $player->sendForm((new self($itemData))->getForm());
    }

    public function getForm(): CustomForm {
        return $this->form;
    }

    private static function getMaxFit(Player $player, Item $item): int {
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
}
