<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\bow;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ExclusiveEnchantManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use ecstsy\Glacia\server\enchantments\enchants\sword\DisarmorEnchant;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TriggerhappyEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        $projectile = $damager instanceof Projectile
            ? $damager
            : (($event instanceof EntityDamageByChildEntityEvent && $event->getChild() instanceof Projectile) ? $event->getChild() : null);
        if (!$projectile instanceof Projectile || !$victim instanceof Player) {
            return;
        }

        $owner = $projectile->getOwningEntity();
        if (!$owner instanceof Player) {
            return;
        }

        $active = AdvancedEnchantState::isTriggerhappyActive($owner);
        if (!$active) {
            $session = PlayerManager::getInstance()->getSession($owner);
            $cooldown = max(0, EnchantTuningManager::getInt("triggerhappy", "cooldown", 18));
            if ($session !== null && $cooldown > 0 && $session->isOnCooldown("triggerhappy")) {
                return;
            }

            $chance = EnchantTuningManager::getLevelInt("triggerhappy", "chance-by-level", $level, 2 + ($level * 2));
            if (!ProcLuckManager::roll($owner, "triggerhappy", $chance, 1, 6)) {
                return;
            }

            if (!ExclusiveEnchantManager::consumeHeldUse($owner, "triggerhappy")) {
                return;
            }

            $fallbackSeconds = max(1, EnchantTuningManager::getInt("triggerhappy", "duration-seconds-per-level", 3) * $level);
            $seconds = max(1, EnchantTuningManager::getLevelInt("triggerhappy", "duration-seconds-by-level", $level, $fallbackSeconds));
            AdvancedEnchantState::setTriggerhappy($owner, $seconds);
            $session?->addCooldown("triggerhappy", $cooldown);
            $owner->sendMessage(C::colorize("&r&cTriggerhappy &7ignited your bow frenzy."));
            $active = true;
        }

        if (!$active) {
            return;
        }

        self::applyAreaDamage($owner, $victim, $event);

        $disarmorChance = max(0, EnchantTuningManager::getInt("triggerhappy", "disarmor-chance", 20));
        if (mt_rand(1, 100) <= $disarmorChance) {
            DisarmorEnchant::forceDisarmor($owner, $victim);
        }
    }

    private static function applyAreaDamage(Player $owner, Player $victim, EntityDamageByEntityEvent $event): void
    {
        $radius = max(1.0, EnchantTuningManager::getFloat("triggerhappy", "aoe-radius", 5.0));
        $damageMultiplier = max(0.1, EnchantTuningManager::getFloat("triggerhappy", "aoe-damage-multiplier", 0.75));
        $damage = max(1.0, $event->getFinalDamage() * $damageMultiplier);
        $center = $victim->getPosition();

        $bb = new AxisAlignedBB(
            $center->x - $radius,
            $center->y - $radius,
            $center->z - $radius,
            $center->x + $radius,
            $center->y + $radius,
            $center->z + $radius
        );

        foreach ($victim->getWorld()->getNearbyEntities($bb) as $entity) {
            if (!$entity instanceof Player || !$entity->isAlive()) {
                continue;
            }
            if ($entity->getId() === $owner->getId() || $entity->getId() === $victim->getId()) {
                continue;
            }
            if (GlaciaUtils::isAlly($owner, $entity)) {
                continue;
            }
            if ($entity->getPosition()->distanceSquared($center) > ($radius * $radius)) {
                continue;
            }

            $entity->attack(new EntityDamageByEntityEvent(
                $owner,
                $entity,
                EntityDamageEvent::CAUSE_PROJECTILE,
                $damage
            ));
        }
    }
}
