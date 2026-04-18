<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\hats\HatManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\CarvedPumpkin;
use pocketmine\block\MobHead;
use pocketmine\command\CommandSender;
use pocketmine\item\Armor;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Item;
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
            $removedHat = HatManager::clearRenderedHat($sender);
            if ($removedHat !== null) {
                $this->returnItem($sender, $removedHat);
                $sender->sendMessage(C::colorize("&r&aYour hat has been cleared."));
                PlayerUtils::playSound($sender, "random.levelup");
                return;
            }

            $helmet = $armorInventory->getHelmet();
            if (!$helmet->isNull()) {
                $armorInventory->setHelmet(VanillaItems::AIR());
                $this->returnItem($sender, $helmet);
                $sender->sendMessage(C::colorize("&r&aYour hat has been cleared."));
                PlayerUtils::playSound($sender, "random.levelup");
                return;
            }

            $sender->sendMessage(C::colorize("&r&cYou must be holding an item to wear as a hat."));
            PlayerUtils::playSound($sender, "note.bass");
            return;
        }

        if (HatManager::isRenderableBlockHat($hand)) {
            $helmet = $armorInventory->getHelmet();
            $oldRenderedHat = $sender instanceof \ecstsy\Glacia\server\hats\HatPlayer ? $sender->getRenderedHat()?->getSourceItem() : null;
            if (
                (!$helmet->isNull() && !$inventory->canAddItem($helmet)) ||
                ($oldRenderedHat !== null && !$inventory->canAddItem($oldRenderedHat))
            ) {
                $sender->sendMessage(C::colorize("&r&cYou need at least one free inventory slot to swap hats."));
                PlayerUtils::playSound($sender, "note.bass");
                return;
            }

            if ($hand->getCount() > 1) {
                $remaining = clone $hand;
                $remaining->setCount($remaining->getCount() - 1);
                $inventory->setItemInHand($remaining);
            } else {
                $inventory->setItemInHand(VanillaItems::AIR());
            }

            if (!$helmet->isNull()) {
                $inventory->addItem($helmet);
                $armorInventory->setHelmet(VanillaItems::AIR());
            }

            $returnedHat = HatManager::clearRenderedHat($sender);
            if ($returnedHat !== null) {
                $this->returnItem($sender, $returnedHat);
            }

            HatManager::applyRenderedHat($sender, clone $hand);
            $sender->sendMessage(C::colorize("&r&aYour hat has been updated."));
            PlayerUtils::playSound($sender, "random.levelup");
            return;
        }

        if (!$this->isVisibleHatItem($hand)) {
            $sender->sendMessage(C::colorize("&r&cThat item will not render properly as a hat. Use a solid block, helmet, mob head, or carved pumpkin."));
            PlayerUtils::playSound($sender, "note.bass");
            return;
        }

        $hat = clone $hand;
        $hat->setCount(1);
        $helmet = $armorInventory->getHelmet();

        if ($hand->getCount() > 1) {
            if (!$helmet->isNull() && !$inventory->canAddItem($helmet)) {
                $sender->sendMessage(C::colorize("&r&cYou need at least one free inventory slot to swap hats."));
                PlayerUtils::playSound($sender, "note.bass");
                return;
            }

            $remaining = clone $hand;
            $remaining->setCount($remaining->getCount() - 1);
            $inventory->setItemInHand($remaining);

            if (!$helmet->isNull()) {
                $inventory->addItem($helmet);
            }
        } else {
            $inventory->setItemInHand($helmet->isNull() ? VanillaItems::AIR() : $helmet);
        }

        $returnedHat = HatManager::clearRenderedHat($sender);
        if ($returnedHat !== null) {
            $this->returnItem($sender, $returnedHat);
        }
        $armorInventory->setHelmet($hat);

        $sender->sendMessage(C::colorize("&r&aYour hat has been updated."));
        PlayerUtils::playSound($sender, "random.levelup");
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }

    private function isVisibleHatItem(Item $item): bool
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

    private function returnItem(Player $player, Item $item): void
    {
        foreach ($player->getInventory()->addItem($item) as $overflow) {
            $player->getWorld()->dropItem($player->getPosition(), $overflow);
        }
    }
}
