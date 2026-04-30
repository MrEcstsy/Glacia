<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\enchants\all\ReforgedEnchant;
use ecstsy\Glacia\server\enchantments\enchants\all\ReforgedLogic;
use ecstsy\Glacia\server\enchantments\enchants\armor\TricksterEnchant;
use ecstsy\Glacia\server\enchantments\enchants\helmet\ImplantsEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\RageEnchant;
use ecstsy\Glacia\server\enchantments\manager\AngelicHealingManager;
use ecstsy\Glacia\server\enchantments\manager\BleedManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantEffectManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantEventRegistry;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantmentEventRegistry;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use ecstsy\Glacia\server\enchantments\manager\WeaponProcGate;
use ecstsy\Glacia\server\enchantments\manager\SilenceManager;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\MaliceWeaponRegistry;
use ecstsy\Glacia\server\enchantments\manager\VirusManager;
use ecstsy\Glacia\server\enchantments\advanced\AdvancedSetEnchants;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\items\pets\PetManager;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\event\entity\EntityEffectRemoveEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\scheduler\ClosureTask;

final class EnchantmentListener implements Listener {

    /** @var array<int, Item> */
    private array $projectileWeapons = [];

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

        EnchantEffectManager::scheduleArmorRefresh($player, 1);
    }

    public function onPlayerMove(PlayerMoveEvent $event): void
    {
        $from = $event->getFrom();
        $to = $event->getTo();

        if (
            $from->getFloorX() === $to->getFloorX() &&
            $from->getFloorY() === $to->getFloorY() &&
            $from->getFloorZ() === $to->getFloorZ()
        ) {
            return;
        }

        EnchantEffectManager::refreshArmorEffects($event->getPlayer());
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        ProcLuckManager::clearPlayer($player);
        RageEnchant::clearPlayer($player);
        EnchantEffectManager::clearPlayerState($player);
        AngelicHealingManager::clearPlayer($player);
        BleedManager::clearPlayer($player);
        SilenceManager::clearPlayer($player);
        TricksterEnchant::clearPlayer($player);
        ImplantsEnchant::clearPlayer($player);
        VirusManager::clearPlayer($player);
        WeaponProcGate::clearAttacker($player);
        AdvancedEnchantState::clearPlayer($player);
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
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $inventory = $action->getInventory();
            if (!$inventory instanceof ArmorInventory) {
                continue;
            }

            $player = $inventory->getHolder();
            if (!$player instanceof Player) {
                continue;
            }

            EnchantEffectManager::onArmorSlotChange($inventory, $action->getSlot(), $action->getSourceItem());
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Player && AdvancedEnchantState::isCoupInvuln($entity)) {
            $event->cancel();
            return;
        }
        if ($event instanceof EntityDamageByEntityEvent) {
            AdvancedSetEnchants::onPreProcessDamage($event);
        }
        if ($event->isCancelled()) {
            return;
        }

        VirusManager::amplifyEffectDamage($event);
        if (!$entity instanceof Player && !$entity instanceof ForsakenChampion) return;

        $handlers = EnchantEventRegistry::getHandlers('entity_damage');

        $highestLevels = [];
        foreach ($this->getArmorContents($entity) as $armor) {
            $enchants = CustomEnchantmentManager::getEnchantments($armor);
            foreach ($enchants as $enchantName => $level) {
                if (!isset($handlers[$enchantName])) {
                    continue;
                }
                if (!isset($highestLevels[$enchantName]) || $level > $highestLevels[$enchantName]) {
                    $highestLevels[$enchantName] = $level;
                }
            }
        }

        foreach ($highestLevels as $enchantName => $level) {
            $enchObj = CustomEnchantmentManager::getEnchantment($enchantName);
            if ($enchObj === null) {
                continue;
            }
            $handlers[$enchantName]($event, $enchObj, $level);
        }

        $item = $this->getCombatItem($entity);
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

        if ($damager instanceof Player && EnchantCombatStateManager::isPlagueWeakened($damager)) {
            $event->setBaseDamage($event->getBaseDamage() * 0.5);
        }

        $weaponHolder = null;
        $weapon = null;
        if ($damager instanceof Player || $damager instanceof ForsakenChampion) {
            $weaponHolder = $damager;
        } elseif ($damager instanceof Projectile) {
            $weapon = $this->projectileWeapons[spl_object_id($damager)] ?? null;
            $owner = $damager->getOwningEntity();
            if ($owner instanceof Player || $owner instanceof ForsakenChampion) {
                $weaponHolder = $owner;
            }
        }

        if ($weaponHolder instanceof Player || $weaponHolder instanceof ForsakenChampion) {
            $weapon ??= $this->getCombatItem($weaponHolder);
            $enchants = CustomEnchantmentManager::getEnchantments($weapon);
            $entityDamageHandlers = EnchantEventRegistry::getHandlers('entity_damage_by_entity');
            $hasWeaponHitEnchant = false;
            foreach (array_keys($enchants) as $enchantName) {
                if (isset($entityDamageHandlers[$enchantName])) {
                    $hasWeaponHitEnchant = true;
                    break;
                }
            }
            $allowWeaponProcs = !$hasWeaponHitEnchant
                || WeaponProcGate::recordAndAllowWeaponProcs($weaponHolder, $victim);

            if ($weaponHolder instanceof Player) {
                if (AdvancedEnchantState::isGlimmerSuppressed($weaponHolder)) {
                    $allowWeaponProcs = false;
                }
                if (AdvancedEnchantState::isCoupAttackerSilenced($weaponHolder)) {
                    $allowWeaponProcs = false;
                }
            }

            if ($allowWeaponProcs) {
                foreach ($enchants as $enchantName => $level) {
                    if ($victim instanceof Player && AdvancedEnchantState::isMalicewardImmune($victim) && MaliceWeaponRegistry::isNegativeWeaponEnchant($enchantName)) {
                        continue;
                    }
                    if (isset($entityDamageHandlers[$enchantName])) {
                        $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                        if ($enchantObj !== null) {
                            $entityDamageHandlers[$enchantName]($event, $enchantObj, $level);
                        }
                    }
                }
            }
        }

        if ($victim instanceof Player || $victim instanceof ForsakenChampion) {
            $armorContents = $this->getArmorContents($victim);
            $handlers = EnchantEventRegistry::getHandlers('entity_damage_by_entity');

            $highestLevels = [];
            foreach ($armorContents as $armorPiece) {
                $enchants = CustomEnchantmentManager::getEnchantments($armorPiece);
                foreach ($enchants as $enchantName => $level) {
                    if (!isset($handlers[$enchantName])) {
                        continue;
                    }
                    if (!isset($highestLevels[$enchantName]) || $level > $highestLevels[$enchantName]) {
                        $highestLevels[$enchantName] = $level;
                    }
                }
            }

            foreach ($highestLevels as $enchantName => $level) {
                $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                if ($enchantObj === null) {
                    continue;
                }
                $handlers[$enchantName]($event, $enchantObj, $level);
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

        if (RuneManager::hasSlownessImmunity($entity) && $event->getEffect()->getType() === VanillaEffects::SLOWNESS()) {
            $event->cancel();
            return;
        }

        if (PetManager::isAegisActive($entity) && $event->getEffect()->getType()->isBad()) {
            $event->cancel();
            return;
        }

        $armorInv = $entity->getArmorInventory();
        $armorContents = $armorInv->getContents();
        $handlers = EnchantEventRegistry::getHandlers('effect_add');

        $highestLevels = [];
        foreach ($armorContents as $armorItem) {
            $enchantments = CustomEnchantmentManager::getEnchantments($armorItem);
            foreach ($enchantments as $enchantName => $level) {
                if (!isset($handlers[$enchantName])) {
                    continue;
                }
                if (!isset($highestLevels[$enchantName]) || $level > $highestLevels[$enchantName]) {
                    $highestLevels[$enchantName] = $level;
                }
            }
        }

        foreach ($highestLevels as $enchantName => $level) {
            $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
            if ($enchantObj === null) {
                continue;
            }
            $handlers[$enchantName]($event, $enchantObj, $level);
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onEntityShootBow(EntityShootBowEvent $event): void
    {
        $projectile = $event->getProjectile();
        if (!$projectile instanceof Projectile) {
            return;
        }

        $this->projectileWeapons[spl_object_id($projectile)] = clone $event->getBow();
    }

    /**
     * @priority HIGHEST
     */
    public function onEffectRemove(EntityEffectRemoveEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }

        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }

        if (EnchantEffectManager::isRefreshingArmorEffects($entity)) {
            return;
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function() use ($entity): void {
                if (!$entity->isConnected()) {
                    return;
                }

                EnchantEffectManager::scheduleArmorRefresh($entity, 1);
            }
        ), 1);
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

        if (RuneManager::hasNoHungerPassive($player)) {
            $event->cancel();
            return;
        }

        $armorInv = $player->getArmorInventory();
        $armorContents = $armorInv->getContents();
        $handlers = EnchantEventRegistry::getHandlers('player_exhaust');

        $highestLevels = [];
        foreach ($armorContents as $armorItem) {
            $enchantments = CustomEnchantmentManager::getEnchantments($armorItem);
            foreach ($enchantments as $enchantName => $level) {
                if (!isset($handlers[$enchantName])) {
                    continue;
                }
                if (!isset($highestLevels[$enchantName]) || $level > $highestLevels[$enchantName]) {
                    $highestLevels[$enchantName] = $level;
                }
            }
        }

        foreach ($highestLevels as $enchantName => $level) {
            $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
            if ($enchantObj === null) {
                continue;
            }
            $handlers[$enchantName]($event, $enchantObj, $level);
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();
        VirusManager::clearEntity($entity);
        WeaponProcGate::clearForVictim($entity);

        // Handle victim armor enchants
        if ($entity instanceof Player || $entity instanceof ForsakenChampion) {
            $armorContents = $this->getArmorContents($entity);
            $handlers = EnchantEventRegistry::getHandlers('entity_death');

            $highestLevels = [];
            foreach ($armorContents as $armorItem) {
                $enchantments = CustomEnchantmentManager::getEnchantments($armorItem);
                foreach ($enchantments as $enchantName => $level) {
                    if (!isset($handlers[$enchantName])) {
                        continue;
                    }
                    if (!isset($highestLevels[$enchantName]) || $level > $highestLevels[$enchantName]) {
                        $highestLevels[$enchantName] = $level;
                    }
                }
            }

            foreach ($highestLevels as $enchantName => $level) {
                $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                if ($enchantObj === null) {
                    continue;
                }
                $handlers[$enchantName]($event, $enchantObj, $level);
            }
        }

        // Handle killer weapon enchants
        $cause = $entity->getLastDamageCause();
        if ($cause instanceof EntityDamageByEntityEvent) {
            $killer = $cause->getDamager();

            $weapon = null;
            if ($killer instanceof Projectile) {
                $weapon = $this->projectileWeapons[spl_object_id($killer)] ?? null;
                $owner = $killer->getOwningEntity();
                if ($owner instanceof Player || $owner instanceof ForsakenChampion) {
                    $killer = $owner;
                }
            }

            if ($killer instanceof Player || $killer instanceof ForsakenChampion) {
                $item = $weapon ?? $this->getCombatItem($killer);
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
        if ($victim instanceof Player) {
            if (AdvancedSetEnchants::hasSteadfastSword($victim)) {
                return;
            }
            RageEnchant::reset($victim->getName());
        }
    }

    private function getCombatItem(Player|ForsakenChampion $entity): Item
    {
        if ($entity instanceof Player) {
            return $entity->getInventory()->getItemInHand();
        }

        return $entity->getCombatItem();
    }

    private function getArmorContents(Player|ForsakenChampion $entity): array
    {
        if ($entity instanceof Player) {
            return $entity->getArmorInventory()->getContents();
        }

        return $entity->getCombatArmorContents();
    }
}
