<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\items\rings\RingManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class LifestealEnchant implements EntityDamageByEntityEnchantmentHandler {
    /** @var array<string, int> */
    private static array $cooldowns = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        if (!$damager instanceof Living) {
            return;
        }

        $chance = EnchantTuningManager::chance("lifesteal", $level, 1.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("lifesteal", $level, 0);
        $cooldownKey = spl_object_id($damager) . ":lifesteal";
        $result = EnchantProcManager::attempt(
            $damager,
            "lifesteal",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => max(0, (self::$cooldowns[$cooldownKey] ?? 0) - time()),
            1,
            1.0
        );
        if (!$result->success) {
            return;
        }

        $heal = EnchantTuningManager::value("lifesteal", "heal", $level, 1.0);

        foreach (EnchantTuningManager::getLevelEffects("lifesteal", $level) as $effect) {
            if (strtoupper(trim((string) ($effect["type"] ?? ""))) !== "STEAL_HEALTH") {
                continue;
            }

            $heal = EnchantTuningManager::rollNumericRange($effect["amount"] ?? null, $heal);
            break;
        }

        $victim = $event->getEntity();
        if ($victim instanceof Player) {
            if (RingManager::playerBlocksEnchant($victim, "lifesteal")) {
                EnchantProcManager::debugAppliedValues($damager, $result, ["blocked" => "ring"]);
                return;
            }
            $heal *= max(0.0, 1.0 - (RuneManager::getLifestealMitigationPercent($victim) / 100));
        }

        $damage = max(0.0, EnchantTuningManager::value("lifesteal", "damage", $level, 1.0));
        if ($damage > 0.0) {
            $event->setBaseDamage($event->getBaseDamage() + $damage);
        }

        $heal = max(0.0, $heal);

        EnchantProcManager::applyCooldownIfNeeded(
            $result,
            static fn(int $seconds) => self::$cooldowns[$cooldownKey] = time() + $seconds
        );
        EnchantProcManager::debugAppliedValues($damager, $result, ["heal" => $heal, "damage" => $damage]);

        foreach (EnchantTuningManager::getLevelEffects("lifesteal", $level) as $effect) {
            if (strtoupper(trim((string) ($effect["type"] ?? ""))) !== "MESSAGE") {
                continue;
            }

            $text = (string) ($effect["text"] ?? "");
            if ($text === "") {
                continue;
            }

            $target = strtolower(trim((string) ($effect["target"] ?? "attacker")));
            $resolved = str_replace("{victim}", $victim instanceof Player ? $victim->getName() : "your target", $text);
            if ($target === "attacker" && $damager instanceof Player) {
                $damager->sendMessage($resolved);
            }
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($damager, $heal, $result): void {
                if (!$damager->isAlive()) {
                    return;
                }
                if ($damager instanceof Player && AdvancedEnchantState::isAnatomyBlocked($damager)) {
                    EnchantProcManager::debugAppliedValues($damager, $result, ["blocked" => "anatomy-delay"]);
                    return;
                }

                $damager->setHealth(min($damager->getMaxHealth(), $damager->getHealth() + $heal));
            }
        ), 1);
    }
}
