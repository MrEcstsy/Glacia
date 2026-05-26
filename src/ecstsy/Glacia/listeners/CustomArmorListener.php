<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\items\armor\HeroicArmorDurabilityManager;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\items\armor\abilities\YijkiLightningStrike;
use ecstsy\Glacia\server\items\armor\ArmorSetManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\CriticalParticle;
use pocketmine\world\particle\PortalParticle;

final class CustomArmorListener implements Listener {

    /** @var array<string, string|null> */
    private array $activeSets = [];

    /** @var array<string, array<string, int>> */
    private array $abilityCooldowns = [];

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();

        $onSlot = function (Inventory $inventory, int $slot, Item $oldItem): void {
            if (!$inventory instanceof ArmorInventory) {
                return;
            }

            $holder = $inventory->getHolder();
            if (!$holder instanceof Player) {
                return;
            }

            $this->checkArmorSetActivation($holder);
        };

        $onContent = function (Inventory $inventory, array $oldContents) use ($onSlot): void {
            if (!$inventory instanceof ArmorInventory) {
                return;
            }

            foreach ($oldContents as $slot => $oldItem) {
                if (!$oldItem->equals($inventory->getItem($slot), false)) {
                    $onSlot($inventory, $slot, $oldItem);
                }
            }
        };

        $player->getArmorInventory()->getListeners()->add(new CallbackInventoryListener($onSlot, $onContent));

        $this->checkArmorSetActivation($player);
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function () use ($player): void {
                if ($player->isConnected()) {
                    ArmorSetManager::getInstance()->syncPlayerCustomArmorLore($player);
                }
            }
        ), 1);
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $name = strtolower($event->getPlayer()->getName());
        unset($this->activeSets[$name]);
        unset($this->abilityCooldowns[$name]);
    }

    public function onArmorTransaction(InventoryTransactionEvent $event): void
    {
        $source = $event->getTransaction()->getSource();
        if (!$source instanceof Player) {
            return;
        }

        foreach ($event->getTransaction()->getActions() as $action) {
            if (!$action instanceof \pocketmine\inventory\transaction\action\SlotChangeAction) {
                continue;
            }

            $inventory = $action->getInventory();
            if (!$inventory instanceof ArmorInventory || $inventory->getHolder() !== $source) {
                continue;
            }

            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function() use ($source): void {
                    if ($source->isConnected()) {
                        $this->checkArmorSetActivation($source);
                    }
                }
            ), 1);
            return;
        }
    }

    public function onDamage(EntityDamageByEntityEvent $event): void {
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        $ignoreVictimDefense = $damager instanceof Player && PetManager::isDefenseBreakActive($damager);

        if ($damager instanceof Player) {
            $damagerSet = ArmorSetManager::getInstance()->getFullSet($damager);
            $victimFaction = $victim instanceof Player ? Loader::getPlayerManager()->getSession($victim)?->getFaction() : null;
            $mitigation = Loader::getFactionUpgradeManager()->getArmorSetMitigationPercent($victimFaction) / 100;

            switch ($damagerSet) {
                case 'phantom':
                    $baseDamage = $event->getBaseDamage();
                    $boost = AdvancedEnchantState::getNatureParadoxBoostMultiplier($damager, 'phantom');
                    $bonusMultiplier = EnchantTuningManager::getArmorSetFloat('phantom', 'attack-bonus-fraction', 0.20) * $boost;

                    if ($this->isUsingGlaciaItem($damager, "phantom_weapon")) {
                        $bonusMultiplier += EnchantTuningManager::getArmorSetFloat('phantom', 'weapon-bonus-fraction', 0.08);
                    }

                    $damage = $baseDamage * (1 + ($bonusMultiplier * max(0.0, 1 - $mitigation)));
                    $event->setBaseDamage($damage);

                    if ($victim instanceof Player && !$this->isAbilityOnCooldown($damager, 'phantom_rend')) {
                        if (mt_rand(1, 100) <= EnchantTuningManager::getArmorSetInt('phantom', 'rend-proc-chance-percent', 18)) {
                            $trueDamage = EnchantTuningManager::getArmorSetFloat('phantom', 'rend-true-damage', 4.5) * $boost;
                            if ($this->isUsingGlaciaItem($damager, "phantom_weapon")) {
                                $trueDamage += EnchantTuningManager::getArmorSetFloat('phantom', 'rend-weapon-bonus-damage', 2.0);
                            }

                            if (!$this->dealSetTrueDamage($damager, $victim, $trueDamage)->isCancelled()) {
                                $this->setAbilityCooldown($damager, 'phantom_rend', 7);
                                $w = $victim->getWorld();
                                $base = $victim->getPosition()->add(0, 1.0, 0);
                                for ($pi = 0; $pi < 10; $pi++) {
                                    $w->addParticle($base->add(mt_rand(-15, 15) / 20, mt_rand(0, 12) / 20, mt_rand(-15, 15) / 20), new CriticalParticle());
                                }
                                for ($pi = 0; $pi < 6; $pi++) {
                                    $w->addParticle($base->add(mt_rand(-10, 10) / 20, mt_rand(0, 8) / 20, mt_rand(-10, 10) / 20), new PortalParticle());
                                }
                                $victim->getEffects()->add(new EffectInstance(VanillaEffects::WEAKNESS(), 50, 0));
                                $damager->sendMessage(C::colorize("&r&5Dreadveil Rend &7cuts through armor and leaves your target reeling."));
                            }
                        }
                    }
                    break;

                case 'yeti':
                    $baseDamage = $event->getBaseDamage();
                    $boost = AdvancedEnchantState::getNatureParadoxBoostMultiplier($damager, 'yeti');
                    $damage = $baseDamage * (1 + ((EnchantTuningManager::getArmorSetFloat('yeti', 'attack-bonus-per-boost', 0.14) * $boost) * max(0.0, 1 - $mitigation)));

                    if ($victim instanceof Player && $this->isUsingGlaciaItem($damager, "yeti_sword")) {
                        $this->damageVictimArmor($victim, 2);
                        if (!$this->isAbilityOnCooldown($damager, 'yeti_crash')) {
                            $this->setAbilityCooldown($damager, 'yeti_crash', 9);
                            $victim->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 50, 1));
                            $victim->getEffects()->add(new EffectInstance(VanillaEffects::MINING_FATIGUE(), 40, 0));
                            $damager->sendMessage(C::colorize("&r&bFrostbound Crash &7batters your target's armor."));
                        }
                    }

                    $event->setBaseDamage($damage);
                    break;

                case 'yijki':
                    $damage = $event->getBaseDamage();

                    if ($this->isUsingGlaciaItem($damager, "yijki_sword")) {
                        $boost = AdvancedEnchantState::getNatureParadoxBoostMultiplier($damager, 'yijki');
                        $damage *= 1 + ((EnchantTuningManager::getArmorSetFloat('yijki', 'attack-bonus-fraction', 0.06) * $boost) * max(0.0, 1 - $mitigation));
                    }

                    $event->setBaseDamage($damage);
                    break;
            }

            if ($victim instanceof Player && RuneManager::shouldTriggerPotionPurge($damager)) {
                $event->setBaseDamage($event->getBaseDamage() * RuneManager::getNullFieldDamageMultiplier($damager));
                $radius = RuneManager::getPotionPurgeRadius();
                foreach ($damager->getWorld()->getPlayers() as $nearby) {
                    if ($nearby->getPosition()->distanceSquared($damager->getPosition()) > ($radius * $radius)) {
                        continue;
                    }

                    if (GlaciaUtils::isAlly($damager, $nearby)) {
                        continue;
                    }

                    $nearby->getEffects()->clear();
                }
            }
        }

        if ($victim instanceof Player) {
            $this->scheduleHeroicDurabilityReconcile($victim);

            if (
                $damager instanceof Player &&
                RuneManager::hasStormcallerWard($victim) &&
                $event->getCause() === \pocketmine\event\entity\EntityDamageEvent::CAUSE_MAGIC &&
                ArmorSetManager::getInstance()->getFullSet($damager) === "yijki"
            ) {
                $event->cancel();
                return;
            }

            $victimSet = ArmorSetManager::getInstance()->getFullSet($victim);

            if (!$ignoreVictimDefense) {
                switch ($victimSet) {
                    case 'yeti':
                        $boost = AdvancedEnchantState::getNatureParadoxBoostMultiplier($victim, 'yeti');
                        $reductionPerBoost = EnchantTuningManager::getArmorSetFloat('yeti', 'damage-reduction-per-boost', 0.07);
                        $reductionCap = EnchantTuningManager::getArmorSetFloat('yeti', 'damage-reduction-cap', 0.90);
                        $event->setBaseDamage($event->getBaseDamage() * (1.0 - min($reductionCap, $reductionPerBoost * $boost)));

                        if ($damager instanceof Player && !$this->isAbilityOnCooldown($victim, 'yeti_pulse')) {
                            if (mt_rand(1, 100) <= EnchantTuningManager::getArmorSetInt('yeti', 'pulse-proc-chance-percent', 14)) {
                                $pulseDamage = EnchantTuningManager::getArmorSetFloat('yeti', 'permafrost-pulse-damage', 5.5) * $boost;
                                $retaliation = $this->dealSetTrueDamage($victim, $damager, $pulseDamage);
                                if (!$retaliation->isCancelled()) {
                                    $this->setAbilityCooldown($victim, 'yeti_pulse', 9);
                                    $damager->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 70, 1));
                                    $damager->getEffects()->add(new EffectInstance(VanillaEffects::WEAKNESS(), 45, 0));
                                    $delta = $damager->getPosition()->subtractVector($victim->getPosition());
                                    $lengthSq = ($delta->x * $delta->x) + ($delta->z * $delta->z);
                                    if ($lengthSq > 0.0001) {
                                        $length = sqrt($lengthSq);
                                        $damager->setMotion(new Vector3(($delta->x / $length) * 0.48, 0.24, ($delta->z / $length) * 0.48));
                                    }
                                    $victim->sendMessage(C::colorize("&r&bPermafrost Pulse &7lashes back and chills your attacker to the bone."));
                                }
                            }
                        }
                        break;

                    case 'yijki':
                        $boost = AdvancedEnchantState::getNatureParadoxBoostMultiplier($victim, 'yijki');
                        $attackerSet = $damager instanceof Player ? ArmorSetManager::getInstance()->getFullSet($damager) : null;
                        $mirrorMatchDisablesReduction = EnchantTuningManager::getArmorSetBool('yijki', 'mirror-match-disables-reduction', false);

                        if (!$mirrorMatchDisablesReduction || $attackerSet !== 'yijki') {
                            $baseDamage = (float) $event->getBaseDamage();
                            $reductionPerBoost = EnchantTuningManager::getArmorSetFloat('yijki', 'damage-reduction-per-boost', 0.03);
                            $reductionCap = EnchantTuningManager::getArmorSetFloat('yijki', 'damage-reduction-cap', 0.05);
                            $event->setBaseDamage($baseDamage * (1.0 - min($reductionCap, $reductionPerBoost * $boost)));
                        }

                        if ($damager instanceof Player && !$this->isAbilityOnCooldown($victim, 'revenge_of_yijki')) {
                            if (mt_rand(1, 100) <= EnchantTuningManager::getArmorSetInt('yijki', 'revenge-proc-chance-percent', 6)) {
                                $this->setAbilityCooldown($victim, 'revenge_of_yijki', EnchantTuningManager::getArmorSetInt('yijki', 'revenge-cooldown-seconds', 12));

                                $lightningDamage = EnchantTuningManager::getArmorSetFloat('yijki', 'revenge-lightning-damage', 2.75);

                                if ($this->isUsingGlaciaItem($victim, "yijki_sword")) {
                                    $lightningDamage *= EnchantTuningManager::getArmorSetFloat('yijki', 'revenge-lightning-weapon-multiplier', 1.1);
                                }

                                YijkiLightningStrike::strike(
                                    $victim,
                                    $damager->getPosition(),
                                    $lightningDamage,
                                    3.0
                                );

                                $victim->sendMessage(C::colorize("&r&eRevenge of Stormcaller &7has been unleashed!"));
                            }
                        }
                        break;
                }
            }

            if (!$ignoreVictimDefense) {
                $legendaryReduction = RuneManager::getLegendaryDamageReduction($victim);
                if ($legendaryReduction > 0.0) {
                    $baseDamage = (float) $event->getBaseDamage();
                    $appliedReduction = max(0.0, min(1.0, $legendaryReduction / 100));
                    $event->setBaseDamage($baseDamage * (1.0 - $appliedReduction));
                }
            }

            // Apply heroic baseline at the end so it stacks cleanly with
            // set-bonus logic but remains independent from full-set checks.
            $baseDamage = (float) $event->getBaseDamage();
            if (!$ignoreVictimDefense) {
                $heroicMultiplier = $this->getHeroicArmorReductionMultiplier($victim, $baseDamage);
                if ($heroicMultiplier < 1.0) {
                    $event->setBaseDamage($baseDamage * $heroicMultiplier);
                }
            }
        }
    }

    private function checkArmorSetActivation(Player $player): void {
        $name = strtolower($player->getName());

        $previous = $this->activeSets[$name] ?? null;
        $current = ArmorSetManager::getInstance()->getFullSet($player);

        if ($previous === $current) {
            return;
        }

        if ($previous !== null) {
            switch ($previous) {
                case 'phantom':
                    $player->sendMessage(C::colorize("&r&5Dreadveil Set Bonus deactivated."));
                    break;
                case 'yeti':
                    $player->sendMessage(C::colorize("&r&l&bFrostbound Set Bonus deactivated."));
                    break;
                case 'yijki':
                    $player->sendMessage(C::colorize("&r&l&fStormcaller Set Bonus deactivated."));
                    break;
            }
        }

        if ($current !== null) {
            switch ($current) {
                case 'phantom':
                    $player->sendMessage(C::colorize("&r&l&5Dreadveil Set Bonus activated!"));
                    break;
                case 'yeti':
                    $player->sendMessage(C::colorize("&r&l&bFrostbound Set Bonus activated!"));
                    break;
                case 'yijki':
                    $player->sendMessage(C::colorize("&r&l&fStormcaller Set Bonus activated!"));
                    break;
            }
        }

        $this->activeSets[$name] = $current;
    }

    private function isAbilityOnCooldown(Player $player, string $ability): bool {
        $playerKey = strtolower($player->getName());
        $abilityKey = strtolower($ability);

        return isset($this->abilityCooldowns[$playerKey][$abilityKey])
            && $this->abilityCooldowns[$playerKey][$abilityKey] > time();
    }

    private function setAbilityCooldown(Player $player, string $ability, int $seconds): void {
        $playerKey = strtolower($player->getName());
        $abilityKey = strtolower($ability);

        $this->abilityCooldowns[$playerKey][$abilityKey] = time() + $seconds;
    }

    private function getAbilityCooldownLeft(Player $player, string $ability): int {
        $playerKey = strtolower($player->getName());
        $abilityKey = strtolower($ability);

        $endsAt = $this->abilityCooldowns[$playerKey][$abilityKey] ?? 0;
        return max(0, $endsAt - time());
    }

    private function clearAbilityCooldown(Player $player, string $ability): void {
        $playerKey = strtolower($player->getName());
        $abilityKey = strtolower($ability);

        unset($this->abilityCooldowns[$playerKey][$abilityKey]);

        if (empty($this->abilityCooldowns[$playerKey])) {
            unset($this->abilityCooldowns[$playerKey]);
        }
    }

    private function clearAllAbilityCooldowns(Player $player): void {
        unset($this->abilityCooldowns[strtolower($player->getName())]);
    }

    private function isUsingGlaciaItem(Player $player, string $identifier): bool {
        $item = $player->getInventory()->getItemInHand();
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");

        if ($glacia === null) {
            return false;
        }

        return $glacia->getString("glaciaItem", "") === $identifier;
    }

    private function damageVictimArmor(Player $victim, int $amount): void {
        $armorInventory = $victim->getArmorInventory();

        foreach ($armorInventory->getContents() as $slot => $armor) {
            if ($armor instanceof Durable && !$armor->isNull()) {
                HeroicArmorDurabilityManager::applyVirtualDurabilityDamage($armor, $amount);
                $armorInventory->setItem($slot, $armor);
            }
        }
    }

    private function dealSetTrueDamage(Player $attacker, Player $victim, float $damage): EntityDamageByEntityEvent
    {
        $event = new EntityDamageByEntityEvent(
            $attacker,
            $victim,
            EntityDamageEvent::CAUSE_MAGIC,
            max(1.0, $damage),
            [],
            0.0,
            0.0
        );
        $victim->attack($event);
        return $event;
    }

    private function getHeroicArmorReductionMultiplier(Player $player, float $damage): float
    {
        $armor = $player->getArmorInventory();

        if ($damage <= 0.0) {
            return 1.0;
        }

        $reduction = 0.0;
        $reduction += $this->isHeroicArmorPiece($armor->getHelmet(), "helmet")
            ? max(0.0, EnchantTuningManager::getFloat("heroicarmor", "helmet-reduction", 0.04))
            : 0.0;
        $reduction += $this->isHeroicArmorPiece($armor->getChestplate(), "chestplate")
            ? max(0.0, EnchantTuningManager::getFloat("heroicarmor", "chestplate-reduction", 0.10))
            : 0.0;
        $reduction += $this->isHeroicArmorPiece($armor->getLeggings(), "leggings")
            ? max(0.0, EnchantTuningManager::getFloat("heroicarmor", "leggings-reduction", 0.08))
            : 0.0;
        $reduction += $this->isHeroicArmorPiece($armor->getBoots(), "boots")
            ? max(0.0, EnchantTuningManager::getFloat("heroicarmor", "boots-reduction", 0.06))
            : 0.0;

        if ($reduction <= 0.0) {
            return 1.0;
        }

        $cap = max(0.0, EnchantTuningManager::getFloat("heroicarmor", "total-reduction-cap", 0.32));
        $mitigationFraction = min($cap, $reduction);
        return 1.0 - max(0.0, min(1.0, $mitigationFraction));
    }

    private function isHeroicArmorPiece(Item $item, ?string $requiredPiece = null): bool
    {
        return HeroicArmorDurabilityManager::isHeroicArmor($item, $requiredPiece);
    }

    private function scheduleHeroicDurabilityReconcile(Player $victim): void
    {
        $snapshot = [];
        $armorInventory = $victim->getArmorInventory();
        foreach ($armorInventory->getContents() as $slot => $item) {
            if (!$item instanceof Durable || !$this->isHeroicArmorPiece($item)) {
                continue;
            }
            $snapshot[$slot] = $item->getDamage();
        }

        if ($snapshot === []) {
            return;
        }

        $playerName = $victim->getName();
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function () use ($playerName, $snapshot): void {
                $player = Loader::getInstance()->getServer()->getPlayerExact($playerName);
                if (!$player instanceof Player || !$player->isOnline()) {
                    return;
                }

                $armor = $player->getArmorInventory();
                foreach ($snapshot as $slot => $oldDamage) {
                    $item = $armor->getItem((int) $slot);
                    if (!$item instanceof Durable || !$this->isHeroicArmorPiece($item)) {
                        continue;
                    }

                    if (HeroicArmorDurabilityManager::absorbObservedDamageIncrease($item, (int) $oldDamage)) {
                        $armor->setItem((int) $slot, $item);
                    }
                }
            }
        ), 1);
    }
}
