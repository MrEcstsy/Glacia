<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\GlaciaExplosiveCreeper;
use ecstsy\Glacia\server\factions\contribution\FactionContributionManager;
use ecstsy\Glacia\server\levels\PlayerLevelManager;
use ecstsy\Glacia\Loader;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;

final class LevelListener implements Listener {

    public function onEntityDeath(EntityDeathEvent $event) : void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof Living || $entity instanceof Player) {
            return;
        }

        $lastDamage = $entity->getLastDamageCause();
        if (!$lastDamage instanceof EntityDamageByEntityEvent) {
            return;
        }

        $damager = $lastDamage->getDamager();
        if (!$damager instanceof Player) {
            return;
        }

        $xp = PlayerLevelManager::getMobXpFor($entity);
        if ($xp <= 0) {
            return;
        }

        $xpMult = FactionContributionManager::getBuffMultiplierForPlayer($damager, "xp-gain-multiplier", 1.0);
        $xpMult *= Loader::getCaptureManager()->getOutpostTypeMultiplierForPlayer($damager, "experience", 1.0);
        $finalXp = max(1, (int) ceil($xp * $xpMult));
        PlayerLevelManager::addXp($damager, $finalXp, "mob_kill");

        if (!$entity instanceof GlaciaExplosiveCreeper) {
            FactionContributionManager::addMobKillContribution($damager, "default");
        }
    }
}
