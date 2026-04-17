<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class HolyEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        $damager = $event->getDamager();
        if (!$damager instanceof Player) return;

        $chance = match($level) {
            1 => 12,
            2 => 16,
            3 => 22,
            4 => 36,
            5 => 45,
            default => 0
        };

        if (mt_rand(1, 100) <= $chance) {
            GlaciaUtils::bless($damager);
        }
    }
}