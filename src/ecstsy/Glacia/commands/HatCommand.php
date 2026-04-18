<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use pocketmine\block\CarvedPumpkin;
use pocketmine\block\MobHead;
use pocketmine\command\CommandSender;
use pocketmine\item\Armor;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\ItemBlock;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class HatCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $inventory = $sender->getInventory();
        $armorInventory = $sender->getArmorInventory();
        $hand = $inventory->getItemInHand();

        if ($hand->isNull()) {
            $sender->sendMessage(C::colorize("&r&cYou must be holding an item to wear as a hat."));
            return;
        }

        if (!$this->isVisibleHatItem($hand)) {
            $sender->sendMessage(C::colorize("&r&cThat item will not render properly as a hat. Use a helmet, mob head, or carved pumpkin."));
            return;
        }

        $helmet = $armorInventory->getHelmet();
        $inventory->setItemInHand($helmet->isNull() ? VanillaItems::AIR() : $helmet);
        $armorInventory->setHelmet($hand);

        $sender->sendMessage(C::colorize("&r&aYour hat has been updated."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }

    private function isVisibleHatItem(\pocketmine\item\Item $item): bool
    {
        if ($item instanceof Armor) {
            return $item->getArmorSlot() === ArmorInventory::SLOT_HEAD;
        }

        if ($item instanceof ItemBlock) {
            $block = $item->getBlock();
            return $block instanceof MobHead || $block instanceof CarvedPumpkin;
        }

        return false;
    }
}
