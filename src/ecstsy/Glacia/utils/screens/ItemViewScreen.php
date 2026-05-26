<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens;

use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\InventoryUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\lang\Translatable;
use pocketmine\utils\TextFormat as C;
use pocketmine\player\Player;
use Vecnavium\FormsUI\SimpleForm;

final class ItemViewScreen extends BaseScreen {

    private InvMenu $menu;
    private SimpleForm $form;

    public function __construct(Player $player, Item $item)
    {
        $this->menu = InvMenu::create(GlaciaUtils::TYPE_DISPENSER);
        $this->menu->setName(C::colorize("&r&8" . $player->getName() . "'s Item"));

        $inventory = $this->menu->getInventory();
        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem();
        InventoryUtils::fillInventory($inventory, $pane, [4]);

        $inventory->setItem(4, $item);

        $this->menu->setListener(InvMenu::readonly());

        $this->form = new SimpleForm(function(Player $player, $data): void {
            
        });

        $this->form->setTitle("§r§8" . $player->getName() . "'s Item");

        $lines = [];

        $name = $this->resolveName($player, $item->getName());
        $count = $item->getCount() > 1 ? " x{$item->getCount()}" : "";
        $lines[] = C::colorize("&l&b" . $name . "&r" . $count);

        foreach ($item->getEnchantments() as $enchant) {
            $enchName = $this->resolveName($player, $enchant->getType()->getName());
            $lines[] = C::colorize("&7- " . $enchName . " " . $enchant->getLevel());
            }

        $lore = $item->getLore();
        if (!empty($lore)) {
            $lines[] = C::colorize("&8Lore:");
            foreach ($lore as $l) {
                $lines[] = C::colorize("&f" . $l);
            }
        }

        $this->form->setContent(C::colorize(implode("\n", $lines)));
        $this->form->addButton(C::colorize("&r&l&0Close"));
    }

    private function resolveName(Player $player, string|Translatable $value): string {
        return $value instanceof Translatable
            ? $player->getLanguage()->translate($value)
            : $value;
    }

    public static function display(Player $player, Item $item): void {
        $screen = new self($player, $item);
        $screen->getMenu()->send($player);
    }

    public static function displayForm(Player $player, Item $item): void {
        $screen = new self($player, $item);
        $player->sendForm($screen->getForm());
    }

    public function getMenu(): InvMenu {
        return $this->menu;
    }

    public function getForm(): SimpleForm {
        return $this->form;
    }
}
