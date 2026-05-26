<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\SetTimePacket;

/**
 * PocketMine applies {@see \pocketmine\world\World::sendTime} after plugin scheduler passes, so a repeating
 * personal-time task cannot win on the same tick. Intercept {@see SetTimePacket} and substitute locked ptime.
 */
final class PersonalTimeNetworkListener implements Listener {

    public function onDataPacketSend(DataPacketSendEvent $event): void {
        $packets = $event->getPackets();
        if (count($packets) !== 1 || !$packets[0] instanceof SetTimePacket) {
            return;
        }

        /** @var SetTimePacket $original */
        $original = $packets[0];
        $targets = $event->getTargets();

        if (count($targets) === 1) {
            $player = $targets[0]->getPlayer();
            if ($player === null) {
                return;
            }
            $preset = GlaciaUtils::getLockedPresetTimeTicks($player);
            if ($preset !== null) {
                $event->setPackets([SetTimePacket::create($preset)]);
            }
            return;
        }

        $anyPreset = false;
        foreach ($targets as $session) {
            $player = $session->getPlayer();
            if ($player !== null && GlaciaUtils::getLockedPresetTimeTicks($player) !== null) {
                $anyPreset = true;
                break;
            }
        }
        if (!$anyPreset) {
            return;
        }

        $event->cancel();
        foreach ($targets as $session) {
            $player = $session->getPlayer();
            $ticks = $player !== null ? GlaciaUtils::getLockedPresetTimeTicks($player) : null;
            $session->syncWorldTime($ticks ?? $original->time);
        }
    }
}
