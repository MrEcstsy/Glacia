<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\enchants\all\ReforgedEnchant;
use ecstsy\Glacia\server\enchantments\enchants\all\ReforgedLogic;
use ecstsy\Glacia\server\enchantments\enchants\sword\RageEnchant;
use ecstsy\Glacia\server\enchantments\manager\EnchantEffectManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantEventRegistry;
use ecstsy\Glacia\server\enchantments\manager\EnchantmentEventRegistry;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class EnchantmentListener implements Listener {

    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();

        $player->getArmorInventory()->getListeners()->add(new CallbackInventoryListener(
            function (Inventory $inventory, int $slot, Item $oldItem): void {
                EnchantEffectManager::onArmorSlotChange($inventory, $slot, $oldItem);
            },
            function (Inventory $inventory, array $oldContents): void {
            }
        ));

        $player->getInventory()->getListeners()->add(new CallbackInventoryListener(
            function (Inventory $inventory, int $slot, Item $oldItem): void {
                EnchantEffectManager::onInventorySlotChange($inventory, $slot, $oldItem);
            },
            function (Inventory $inventory, array $oldContents): void {
            }
        ));
    }

    /**
     * @priority HIGHEST
     */
    public function onPlayerItemHeld(PlayerItemHeldEvent $event): void
    {
        $player = $event->getPlayer();
        $inventory = $player->getInventory();

        $oldItem = $player->getInventory()->getItemInHand();
        $newItem = $inventory->getItem($event->getSlot());

        EnchantEffectManager::updateHeldItemEffects($player, $oldItem, $newItem);
    }

    /**
     * @priority HIGHEST
     */
    public function onInventoryTransaction(InventoryTransactionEvent $event): void
    {
        foreach ($event->getTransaction()->getActions() as $action) {
            $inv = $event->getTransaction()->getSource()->getInventory();
            $player = $inv instanceof PlayerInventory ? $inv->getHolder() : ($inv instanceof ArmorInventory ? $inv->getHolder() : null);

            if ($player instanceof Player) {
                if ($inv instanceof ArmorInventory && $action instanceof SlotChangeAction) {
                    $slot = $action->getSlot();
                    $oldItem = $action->getSourceItem();
                    EnchantEffectManager::onArmorSlotChange($inv, $slot, $oldItem);
                }
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) return;

        $handlers = EnchantEventRegistry::getHandlers('entity_damage');

        foreach ($entity->getArmorInventory()->getContents() as $armor) {
            $enchants = CustomEnchantmentManager::getEnchantments($armor);

            foreach ($enchants as $enchantName => $level) {
                if (isset($handlers[$enchantName])) {
                    $enchObj = CustomEnchantmentManager::getEnchantment($enchantName);
                    if ($enchObj !== null) {
                        $handlers[$enchantName]($event, $enchObj, $level);
                    }
                }
            }
        }

        $item = $entity->getInventory()->getItemInHand();
        $enchants = CustomEnchantmentManager::getEnchantments($item);

        foreach ($enchants as $enchantName => $level) {
            if (isset($handlers[$enchantName])) {
                $enchObj = CustomEnchantmentManager::getEnchantment($enchantName);
                if ($enchObj !== null) {
                    $handlers[$enchantName]($event, $enchObj, $level);
                }
            }
        }
    }

    /**
     * @priority HIGHEST 
     */ 
    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $damager = $event->getDamager();

        if ($damager instanceof Player) {
            $weapon = $damager->getInventory()->getItemInHand();
            $enchants = CustomEnchantmentManager::getEnchantments($weapon);

            foreach ($enchants as $enchantName => $level) {
                $handlers = EnchantEventRegistry::getHandlers('entity_damage_by_entity');
                if (isset($handlers[$enchantName])) {
                    $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                    if ($enchantObj !== null) {
                        $handlers[$enchantName]($event, $enchantObj, $level);
                    }
                }
            }
        }

        if ($victim instanceof Player) {
            $armorContents = $victim->getArmorInventory()->getContents();

            foreach ($armorContents as $armorPiece) {
                $enchants = CustomEnchantmentManager::getEnchantments($armorPiece);

                foreach ($enchants as $enchantName => $level) {
                    $handlers = EnchantEventRegistry::getHandlers('entity_damage_by_entity');
                    if (isset($handlers[$enchantName])) {
                        $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                        if ($enchantObj !== null) {
                            $handlers[$enchantName]($event, $enchantObj, $level);
                        }
                    }
                }
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        $enchantments = CustomEnchantmentManager::getEnchantments($item);

        foreach ($enchantments as $enchantName => $level) {
            $handlers = EnchantEventRegistry::getHandlers('block_break');
            if (isset($handlers[$enchantName])) {
                $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                if ($enchantObj !== null) {
                    $handlers[$enchantName]($event, $enchantObj, $level);
                }
            }
        }
    }
    
    /**
     * @priority HIGHEST
     */
    public function onEffectAdd(EntityEffectAddEvent $event): void {
        if($event->isCancelled()) return;

        $entity = $event->getEntity();
        if (!$entity instanceof Player) return;

        $armorInv = $entity->getArmorInventory();
        $armorContents = $armorInv->getContents();

        foreach ($armorContents as $armorItem) {
            $enchantments = CustomEnchantmentManager::getEnchantments($armorItem);

            foreach ($enchantments as $enchantName => $level) {
                $handlers = EnchantEventRegistry::getHandlers('effect_add');
                if (isset($handlers[$enchantName])) {
                    $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);

                    if ($enchantObj !== null) {
                        $handlers[$enchantName]($event, $enchantObj, $level);
                    }
                }
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onInteract(PlayerInteractEvent $event): void {
        if($event->isCancelled()) return;

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        $enchantments = CustomEnchantmentManager::getEnchantments($item);

        foreach ($enchantments as $enchantName => $level) {
            $handlers = EnchantEventRegistry::getHandlers('interact');
            if (isset($handlers[$enchantName])) {
                $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);

                if ($enchantObj !== null) {
                    $handlers[$enchantName]($event, $enchantObj, $level);
                }
            }
        }
    }
    
    /**
     * @priority HIGHEST
     */
    public function onPlayerExhaust(PlayerExhaustEvent $event): void {
        if($event->isCancelled()) return;

        $player = $event->getPlayer();
        if (!$player instanceof Player) return;

        $armorInv = $player->getArmorInventory();
        $armorContents = $armorInv->getContents();

        foreach ($armorContents as $armorItem) {
            $enchantments = CustomEnchantmentManager::getEnchantments($armorItem);

            foreach ($enchantments as $enchantName => $level) {
                $handlers = EnchantEventRegistry::getHandlers('player_exhaust');
                if (isset($handlers[$enchantName])) {
                    $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);

                    if ($enchantObj !== null) {
                        $handlers[$enchantName]($event, $enchantObj, $level);
                    }
                }
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();

        // Handle victim armor enchants
        if ($entity instanceof Player) {
            $armorInv = $entity->getArmorInventory();
            $armorContents = $armorInv->getContents();

            foreach ($armorContents as $armorItem) {
                $enchantments = CustomEnchantmentManager::getEnchantments($armorItem);

                foreach ($enchantments as $enchantName => $level) {
                    $handlers = EnchantEventRegistry::getHandlers('entity_death');
                    if (isset($handlers[$enchantName])) {
                        $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                        if ($enchantObj !== null) {
                            $handlers[$enchantName]($event, $enchantObj, $level);
                        }
                    }
                }
            }
        }

        // Handle killer weapon enchants
        $cause = $entity->getLastDamageCause();
        if ($cause instanceof EntityDamageByEntityEvent) {
            $killer = $cause->getDamager();

            if ($killer instanceof Player) {
                $item = $killer->getInventory()->getItemInHand();
                $enchantments = CustomEnchantmentManager::getEnchantments($item);

                foreach ($enchantments as $enchantName => $level) {
                    $handlers = EnchantEventRegistry::getHandlers('entity_death');
                    if (isset($handlers[$enchantName])) {
                        $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                        if ($enchantObj !== null) {
                            $handlers[$enchantName]($event, $enchantObj, $level);
                        }
                    }
                }
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onBreakReforged(BlockBreakEvent $event): void {
        if($event->isCancelled()) return;

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        $new = ReforgedEnchant::tryReforged($player, $item);
        $player->getInventory()->setItemInHand($new);
    }

    /**
     * @priority HIGHEST
     */
    public function onHit(EntityDamageByEntityEvent $event) : void {
        if($event->isCancelled()) return;

        $damager = $event->getDamager();
        if(!$damager instanceof Player) return;

        $item = $damager->getInventory()->getItemInHand();

        $new = ReforgedEnchant::tryReforged($damager, $item);
        $damager->getInventory()->setItemInHand($new);
    }

    /**
     * @priority HIGHEST
     */
    public function onArmorDamage(EntityDamageEvent $event) : void {
        if($event->isCancelled()) return;

        $player = $event->getEntity();
        if(!$player instanceof Player) return;

        $inv = $player->getArmorInventory();
        foreach($inv->getContents() as $slot => $armor){
            $new = ReforgedEnchant::tryReforged($player, $armor);
            $inv->setItem($slot, $new);
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDamageReset(EntityDamageByEntityEvent $event): void {
        $victim = $event->getEntity();
        if($victim instanceof Player) {
            RageEnchant::reset($victim->getName());
        }
    }
}