<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\blackmarket\BlackMarketAuctionManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDeathEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\player\Player;

final class MuravievaEnchant implements EntityDeathEnchantmentHandler
{
    /** @var array<string, int> */
    private static array $x5LootUntil = [];

    public static function handle(EntityDeathEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $entity = $event->getEntity();
        $cause = $entity->getLastDamageCause();
        if (!$cause instanceof EntityDamageByEntityEvent) return;
        $killer = $cause->getDamager();
        if (!$killer instanceof Player) return;

        if ((self::$x5LootUntil[$killer->getUniqueId()->toString()] ?? 0) > time()) {
            $drops = $event->getDrops();
            $extra = [];
            foreach ($drops as $drop) {
                for ($i = 0; $i < 4; $i++) {
                    $extra[] = clone $drop;
                }
            }
            $event->setDrops(array_merge($drops, $extra));
        }

        $chance = EnchantTuningManager::getLevelInt("muravieva", "chance-by-level", $level, $level * 3);
        if (!ProcLuckManager::roll($killer, "muravieva", $chance, 1, 8)) return;

        $pool = BlackMarketAuctionManager::getInstance()->getPoolEntries($killer);
        if ($pool !== []) {
            $pick = $pool[array_rand($pool)];
            $item = $pick["item"] ?? null;
            if ($item instanceof \pocketmine\item\Item) {
                foreach ($killer->getInventory()->addItem(clone $item) as $leftover) {
                    $killer->getWorld()->dropItem($killer->getPosition(), $leftover);
                }
            }
        }

        $duration = max(1, EnchantTuningManager::getInt("muravieva", "x5-loot-duration-seconds", 30));
        self::$x5LootUntil[$killer->getUniqueId()->toString()] = time() + $duration;
    }
}
