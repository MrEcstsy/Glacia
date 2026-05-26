<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\zones\ZoneMob;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDeathEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ExclusiveEnchantManager;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\player\Player;

final class GloryhunterEnchant implements EntityDeathEnchantmentHandler
{
    public static function handle(EntityDeathEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof ZoneMob) {
            return;
        }

        $cause = $entity->getLastDamageCause();
        if (!$cause instanceof EntityDamageByEntityEvent) {
            return;
        }

        $damager = $cause->getDamager();
        if (!$damager instanceof Player) {
            return;
        }

        $result = EnchantProcManager::attempt(
            $damager,
            "gloryhunter",
            $level,
            EnchantTuningManager::chance("gloryhunter", $level, 1.0),
            EnchantTuningManager::cooldownSeconds("gloryhunter", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        if (!ExclusiveEnchantManager::consumeHeldUse($damager, "gloryhunter")) {
            return;
        }

        $reward = EnchantTuningManager::getValue("gloryhunter", "reward", null);
        if (!is_array($reward)) {
            return;
        }

        $item = RewardResolver::resolveItem($reward, $damager);
        if ($item === null) {
            return;
        }

        $drops = $event->getDrops();
        $drops[] = $item;
        $event->setDrops($drops);
        PlayerUtils::playSound($damager, "random.orb", 0.8, 1.25);
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "reward_added" => true,
        ]);
    }
}
