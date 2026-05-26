<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\BlockBreakEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\block\Lava;
use pocketmine\event\block\BlockBreakEvent;

final class DetonateEnchant implements BlockBreakEnchantmentHandler
{
    /** @var array<string, int> */
    private static array $cooldowns = [];

    public static function handle(BlockBreakEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $player = $event->getPlayer();
        $key = strtolower($player->getName());
        $chance = EnchantTuningManager::chance("detonate", $level, 5.0 * $level);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("detonate", $level, 0);
        $result = EnchantProcManager::attempt(
            $player,
            "detonate",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => max(0, (self::$cooldowns[$key] ?? 0) - time())
        );
        if (!$result->success) {
            return;
        }

        $radius = max(0, EnchantTuningManager::valueInt("detonate", "radius", $level, 1));
        $world = $event->getBlock()->getPosition()->getWorld();
        $center = $event->getBlock()->getPosition();
        $broken = 0;
        for ($x = -$radius; $x <= $radius; $x++) {
            for ($y = -$radius; $y <= $radius; $y++) {
                for ($z = -$radius; $z <= $radius; $z++) {
                    $pos = $center->add($x, $y, $z);
                    $block = $world->getBlock($pos);
                    if ($block instanceof Lava) {
                        continue;
                    }
                    if ($block->isSolid()) {
                        $world->useBreakOn($pos, $player->getInventory()->getItemInHand(), $player);
                        $broken++;
                    }
                }
            }
        }

        if ($cooldownSeconds > 0) {
            self::$cooldowns[$key] = time() + $cooldownSeconds;
        }
        EnchantProcManager::debugAppliedValues($player, $result, [
            "radius" => $radius,
            "broken_blocks" => $broken,
        ]);
    }
}
