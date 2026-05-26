<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\mobs\Ghast;
use ecstsy\Glacia\entity\mobs\SnowGolem;
use ecstsy\Glacia\entity\watchdogs\ChunkWatchdogEntity;
use ecstsy\Glacia\server\watchdogs\ChunkWatchdogManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\Listener;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ChunkWatchdogListener implements Listener
{
    public function onInteract(PlayerInteractEvent $event): void
    {
        if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            return;
        }

        $item = $event->getItem();
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return;
        }

        $itemId = $glacia->getString("glaciaItem", "");
        $type = ChunkWatchdogManager::getTypeFromItemId($itemId);
        if ($type === null) {
            return;
        }

        $event->cancel();
        if (!ChunkWatchdogManager::place($event->getPlayer(), $event->getBlock()->getPosition(), $type)) {
            PlayerUtils::playSound($event->getPlayer(), "note.bass");
            return;
        }

        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE()) {
            $item->pop();
            $event->getPlayer()->getInventory()->setItemInHand($item);
        }
        PlayerUtils::playSound($event->getPlayer(), "random.levelup");
    }

    public function onWatchdogHit(EntityDamageByEntityEvent $event): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof ChunkWatchdogEntity) {
            return;
        }

        $event->cancel();
        $damager = $event->getDamager();
        if (!$damager instanceof Player) {
            return;
        }

        ChunkWatchdogManager::openManageForm($damager, $entity->getWatchdogId());
    }

    public function onWatchdogInteract(PlayerEntityInteractEvent $event): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof ChunkWatchdogEntity) {
            return;
        }

        $event->cancel();
        ChunkWatchdogManager::openManageForm($event->getPlayer(), $entity->getWatchdogId());
    }

    public function onEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();
        $position = $entity->getPosition();

        if ($entity instanceof SnowGolem && ChunkWatchdogManager::countInChunk($position, ChunkWatchdogManager::TYPE_FOX) > 0) {
            $drops = [];
            foreach ($event->getDrops() as $drop) {
                $drops[] = ChunkWatchdogManager::setDropSellBonus($drop, 1.05);
            }
            $event->setDrops($drops);
            return;
        }

        if ($entity instanceof Ghast && ChunkWatchdogManager::countInChunk($position, ChunkWatchdogManager::TYPE_GOAT) > 0) {
            $drops = [];
            foreach ($event->getDrops() as $drop) {
                $drops[] = ChunkWatchdogManager::setDropSellBonus($drop, 1.05);
            }
            $event->setDrops($drops);
        }
    }
}
