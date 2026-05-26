<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\mobs\GlaciaLightningStrike;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\MirrorbrandProcManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class StormlashEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Living || !$victim instanceof Living) {
            return;
        }
        $session = $damager instanceof Player ? Loader::getPlayerManager()->getSession($damager) : null;
        $chance = EnchantTuningManager::chance("stormlash", $level, 1.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("stormlash", $level, 4);
        $result = EnchantProcManager::attempt(
            $damager,
            "stormlash",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session?->getCooldown("stormlash") ?? 0,
            1,
            6.0
        );
        if (!$result->success) {
            return;
        }

        if ($victim instanceof Player && RuneManager::hasStormcallerWard($victim)) {
            EnchantProcManager::debugAppliedValues($damager, $result, ["blocked" => "stormcaller-ward"]);
            return;
        }

        $damage = max(0.0, EnchantTuningManager::value("stormlash", "damage", $level, 2.0));
        if ($damage > 0.0) {
            $event->setBaseDamage($event->getBaseDamage() + $damage);
        }
        if ($damager instanceof Player && $victim instanceof Player && $damage > 0.0) {
            MirrorbrandProcManager::record($damager, "stormlash", static function (Player $reflector, Player $attacker) use ($damage): bool {
                $attacker->setHealth(max(0.0, $attacker->getHealth() - $damage));
                return true;
            });
        }
        EnchantProcManager::debugAppliedValues($damager, $result, ["damage" => $damage]);

        $position = $victim->getPosition();
        $lightning = new GlaciaLightningStrike(new Location(
            $position->x + 0.5,
            $position->y,
            $position->z + 0.5,
            $victim->getWorld(),
            0.0,
            0.0
        ));
        $lightning->spawnToAll();

        foreach ($victim->getWorld()->getPlayers() as $viewer) {
            PlayerUtils::playSound($viewer, "ambient.weather.thunder", 1.0, 1.0);
            PlayerUtils::playSound($viewer, "ambient.weather.lightning.impact", 1.0, 1.0);
        }

        EnchantProcManager::applyCooldownIfNeeded($result, static fn(int $seconds) => $session?->addCooldown("stormlash", $seconds));
    }
}
