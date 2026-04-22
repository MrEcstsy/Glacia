<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\worldborder\WorldBorderManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class WorldBorderListener implements Listener {

    /** @var array<string, int> */
    private array $messageCooldowns = [];

    public function onPlayerMove(PlayerMoveEvent $event) : void
    {
        if (!WorldBorderManager::isEnabled()) {
            return;
        }

        $player = $event->getPlayer();
        $to = $event->getTo();
        if ($to === null || WorldBorderManager::isInside($to)) {
            return;
        }

        $event->cancel();
        $this->notify($player);
    }

    public function onTeleport(EntityTeleportEvent $event) : void
    {
        if (!WorldBorderManager::isEnabled()) {
            return;
        }

        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }

        $to = $event->getTo();
        if (WorldBorderManager::isInside($to)) {
            return;
        }

        $event->cancel();
        $this->notify($entity);
    }

    public function onBlockPlace(BlockPlaceEvent $event) : void
    {
        if (!WorldBorderManager::isEnabled()) {
            return;
        }

        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            if (!WorldBorderManager::isInside($block->getPosition())) {
                $event->cancel();
                $this->notify($event->getPlayer());
                return;
            }
        }
    }

    private function notify(Player $player) : void
    {
        $cooldown = WorldBorderManager::getMessageCooldown();
        $uuid = $player->getUniqueId()->toString();
        $now = time();
        if (($this->messageCooldowns[$uuid] ?? 0) > $now) {
            return;
        }

        $this->messageCooldowns[$uuid] = $now + $cooldown;
        $player->sendActionBarMessage(C::colorize("&r&cYou have reached the world border."));
        PlayerUtils::playSound($player, "note.bass");
    }
}
