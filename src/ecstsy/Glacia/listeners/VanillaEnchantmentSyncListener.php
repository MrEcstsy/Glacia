<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\enchantments\vanilla\ExtendedVanillaEnchantmentRegistry;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\player\Player;

final class VanillaEnchantmentSyncListener implements Listener
{
    public function onJoin(PlayerJoinEvent $event): void
    {
        ExtendedVanillaEnchantmentRegistry::syncPlayer($event->getPlayer());
    }

    public function onMove(PlayerMoveEvent $event): void
    {
        ExtendedVanillaEnchantmentRegistry::syncPlayer($event->getPlayer());
    }

    public function onArmorTransaction(InventoryTransactionEvent $event): void
    {
        $player = $event->getTransaction()->getSource();
        if (!$player instanceof Player) {
            return;
        }

        foreach ($event->getTransaction()->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $inventory = $action->getInventory();
            if ($inventory instanceof ArmorInventory && $inventory->getHolder() === $player) {
                ExtendedVanillaEnchantmentRegistry::syncPlayer($player);
                return;
            }
        }
    }
}
