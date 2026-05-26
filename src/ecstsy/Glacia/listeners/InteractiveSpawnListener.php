<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\InteractiveSpawnEntity;
use ecstsy\Glacia\server\spawn\InteractiveSpawnAction;
use ecstsy\Glacia\server\spawn\InteractiveSpawnRemoveMode;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class InteractiveSpawnListener implements Listener
{
    /** @var array<string, float> */
    private array $cooldowns = [];

    /**
     * @priority HIGHEST
     * @handleCancelled
     */
    public function onInteractiveEntityHit(EntityDamageByEntityEvent $event): void
    {
        $entity = $event->getEntity();
        $damager = $event->getDamager();
        if (!$entity instanceof InteractiveSpawnEntity || !$damager instanceof Player) {
            return;
        }

        $event->cancel();
        $this->handleInteraction($damager, $entity);
    }

    /**
     * @priority HIGHEST
     * @handleCancelled
     */
    public function onInteractiveEntityInteract(PlayerEntityInteractEvent $event): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof InteractiveSpawnEntity) {
            return;
        }

        $event->cancel();
        $this->handleInteraction($event->getPlayer(), $entity);
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        InteractiveSpawnRemoveMode::onQuit($event->getPlayer()->getUniqueId());
    }

    private function handleInteraction(Player $player, InteractiveSpawnEntity $entity): void
    {
        if (InteractiveSpawnRemoveMode::isActive($player)) {
            $label = $entity->getDisplayLabel();
            if ($entity instanceof Entity) {
                $entity->flagForDespawn();
            }
            $player->sendMessage(C::colorize("&r&aRemoved interactive spawn &f{$label}&a."));
            return;
        }

        $key = $entity->getId() . ":" . $player->getUniqueId()->toString();
        $now = microtime(true);
        if (($this->cooldowns[$key] ?? 0.0) > $now) {
            return;
        }

        $this->cooldowns[$key] = $now + 0.35;
        InteractiveSpawnAction::execute($player, $entity->getInteractAction());
    }
}
