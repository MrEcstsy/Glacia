<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\blackmarket\BlackMarketAuctionManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDeathEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class MuravievaEnchant implements EntityDeathEnchantmentHandler
{
    /** @var array<string, int> */
    private static array $x5LootUntil = [];

    public static function handle(EntityDeathEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $entity = $event->getEntity();
        $cause = $entity->getLastDamageCause();
        if (!$cause instanceof EntityDamageByEntityEvent) {
            return;
        }
        $killer = $cause->getDamager();
        if (!$killer instanceof Player) {
            return;
        }

        if ((self::$x5LootUntil[$killer->getUniqueId()->toString()] ?? 0) > time()) {
            $drops = $event->getDrops();
            $extra = [];
            foreach ($drops as $drop) {
                for ($i = 0; $i < 4; $i++) {
                    $extra[] = clone $drop;
                }
            }
            $event->setDrops(array_merge($drops, $extra));
            EnchantDebugManager::logPassiveValues($killer, "blackhand", $level, [
                "x5_loot_active" => true,
                "drop_count" => count($event->getDrops()),
            ]);
        }

        $result = EnchantProcManager::attempt(
            $killer,
            "blackhand",
            $level,
            EnchantTuningManager::chance("blackhand", $level, 3.0),
            EnchantTuningManager::cooldownSeconds("blackhand", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        $grantedItem = false;
        $pool = BlackMarketAuctionManager::getInstance()->getPoolEntries($killer);
        if ($pool !== []) {
            $pick = $pool[array_rand($pool)];
            $item = $pick["item"] ?? null;
            if ($item instanceof Item) {
                foreach ($killer->getInventory()->addItem(clone $item) as $leftover) {
                    $killer->getWorld()->dropItem($killer->getPosition(), $leftover);
                }
                $grantedItem = true;
            }
        }

        $duration = max(1, EnchantTuningManager::valueInt("blackhand", "x5-loot-duration-seconds", $level, 30));
        self::$x5LootUntil[$killer->getUniqueId()->toString()] = time() + $duration;
        EnchantProcManager::debugAppliedValues($killer, $result, [
            "granted_item" => $grantedItem,
            "x5_loot_duration_seconds" => $duration,
        ]);
    }
}
