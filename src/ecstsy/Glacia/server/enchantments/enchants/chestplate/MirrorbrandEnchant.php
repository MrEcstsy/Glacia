<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\MirrorbrandProcManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class MirrorbrandEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $damager = $event->getDamager();
        if (!$victim instanceof Player || !$damager instanceof Player) {
            return;
        }

        $latestProc = MirrorbrandProcManager::getLatest($damager);
        if ($latestProc === null) {
            return;
        }

        $chance = EnchantTuningManager::chance("mirrorbrand", $level, (float) max(0, $level));
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("mirrorbrand", $level, 0);
        $result = EnchantProcManager::attempt($victim, "mirrorbrand", $level, $chance, $cooldownSeconds);
        if (!$result->success) {
            return;
        }

        $reflected = ($latestProc["callback"])($victim, $damager);
        if (!$reflected) {
            return;
        }

        $victim->sendMessage(C::colorize("&r&6Mirrorbrand &7reflected &f" . $latestProc["enchantId"] . " &7back at your attacker."));
        $damager->sendMessage(C::colorize("&r&6Mirrorbrand &7reflected your &f" . $latestProc["enchantId"] . " &7proc."));
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "reflected_enchant" => $latestProc["enchantId"],
        ]);
    }
}
