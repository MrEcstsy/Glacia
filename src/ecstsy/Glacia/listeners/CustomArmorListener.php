<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\items\armor\HeroicArmorDurabilityManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\items\armor\abilities\YijkiLightningStrike;
use ecstsy\Glacia\server\items\armor\ArmorSetManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
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

final class CustomArmorListener implements Listener {

    /**
     * Heroic armor baseline (independent from set bonuses).
     * Weighted like vanilla slot scaling (chest > legs > boots/helmet),
     * tuned to be stronger than diamond overall for PvP.
     */
    private const HEROIC_REDUCTION_HELMET = 0.04;
    private const HEROIC_REDUCTION_CHESTPLATE = 0.10;
    private const HEROIC_REDUCTION_LEGGINGS = 0.08;
    private const HEROIC_REDUCTION_BOOTS = 0.06;
    private const HEROIC_REDUCTION_CAP = 0.32;

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
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $name = strtolower($event->getPlayer()->getName());
        unset($this->activeSets[$name]);
        unset($this->abilityCooldowns[$name]);
    }

    public function onDamage(EntityDamageByEntityEvent $event): void {
        $damager = $event->getDamager();
        $victim = $event->getEntity();

        if ($damager instanceof Player) {
            $damagerSet = ArmorSetManager::getInstance()->getFullSet($damager);
            $victimFaction = $victim instanceof Player ? Loader::getPlayerManager()->getSession($victim)?->getFaction() : null;
            $mitigation = Loader::getFactionUpgradeManager()->getArmorSetMitigationPercent($victimFaction) / 100;

            switch ($damagerSet) {
                case 'phantom':
                    $baseDamage = $event->getBaseDamage();
                    $bonusMultiplier = 0.15;

                    if ($this->isUsingGlaciaItem($damager, "phantom_weapon")) {
                        $bonusMultiplier += 0.05;
                    }

                    $damage = $baseDamage * (1 + ($bonusMultiplier * max(0.0, 1 - $mitigation)));
                    $event->setBaseDamage($damage);
                    break;

                case 'yeti':
                    $baseDamage = $event->getBaseDamage();
                    $damage = $baseDamage * (1 + (0.10 * max(0.0, 1 - $mitigation)));

                    if ($victim instanceof Player && $this->isUsingGlaciaItem($damager, "yeti_sword")) {
                        $this->damageVictimArmor($victim, 1);
                    }

                    $event->setBaseDamage($damage);
                    break;

                case 'yijki':
                    $damage = $event->getBaseDamage();

                    if ($this->isUsingGlaciaItem($damager, "yijki_sword")) {
                        $damage *= 1 + (0.10 * max(0.0, 1 - $mitigation));
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

            switch ($victimSet) {
                case 'yeti':
                    $event->setBaseDamage($event->getBaseDamage() * 0.95);
                    break;

                case 'yijki':
                    $event->setBaseDamage($event->getBaseDamage() * 0.80);

                    if ($damager instanceof Player && !$this->isAbilityOnCooldown($victim, 'revenge_of_yijki')) {
                        if (mt_rand(1, 100) <= 10) {
                            $this->setAbilityCooldown($victim, 'revenge_of_yijki', 8);

                            $lightningDamage = 4.0;

                            if ($this->isUsingGlaciaItem($victim, "yijki_sword")) {
                                $lightningDamage *= 1.25;
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

            $legendaryReduction = RuneManager::getLegendaryDamageReduction($victim);
            if ($legendaryReduction > 0.0) {
                $event->setBaseDamage($event->getBaseDamage() * max(0.0, 1.0 - ($legendaryReduction / 100)));
            }

            // Apply heroic baseline at the end so it stacks cleanly with
            // set-bonus logic but remains independent from full-set checks.
            $baseDamage = (float) $event->getBaseDamage();
            $heroicMultiplier = $this->getHeroicArmorReductionMultiplier($victim, $baseDamage);
            if ($heroicMultiplier < 1.0) {
                $event->setBaseDamage($baseDamage * $heroicMultiplier);
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

    private function getHeroicArmorReductionMultiplier(Player $player, float $damage): float
    {
        $armor = $player->getArmorInventory();

        // Only count "true heroic" pieces (Heroic Upgrade state).
        $armorPoints = 0;
        $armorPoints += $this->isHeroicArmorPiece($armor->getHelmet(), "helmet") ? 3 : 0;
        $armorPoints += $this->isHeroicArmorPiece($armor->getChestplate(), "chestplate") ? 8 : 0;
        $armorPoints += $this->isHeroicArmorPiece($armor->getLeggings(), "leggings") ? 6 : 0;
        $armorPoints += $this->isHeroicArmorPiece($armor->getBoots(), "boots") ? 3 : 0;

        if ($armorPoints <= 0 || $damage <= 0.0) {
            return 1.0;
        }

        // Mimic vanilla armor penetration scaling: reduction depends on incoming damage.
        // We treat heroic as "armor-only" with 0 armor toughness (so it follows the classic
        // armorPoints - damage/(2 + toughness/4) behavior).
        $toughness = 0.0;
        $armorPoints = min(20.0, (float) $armorPoints);

        $mitigationFraction = min(
            20.0,
            max(
                $armorPoints / 5.0,
                $armorPoints - ($damage / (2.0 + ($toughness / 4.0)))
            )
        ) / 25.0; // 0.00 .. 0.80

        $mitigationFraction = max(0.0, min(0.8, $mitigationFraction));
        return 1.0 - $mitigationFraction;
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
