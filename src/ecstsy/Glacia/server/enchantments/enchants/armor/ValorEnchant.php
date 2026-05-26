<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Sword;
use pocketmine\player\Player;

final class ValorEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        if ($event->getBaseDamage() <= 0) {
            return;
        }

        $held = $victim->getInventory()->getItemInHand();
        if (!$held instanceof Sword) {
            return;
        }

        $chance = EnchantTuningManager::chance("valor", $level, 4.0);
        $result = EnchantProcManager::attempt($victim, "valor", $level, $chance);
        if (!$result->success) {
            return;
        }

        $reductionPercent = max(0.0, EnchantTuningManager::value("valor", "damage-reduction-percent", $level, 4.5));

        $base = $event->getBaseDamage();
        $new = $base * (1 - ($reductionPercent / 100));
        $event->setBaseDamage($new);
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "base_damage" => $base,
            "reduction_percent" => $reductionPercent,
            "final_damage" => $new,
        ]);
    }
}
