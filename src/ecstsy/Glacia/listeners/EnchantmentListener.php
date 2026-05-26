<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\MummifiedRemnant;
use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\enchantments\enchants\all\ReforgedEnchant;
use ecstsy\Glacia\server\enchantments\enchants\all\ReforgedLogic;
use ecstsy\Glacia\server\enchantments\enchants\armor\ArmageddonEnchant;
use ecstsy\Glacia\server\enchantments\enchants\helmet\TricksterEnchant;
use ecstsy\Glacia\server\enchantments\enchants\helmet\ImplantsEnchant;
use ecstsy\Glacia\server\enchantments\enchants\helmet\WellFedEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\RageEnchant;
use ecstsy\Glacia\server\enchantments\manager\AngelicHealingManager;
use ecstsy\Glacia\server\enchantments\manager\BleedManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantEffectManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantEventRegistry;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantmentEventRegistry;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use ecstsy\Glacia\server\enchantments\manager\WeaponProcGate;
use ecstsy\Glacia\server\enchantments\manager\SilenceManager;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\MaliceWeaponRegistry;
use ecstsy\Glacia\server\enchantments\manager\MirrorbrandProcManager;
use ecstsy\Glacia\server\enchantments\manager\SoulGemSessionManager;
use ecstsy\Glacia\server\enchantments\manager\VirusManager;
use ecstsy\Glacia\server\enchantments\advanced\AdvancedSetEnchants;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\zones\ZoneManager;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\event\entity\EntityEffectRemoveEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\item\enchantment\VanillaEnchantments;
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
    /** @var array<string, Item> */
    private array $lastShotBows = [];

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
        $player = $event->getPlayer();

        if (
            $from->getFloorX() === $to->getFloorX() &&
            $from->getFloorY() === $to->getFloorY() &&
            $from->getFloorZ() === $to->getFloorZ()
        ) {
            return;
        }

        EnchantEffectManager::refreshArmorEffects($player);

        $implantsLevel = CustomEnchantmentManager::getLevel($player->getArmorInventory()->getHelmet(), "implants");
        if ($implantsLevel > 0) {
            ImplantsEnchant::tick($player, $implantsLevel);
        }
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
        SoulGemSessionManager::clearOnQuit($player);
        WellFedEnchant::clearPlayer($player);
        MirrorbrandProcManager::clearPlayer($player);
        unset($this->lastShotBows[$player->getUniqueId()->toString()]);
    }

    /**
     * @priority HIGHEST
     */
    public function onPlayerItemHeld(PlayerItemHeldEvent $event): void
    {
        $player = $event->getPlayer();
        if (AdvancedEnchantState::isInventoryLocked($player)) {
            $event->cancel();
            return;
        }
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
        if (!$entity instanceof Player && !$entity instanceof ForsakenChampion && !$entity instanceof MummifiedRemnant) return;

        if ($entity instanceof Player && EnchantCombatStateManager::areCustomEnchantsDisabled($entity)) {
            return;
        }

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
            if ($enchObj === null || ($entity instanceof Player && $this->shouldSuppressEndfallEnchant($entity, $enchObj))) {
                continue;
            }
            $handlers[$enchantName]($event, $enchObj, $level);
        }

        $item = $this->getCombatItem($entity);
        $enchants = CustomEnchantmentManager::getEnchantments($item);

        foreach ($enchants as $enchantName => $level) {
            if (isset($handlers[$enchantName])) {
                $enchObj = CustomEnchantmentManager::getEnchantment($enchantName);
                if ($enchObj !== null && !($entity instanceof Player && $this->shouldSuppressEndfallEnchant($entity, $enchObj))) {
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

        $baseDamageBeforeEnchants = $event->getBaseDamage();
        $victim = $event->getEntity();
        $damager = $event->getDamager();
        $projectileDamager = null;
        if ($damager instanceof Projectile) {
            $projectileDamager = $damager;
        } elseif ($event instanceof EntityDamageByChildEntityEvent && $event->getChild() instanceof Projectile) {
            $projectileDamager = $event->getChild();
        }

        if ($damager instanceof Player && $victim instanceof Player) {
            ArmageddonEnchant::applyActiveHitEffects($event);
        }

        if ($damager instanceof Player && EnchantCombatStateManager::isPlagueWeakened($damager)) {
            $event->setBaseDamage($event->getBaseDamage() * 0.5);
        }

        $weaponHolder = null;
        $weapon = null;
        if ($projectileDamager instanceof Projectile) {
            $weapon = $this->projectileWeapons[$projectileDamager->getId()] ?? null;
            $owner = $projectileDamager->getOwningEntity();
            if ($owner instanceof Player || $owner instanceof ForsakenChampion || $owner instanceof MummifiedRemnant) {
                $weaponHolder = $owner;
                if ($weapon === null && $owner instanceof Player) {
                    $weapon = $this->lastShotBows[$owner->getUniqueId()->toString()] ?? null;
                }
            }
        } elseif ($damager instanceof Player || $damager instanceof ForsakenChampion || $damager instanceof MummifiedRemnant) {
            $weaponHolder = $damager;
        }

        if ($projectileDamager === null && $weaponHolder instanceof Player) {
            $weapon ??= $this->getCombatItem($weaponHolder);
            $sharpnessBonus = $this->getVanillaSharpnessDamageBonus($weapon);
            if ($sharpnessBonus > 0.0) {
                $event->setBaseDamage($event->getBaseDamage() + $sharpnessBonus);
            }
        }

        if ($weaponHolder instanceof Player || $weaponHolder instanceof ForsakenChampion || $weaponHolder instanceof MummifiedRemnant) {
            $weapon ??= $this->getCombatItem($weaponHolder);
            $enchants = CustomEnchantmentManager::getEnchantments($weapon);
            $entityDamageHandlers = EnchantEventRegistry::getHandlers('entity_damage_by_entity');
            $hasLegacyGatedWeaponHitEnchant = false;
            foreach (array_keys($enchants) as $enchantName) {
                if (isset($entityDamageHandlers[$enchantName])) {
                    if (!EnchantTuningManager::isWeaponProcGateExempt($enchantName)) {
                        $hasLegacyGatedWeaponHitEnchant = true;
                    }
                }
            }
            $legacyWeaponProcsAllowed = !$hasLegacyGatedWeaponHitEnchant
                || WeaponProcGate::recordAndAllowWeaponProcs($weaponHolder, $victim);
            $allowWeaponProcs = true;

            if ($weaponHolder instanceof Player) {
                if (AdvancedEnchantState::isGlimmerSuppressed($weaponHolder)) {
                    $allowWeaponProcs = false;
                }
                if (AdvancedEnchantState::isCoupAttackerSilenced($weaponHolder)) {
                    $allowWeaponProcs = false;
                }
                if (EnchantCombatStateManager::areCustomEnchantsDisabled($weaponHolder)) {
                    $allowWeaponProcs = false;
                }
            }

            if ($allowWeaponProcs) {
                foreach ($enchants as $enchantName => $level) {
                    if (!isset($entityDamageHandlers[$enchantName])) {
                        continue;
                    }
                    if (!EnchantTuningManager::isWeaponProcGateExempt($enchantName) && !$legacyWeaponProcsAllowed) {
                        continue;
                    }
                    if ($victim instanceof Player && AdvancedEnchantState::isMalicewardImmune($victim) && MaliceWeaponRegistry::isNegativeWeaponEnchant($enchantName)) {
                        continue;
                    }
                    $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                    if ($enchantObj !== null && !($weaponHolder instanceof Player && $this->shouldSuppressEndfallEnchant($weaponHolder, $enchantObj))) {
                        $entityDamageHandlers[$enchantName]($event, $enchantObj, $level);
                    }
                }
            }
        }

        if ($damager instanceof Player && $victim instanceof Player) {
            $highestLevel = 0;
            foreach ($damager->getArmorInventory()->getContents() as $armorPiece) {
                $level = CustomEnchantmentManager::getLevel($armorPiece, 'armageddon');
                if ($level > $highestLevel) {
                    $highestLevel = $level;
                }
            }

            if ($highestLevel > 0) {
                $enchantObj = CustomEnchantmentManager::getEnchantment('armageddon');
                if ($enchantObj !== null) {
                    ArmageddonEnchant::handle($event, $enchantObj, $highestLevel);
                }
            }
        }

        if ($victim instanceof Player || $victim instanceof ForsakenChampion || $victim instanceof MummifiedRemnant) {
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

            if ($victim instanceof Player && EnchantCombatStateManager::areCustomEnchantsDisabled($victim)) {
                $this->clampEnchantDamageSpike($event, $baseDamageBeforeEnchants);
                return;
            }

            foreach ($highestLevels as $enchantName => $level) {
                $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                if ($enchantObj === null || ($victim instanceof Player && $this->shouldSuppressEndfallEnchant($victim, $enchantObj))) {
                    continue;
                }
                $handlers[$enchantName]($event, $enchantObj, $level);
            }
        }

        $this->clampEnchantDamageSpike($event, $baseDamageBeforeEnchants);
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
                if ($enchantObj !== null && !$this->shouldSuppressEndfallEnchant($player, $enchantObj)) {
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
            if ($enchantObj === null || $this->shouldSuppressEndfallEnchant($entity, $enchantObj)) {
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

        $bow = clone $event->getBow();
        $this->projectileWeapons[$projectile->getId()] = $bow;

        $shooter = $event->getEntity();
        if ($shooter instanceof Player) {
            $this->lastShotBows[$shooter->getUniqueId()->toString()] = clone $bow;
        }
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

        if (EnchantCombatStateManager::areCustomEnchantsDisabled($player)) {
            return;
        }

        $enchantments = CustomEnchantmentManager::getEnchantments($item);

        foreach ($enchantments as $enchantName => $level) {
            $handlers = EnchantEventRegistry::getHandlers('interact');
            if (isset($handlers[$enchantName])) {
                $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);

                if ($enchantObj !== null && !$this->shouldSuppressEndfallEnchant($player, $enchantObj)) {
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

        if (EnchantCombatStateManager::areCustomEnchantsDisabled($player)) {
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
            if ($enchantObj === null || $this->shouldSuppressEndfallEnchant($player, $enchantObj)) {
                continue;
            }
            $handlers[$enchantName]($event, $enchantObj, $level);
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onPlayerItemConsume(PlayerItemConsumeEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }

        $player = $event->getPlayer();
        if (!$player instanceof Player) {
            return;
        }

        if (EnchantCombatStateManager::areCustomEnchantsDisabled($player)) {
            return;
        }

        $handlers = EnchantEventRegistry::getHandlers('player_consume');
        if ($handlers === []) {
            return;
        }

        $highestLevels = [];
        foreach ($player->getArmorInventory()->getContents() as $armorItem) {
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
            if ($enchantObj === null || $this->shouldSuppressEndfallEnchant($player, $enchantObj)) {
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
        if ($entity instanceof Player || $entity instanceof ForsakenChampion || $entity instanceof MummifiedRemnant) {
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

            if (!$entity instanceof Player || !EnchantCombatStateManager::areCustomEnchantsDisabled($entity)) {
                foreach ($highestLevels as $enchantName => $level) {
                    $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                    if ($enchantObj === null || ($entity instanceof Player && $this->shouldSuppressEndfallEnchant($entity, $enchantObj))) {
                        continue;
                    }
                    $handlers[$enchantName]($event, $enchantObj, $level);
                }
            }
        }

        // Handle killer weapon enchants
        $cause = $entity->getLastDamageCause();
        if ($cause instanceof EntityDamageByEntityEvent) {
            $killer = $cause->getDamager();

            $weapon = null;
            if ($killer instanceof Projectile) {
                $weapon = $this->projectileWeapons[$killer->getId()] ?? null;
                $owner = $killer->getOwningEntity();
                if ($owner instanceof Player || $owner instanceof ForsakenChampion) {
                    $killer = $owner;
                }
            }

            if ($killer instanceof Player || $killer instanceof ForsakenChampion || $killer instanceof MummifiedRemnant) {
                $item = $weapon ?? $this->getCombatItem($killer);
                $enchantments = CustomEnchantmentManager::getEnchantments($item);

                if (!$killer instanceof Player || !EnchantCombatStateManager::areCustomEnchantsDisabled($killer)) {
                    foreach ($enchantments as $enchantName => $level) {
                        $handlers = EnchantEventRegistry::getHandlers('entity_death');
                        if (isset($handlers[$enchantName])) {
                            $enchantObj = CustomEnchantmentManager::getEnchantment($enchantName);
                            if ($enchantObj !== null && !($killer instanceof Player && $this->shouldSuppressEndfallEnchant($killer, $enchantObj))) {
                                $handlers[$enchantName]($event, $enchantObj, $level);
                            }
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
            if (RageEnchant::shouldPreserveOnDamage($victim) || AdvancedSetEnchants::hasSteadfastSword($victim)) {
                return;
            }
            RageEnchant::reset($victim->getName());
        }
    }

    private function getCombatItem(Player|ForsakenChampion|MummifiedRemnant $entity): Item
    {
        if ($entity instanceof Player) {
            return $entity->getInventory()->getItemInHand();
        }

        return $entity->getCombatItem();
    }

    private function getArmorContents(Player|ForsakenChampion|MummifiedRemnant $entity): array
    {
        if ($entity instanceof Player) {
            return $entity->getArmorInventory()->getContents();
        }

        return $entity->getCombatArmorContents();
    }

    private function shouldSuppressEndfallEnchant(Player $player, CustomEnchantment $enchant): bool
    {
        if (!$this->isPlayerInsideEndfall($player)) {
            return false;
        }

        return in_array($enchant->getRarity(), [
            Groups::getGroupId("mastery"),
            Groups::getGroupId("exclusive"),
        ], true);
    }

    private function isPlayerInsideEndfall(Player $player): bool
    {
        $zone = ZoneManager::getZoneAt($player->getPosition());
        return strtolower((string) ($zone["id"] ?? "")) === "endfall";
    }

    private function clampEnchantDamageSpike(EntityDamageByEntityEvent $event, float $baseDamageBeforeEnchants): void
    {
        $cap = $this->resolvePerEnchantAddedDamageCap($event);
        if ($cap === null || $cap <= 0.0) {
            return;
        }

        $maxDamage = $baseDamageBeforeEnchants + $cap;
        if ($event->getBaseDamage() > $maxDamage) {
            $event->setBaseDamage($maxDamage);
        }
    }

    private function resolvePerEnchantAddedDamageCap(EntityDamageByEntityEvent $event): ?float
    {
        $caps = [];

        $damager = $event->getDamager();
        $projectileDamager = null;
        if ($damager instanceof Projectile) {
            $projectileDamager = $damager;
        } elseif ($event instanceof EntityDamageByChildEntityEvent && $event->getChild() instanceof Projectile) {
            $projectileDamager = $event->getChild();
        }

        $weaponHolder = null;
        $weapon = null;
        if ($projectileDamager instanceof Projectile) {
            $weapon = $this->projectileWeapons[$projectileDamager->getId()] ?? null;
            $owner = $projectileDamager->getOwningEntity();
            if ($owner instanceof Player || $owner instanceof ForsakenChampion || $owner instanceof MummifiedRemnant) {
                $weaponHolder = $owner;
                if ($weapon === null && $owner instanceof Player) {
                    $weapon = $this->lastShotBows[$owner->getUniqueId()->toString()] ?? null;
                }
            }
        } elseif ($damager instanceof Player || $damager instanceof ForsakenChampion || $damager instanceof MummifiedRemnant) {
            $weaponHolder = $damager;
        }

        if ($weaponHolder instanceof Player || $weaponHolder instanceof ForsakenChampion || $weaponHolder instanceof MummifiedRemnant) {
            $weapon ??= $this->getCombatItem($weaponHolder);
            foreach (CustomEnchantmentManager::getEnchantments($weapon) as $enchantName => $level) {
                $cap = EnchantTuningManager::getPerEnchantAddedDamageCap($enchantName, (int) $level, null);
                if ($cap !== null) {
                    $caps[] = $cap;
                }
            }
        }

        $victim = $event->getEntity();
        if ($victim instanceof Player || $victim instanceof ForsakenChampion || $victim instanceof MummifiedRemnant) {
            foreach ($this->getArmorContents($victim) as $armorPiece) {
                foreach (CustomEnchantmentManager::getEnchantments($armorPiece) as $enchantName => $level) {
                    $cap = EnchantTuningManager::getPerEnchantAddedDamageCap($enchantName, (int) $level, null);
                    if ($cap !== null) {
                        $caps[] = $cap;
                    }
                }
            }
        }

        if ($caps === []) {
            return null;
        }

        return min($caps);
    }

    private function getVanillaSharpnessDamageBonus(Item $weapon): float
    {
        $sharpnessLevel = 0;
        foreach ($weapon->getEnchantments() as $instance) {
            if ($instance->getType() === VanillaEnchantments::SHARPNESS()) {
                $sharpnessLevel = max($sharpnessLevel, $instance->getLevel());
            }
        }

        if ($sharpnessLevel <= 0) {
            return 0.0;
        }

        $perLevelBonus = max(0.0, EnchantTuningManager::getBalanceFloat("sharpness-melee-bonus-per-level", 0.05));
        return $sharpnessLevel * $perLevelBonus;
    }
}
