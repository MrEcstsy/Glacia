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
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class ItemViewScreen extends BaseScreen {

    private InvMenu $menu;
    private SimpleForm $form;

    public function __construct(Player $player, Item $item) {
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

        $name = $this->resolveText($player, $item->getName());
        $count = $item->getCount() > 1 ? " x{$item->getCount()}" : "";
        $lines[] = C::colorize("&l&b" . $name . "&r" . $count);

        $lore = $item->getLore();

        while (isset($lore[0]) && trim(C::clean($lore[0])) === '') {
            array_shift($lore);
        }

        foreach ($lore as $line) {
            $lines[] = $line;
        }

        $vanillaEnchantLines = [];
        foreach ($item->getEnchantments() as $enchantmentInstance) {
            $enchName = $this->resolveText($player, $enchantmentInstance->getType()->getName());
            $vanillaEnchantLines[] = C::colorize("&7" . $enchName . ' ' . $this->toRoman($enchantmentInstance->getLevel()));
        }

        if (!empty($vanillaEnchantLines)) {
            if (!empty($lore)) {
                $lines[] = '';
            }

            foreach ($vanillaEnchantLines as $line) {
                $lines[] = $line;
            }
        }

        $this->form->setContent(implode("\n", $lines));
        $this->form->addButton(C::colorize("&r&l&0Close"));
    }

    private function resolveText(Player $player, string|Translatable $value): string {
        return $value instanceof Translatable
            ? $player->getLanguage()->translate($value)
            : $value;
    }

    private function toRoman(int $number): string {
        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $result = '';
        foreach ($map as $value => $roman) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }

        return $result;
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
