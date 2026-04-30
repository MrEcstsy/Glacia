<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\enchants\chestplate\KerbusEnchant;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class DisarmorEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Living || !$victim instanceof Player) return;

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null || !CustomEnchantmentManager::hasEnchantment($item, "disarmor")) return;

        $forcedProc = $damager instanceof Player && BrotherhoodEnchant::consumeForcedProc($damager, "disarmor");
        if (!$forcedProc) {
            $chance = EnchantTuningManager::getLevelInt("disarmor", "chance-by-level", $level, 2 + ($level * 2));
            if (!ProcLuckManager::roll($damager, "disarmor", $chance, 2, 10)) return;
        }

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;
        $cooldown = max(0, EnchantTuningManager::getInt("disarmor", "cooldown", 12));
        if (!$forcedProc && $session !== null && $cooldown > 0 && $session->isOnCooldown("disarmor")) return;

        if ($damager instanceof Player) {
            $chest = $victim->getArmorInventory()->getChestplate();
            if (CustomEnchantmentManager::getLevel($chest, "mirrorbrand") > 0
                && ProcLuckManager::roll($victim, "mirrorbrand", min(20, 4 * CustomEnchantmentManager::getLevel($chest, "mirrorbrand")), 1, 5)) {
                if (!KerbusEnchant::consumeProtection($damager)) {
                    $dInv = $damager->getArmorInventory();
                    $dSlots = [];
                    foreach ($dInv->getContents() as $s => $a) {
                        if (!$a->isNull()) {
                            $dSlots[] = $s;
                        }
                    }
                    if ($dSlots !== []) {
                        $ds = $dSlots[array_rand($dSlots)];
                        $r = $dInv->getItem($ds);
                        $dInv->setItem($ds, VanillaItems::AIR());
                        foreach ($damager->getInventory()->addItem($r) as $leftover) {
                            $damager->getWorld()->dropItem($damager->getPosition(), $leftover);
                        }
                        $victim->sendMessage(C::colorize("&r&6Mirrorbrand &7reflected your disarm!"));
                    }
                }
                if (!$forcedProc && $session !== null && $cooldown > 0) {
                    $session->addCooldown("disarmor", $cooldown);
                }
                return;
            }
        }

        $inv = $victim->getArmorInventory();
        if (KerbusEnchant::consumeProtection($victim)) {
            return;
        }
        $slots = [];
        foreach ($inv->getContents() as $slot => $armor) {
            if (!$armor->isNull() && $damager instanceof Player && AdvancedEnchantState::isFetterSlotLocked($victim, $slot)) {
                continue;
            }
            if (!$armor->isNull()) {
                $slots[] = $slot;
            }
        }
        if ($slots === []) {
            return;
        }

        $slot = $slots[array_rand($slots)];
        $removed = $inv->getItem($slot);
        $inv->setItem($slot, VanillaItems::AIR());
        foreach ($victim->getInventory()->addItem($removed) as $leftover) {
            $victim->getWorld()->dropItem($victim->getPosition(), $leftover);
        }

        if ($damager instanceof Player) {
            $fl = CustomEnchantmentManager::getLevel($damager->getArmorInventory()->getBoots(), "fetter");
            if ($fl > 0) {
                AdvancedEnchantState::lockFetterSlot($victim, $slot, 3 * $fl);
            }
        }

        if ($damager instanceof Player) {
            $damager->sendMessage(C::colorize("&r&4Disarmor &r&7ripped a piece of armor loose."));
            $brotherhoodLevel = CustomEnchantmentManager::getLevel($item, "brotherhood");
            BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "disarmor");
        }

        if (!$forcedProc && $session !== null && $cooldown > 0) {
            $session->addCooldown("disarmor", $cooldown);
        }
    }
}

