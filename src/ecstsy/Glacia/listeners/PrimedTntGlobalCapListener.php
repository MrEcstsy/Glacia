<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\cannon\CannonRulesConfig;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;

/**
 * Server-wide primed-TNT limit from {@see CannonRulesConfig}.
 * @priority MONITOR so it runs after {@see PrimedTntUpgradeListener} swaps vanilla primed → Glacia.
 */
final class PrimedTntGlobalCapListener implements Listener {

    private static int $primedCount = 0;
    /** @var array<int, true> */
    private static array $countedPrimedIds = [];

    /**
     * @priority MONITOR
     */
    public function onEntitySpawn(EntitySpawnEvent $event): void {
        if (!CannonRulesConfig::enabled()) {
            return;
        }
        $e = $event->getEntity();
        if (!$e instanceof PrimedTNT || $e->isFlaggedForDespawn()) {
            return;
        }
        if (isset(self::$countedPrimedIds[$e->getId()])) {
            return;
        }
        if (self::$primedCount >= CannonRulesConfig::getMaxPrimedTnt()) {
            $e->flagForDespawn();

            return;
        }
        self::$countedPrimedIds[$e->getId()] = true;
        self::$primedCount++;
    }

    /**
     * @priority MONITOR
     */
    public function onEntityDespawn(EntityDespawnEvent $event): void {
        if (!CannonRulesConfig::enabled()) {
            return;
        }
        $e = $event->getEntity();
        if (!$e instanceof PrimedTNT) {
            return;
        }
        if (!isset(self::$countedPrimedIds[$e->getId()])) {
            return;
        }
        unset(self::$countedPrimedIds[$e->getId()]);
        self::$primedCount = max(0, self::$primedCount - 1);
    }
}
