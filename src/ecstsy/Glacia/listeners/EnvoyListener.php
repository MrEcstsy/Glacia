<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use pocketmine\block\Chest;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\Listener;

final class EnvoyListener implements Listener
{
    public function onInteract(PlayerInteractEvent $event): void
    {
        $block = $event->getBlock();
        if (!$block instanceof Chest) {
            return;
        }

        $data = Loader::getBlockData()->get($block);
        if (!is_array($data) || ($data["type"] ?? "") !== "envoy") {
            return;
        }

        $event->cancel();
        Loader::getEnvoyManager()->claimChest($event->getPlayer(), $block->getPosition());
    }

    public function onBreak(BlockBreakEvent $event): void
    {
        $block = $event->getBlock();
        if (!$block instanceof Chest) {
            return;
        }

        $data = Loader::getBlockData()->get($block);
        if (!is_array($data) || ($data["type"] ?? "") !== "envoy") {
            return;
        }

        $event->cancel();
        Loader::getEnvoyManager()->claimChest($event->getPlayer(), $block->getPosition());
    }
}
