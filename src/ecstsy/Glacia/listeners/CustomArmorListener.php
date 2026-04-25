<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\armor\abilities\YijkiLightningStrike;
use ecstsy\Glacia\server\items\armor\ArmorSetManager;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

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
        }

        if ($victim instanceof Player) {
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
                $armor->applyDamage($amount);
                $armorInventory->setItem($slot, $armor);
            }
        }
    }
}
