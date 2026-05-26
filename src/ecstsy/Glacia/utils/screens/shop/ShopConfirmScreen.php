<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\shop;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\economy\EconomyManager;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\CustomForm;

final class ShopConfirmScreen extends BaseScreen {

    public CustomForm $form;

    public function __construct(array $itemData, ?string $category = null, ?int $page = null)
    {
        $name = (string) ($itemData["name"] ?? $itemData["id"]);
        $buy = (float) $itemData["buy"];

        $returnToCategory = static function(Player $player) use ($category, $page): void {
            self::reopenShop($player, $category, $page);
        };

        $this->form = new CustomForm(function(Player $player, $data) use ($itemData, $buy, $returnToCategory): void {
            if ($data === null) {
                $returnToCategory($player);
                return;
            }

            $amount = (int) $data[1];
            $buyMax = (bool) $data[2];
            $customAmount = trim((string) $data[3]);

            $item = ShopRepository::createItem($itemData["id"]);
            if ($item === null) {
                $returnToCategory($player);
                return;
            }

            if ($buyMax) {
                $amount = self::getMaxFit($player, $item);
            } elseif ($customAmount !== "" && is_numeric($customAmount)) {
                $amount = (int) $customAmount;
            }

            if ($amount <= 0) {
                $returnToCategory($player);
                return;
            }

            $maxFit = self::getMaxFit($player, $item);
            if ($maxFit <= 0) {
                $player->sendMessage(C::colorize("&cYou do not have enough inventory space."));
                $returnToCategory($player);
                return;
            }

            $amount = min($amount, $maxFit);
            $totalCost = round($buy * $amount, 2);

            if (!EconomyManager::has($player, $totalCost)) {
                $player->sendMessage(C::colorize("&cNot enough money or your balance could not be loaded."));
                $returnToCategory($player);
                return;
            }

            if (!EconomyManager::take($player, $totalCost)) {
                $player->sendMessage(C::colorize("&cCould not process your purchase."));
                $returnToCategory($player);
                return;
            }

            $item->setCount($amount);
            $player->getInventory()->addItem($item);

            $player->sendMessage(C::colorize("&aPurchased x$amount for $" . number_format($totalCost, 2)));
            $returnToCategory($player);
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

    public static function displayForm(Player $player, array $itemData, ?string $category = null, ?int $page = null): void {
        $player->sendForm((new self($itemData, $category, $page))->getForm());
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

    private static function reopenShop(Player $player, ?string $category = null, ?int $page = null): void
    {
        $session = Loader::getPlayerManager()->getSession($player);
        $useChestInventories = $session === null || $session->getSetting("chest_inventories") !== false;

        if ($category === null) {
            if ($useChestInventories) {
                ShopScreen::display($player);
            } else {
                ShopFormScreen::display($player);
            }
            return;
        }

        if ($useChestInventories) {
            (new ShopCategoryScreen($category, max(1, $page ?? 1)))->display($player);
            return;
        }

        ShopFormScreen::displayCategory($player, $category, max(0, ($page ?? 1) - 1));
    }
}
