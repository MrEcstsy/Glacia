<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class HolyEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        if (!$damager instanceof Player) return;

        $chance = EnchantTuningManager::chance("holy", $level, 12.0);
        $result = EnchantProcManager::attempt($damager, "holy", $level, $chance);
        if (!$result->success) {
            return;
        }

        GlaciaUtils::bless($damager);
        EnchantProcManager::debugAppliedValues($damager, $result, ["blessed" => true]);
    }
}
