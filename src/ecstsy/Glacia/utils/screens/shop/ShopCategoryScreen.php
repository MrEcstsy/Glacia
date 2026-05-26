<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\shop;

use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ShopCategoryScreen extends BaseScreen {

    public InvMenu $invMenu;
    private const ITEMS_PER_PAGE = 45;
    private int $page = 1;
    private array $items = [];
    private string $category;

    public function __construct(string $category, int $page = 1)
    {
        $this->category = $category;
        $this->items = ShopRepository::getItemsForCategory($category);
        $maxPage = max(1, (int) ceil(max(1, count($this->items)) / self::ITEMS_PER_PAGE));
        $this->page = max(1, min($maxPage, $page));
        $this->invMenu = CustomSizedInvMenu::create(54);
        $this->invMenu->setName(C::colorize("&r&8Shop Items (&b$category&r&8)"));

        $this->invMenu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            if ($slot === 53) {
                if (($this->page * self::ITEMS_PER_PAGE) < count($this->items)) {
                    $this->page++;
                    $this->build();
                }
                return;
            }

            if ($slot === 45) {
                if ($this->page > 1) {
                    $this->page--;
                    $this->build();
                    return;
                }

                ShopScreen::display($player);
                return;
            }

            if ($slot < self::ITEMS_PER_PAGE) {
                $index = ($this->page - 1) * self::ITEMS_PER_PAGE + $slot;
                $itemData = $this->items[$index] ?? null;
                if ($itemData === null) {
                    return;
                }

                $player->removeCurrentWindow();
                $transaction->then(function() use($player, $itemData) {
                    ShopConfirmScreen::displayForm($player, $itemData, $this->category, $this->page);
                });
            }
        }));

        $this->build();
    }

    private function build(): void {
        $inv = $this->invMenu->getInventory();
        $inv->clearAll();

        $offset = ($this->page - 1) * self::ITEMS_PER_PAGE;
        $pageItems = array_slice($this->items, $offset, self::ITEMS_PER_PAGE);

        foreach ($pageItems as $index => $data) {
            $item = ShopRepository::createItem($data["id"]);
            if ($item === null) {
                continue;
            }

            $buy = (float) $data["buy"];
            $sell = $data["sell"] ?? null;
            $sellText = $sell !== null ? "&c$" . number_format((float) $sell, 2) : "&cUnavailable";

            $item->setCustomName(C::colorize("&r&f" . $data["name"]));
            $item->setLore([
                C::colorize("&r&7Buy: &a$" . number_format($buy, 2)),
                C::colorize("&r&7Sell: " . $sellText),
                "",
                C::colorize("&r&eClick to purchase")
            ]);

            $inv->setItem($index, $item);
        }

        for ($i = 45; $i <= 53; $i++) {
            $inv->setItem($i, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" "));
        }

        if ($this->page > 1) {
            $inv->setItem(45, VanillaItems::ARROW()->setCustomName(C::colorize("&r&c<- Back")));
        } else {
            $inv->setItem(45, VanillaItems::ARROW()->setCustomName(C::colorize("&r&c<- Categories")));
        }

        if (($offset + self::ITEMS_PER_PAGE) < count($this->items)) {
            $inv->setItem(53, VanillaItems::ARROW()->setCustomName(C::colorize("&r&aNext ->")));
        }
    }

    public function getMenu(): ?InvMenu
    {
        return $this->invMenu;
    }

    public function display(Player $player): void
    {
        $this->getMenu()->send($player);
    }
}
