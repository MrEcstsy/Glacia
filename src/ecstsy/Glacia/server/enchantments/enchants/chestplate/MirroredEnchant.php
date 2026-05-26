<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class MirroredEnchant implements EntityDamageByEntityEnchantmentHandler
{
    /** @var array<string, int> */
    private static array $cooldowns = [];

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

        $key = strtolower($victim->getName());
        $chance = EnchantTuningManager::chance("mirrored", $level, 7.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("mirrored", $level, 5);
        $result = EnchantProcManager::attempt(
            $victim,
            "mirrored",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => max(0, (self::$cooldowns[$key] ?? 0) - time())
        );
        if (!$result->success) {
            return;
        }

        $reflectPercent = max(0.0, EnchantTuningManager::value("mirrored", "reflect-percent", $level, 28.0));
        $minimumDamage = max(0.0, EnchantTuningManager::value("mirrored", "minimum-reflect-damage", $level, 0.5));
        $damageSource = strtolower(trim((string) EnchantTuningManager::getValue("mirrored", "reflect-damage-source", "original-base")));
        $sourceDamage = match ($damageSource) {
            "final" => $event->getFinalDamage(),
            "base" => $event->getBaseDamage(),
            default => $event->getOriginalBaseDamage(),
        };
        $reflected = max($minimumDamage, $sourceDamage * ($reflectPercent / 100.0));
        $damager->attack(new EntityDamageEvent($damager, EntityDamageEvent::CAUSE_MAGIC, $reflected));

        self::$cooldowns[$key] = time() + $cooldownSeconds;
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "damage_source" => $damageSource,
            "source_damage" => $sourceDamage,
            "reflect_percent" => $reflectPercent,
            "minimum_reflect_damage" => $minimumDamage,
            "reflected_damage" => $reflected,
        ]);
    }
}
