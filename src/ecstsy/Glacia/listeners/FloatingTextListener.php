<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\FloatingTextEntity;
use ecstsy\Glacia\server\floatingtext\FloatingTextManager;
use ecstsy\Glacia\server\floatingtext\FloatingTextRemoveMode;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FloatingTextListener implements Listener
{
    public function onFloatingTextHit(EntityDamageByEntityEvent $event): void
    {
        $entity = $event->getEntity();
        $damager = $event->getDamager();
        if (!$entity instanceof FloatingTextEntity || !$damager instanceof Player) {
            return;
        }

        if (!FloatingTextRemoveMode::isActive($damager)) {
            return;
        }

        $event->cancel();
        $instanceId = FloatingTextManager::getInstanceIdByEntity($entity);
        if ($instanceId === null) {
            $damager->sendMessage(C::colorize("&r&cThat floating text is not managed by the floating text config."));
            return;
        }

        FloatingTextRemoveMode::disable($damager);
        if (!FloatingTextManager::removeInstance($instanceId)) {
            $damager->sendMessage(C::colorize("&r&cFailed to remove floating text &f{$instanceId}&c."));
            return;
        }

        $damager->sendMessage(C::colorize("&r&aRemoved floating text &f{$instanceId}&a."));
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        FloatingTextRemoveMode::onQuit($event->getPlayer()->getUniqueId());
    }
}
