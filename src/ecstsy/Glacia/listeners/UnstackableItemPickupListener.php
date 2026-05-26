<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\items\GlaciaItemStackPolicy;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\ItemMergeEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

/**
 * PocketMine may choose an inventory and fire pickup even when {@see BaseInventory::canAddItem}
 * disagrees with {@see BaseInventory::getAddableItemQuantity} for edge cases (unique NBT, timing).
 * For max-stack-1 items (lootboxes, tools, etc.), cancel pickup unless the full stack fits — avoids
 * pickup animation + entity despawn + immediate drop remainder on the ground.
 */
final class UnstackableItemPickupListener implements Listener {

    public function onEntityItemPickup(EntityItemPickupEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }

        $collector = $event->getEntity();
        if (!$collector instanceof Player || !$collector->hasFiniteResources()) {
            return;
        }

        $item = $event->getItem();
        if (!GlaciaItemStackPolicy::shouldUnstackInPlayerStorage($item) && $item->getMaxStackSize() !== 1) {
            return;
        }

        $inventory = $event->getInventory();
        if ($inventory === null) {
            return;
        }

        if (GlaciaItemStackPolicy::shouldUnstackInPlayerStorage($item)) {
            $origin = $event->getOrigin();
            if (!$origin instanceof ItemEntity) {
                $event->cancel();
                return;
            }

            $requiredSlots = max(1, $item->getCount());
            $emptySlots = 0;
            $size = $inventory->getSize();

            for ($slot = 0; $slot < $size; $slot++) {
                if ($inventory->getItem($slot)->isNull()) {
                    $emptySlots++;
                    if ($emptySlots >= $requiredSlots) {
                        return;
                    }
                }
            }

            if ($emptySlots > 0) {
                $event->cancel();
                $toCollect = min($requiredSlots, $emptySlots);
                $single = clone $item;
                $single->setCount(1);

                for ($i = 0; $i < $toCollect; $i++) {
                    $inventory->addItem(clone $single);
                }

                $remaining = $requiredSlots - $toCollect;
                if ($remaining <= 0) {
                    $origin->flagForDespawn();
                    return;
                }

                $origin->setStackSize($remaining);
                $origin->setPickupDelay(10);
                $this->sendSlotsNeededActionBar($collector, $remaining);
                return;
            }

            $event->cancel();
            $this->sendSlotsNeededActionBar($collector, $requiredSlots);
            return;
        }

        if (!$inventory->canAddItem($item)) {
            $event->cancel();
            $this->sendSlotsNeededActionBar($collector, 1);
        }
    }

    public function onItemMerge(ItemMergeEvent $event): void
    {
        $source = $event->getEntity()->getItem();
        $target = $event->getTarget()->getItem();

        if (
            GlaciaItemStackPolicy::shouldUnstackInPlayerStorage($source) ||
            GlaciaItemStackPolicy::shouldUnstackInPlayerStorage($target)
        ) {
            $event->cancel();
        }
    }

    private function sendSlotsNeededActionBar(Player $player, int $amountNeeded): void
    {
        $amountNeeded = max(1, $amountNeeded);
        $suffix = $amountNeeded === 1 ? "slot needed" : "slots needed";
        $player->sendActionBarMessage(C::colorize("&r&cYou need more slots &r&7({$amountNeeded} {$suffix})"));
    }
}
