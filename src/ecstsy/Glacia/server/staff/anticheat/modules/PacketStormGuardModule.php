<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat\modules;

use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatProfile;
use ecstsy\Glacia\server\staff\anticheat\GuardDetectionModule;
use ecstsy\Glacia\server\staff\anticheat\GuardFlag;
use pocketmine\player\Player;

/** High inbound packet rate (often misbehaving clients); log-only. */
final class PacketStormGuardModule implements GuardDetectionModule
{
    public function getName(): string
    {
        return "PacketStorm";
    }

    public function configKey(): string
    {
        return "packet_storm";
    }

    public function check(AntiCheatManager $guard, Player $player, AntiCheatProfile $profile, array $ctx): ?GuardFlag
    {
        if (($ctx["phase"] ?? "") !== "packet") {
            return null;
        }

        if (!$guard->isGuardModuleEnabled($this->configKey())) {
            return null;
        }

        $rate = $profile->getPacketRatePerSecond();
        $max = $guard->getGuardPacketStormMaxPerSecond();
        if ($rate <= $max) {
            return null;
        }

        return new GuardFlag($this->getName(), 92.0, "inbound packet rate {$rate}/s (max {$max})", [
            "packetRate" => $rate,
            "max" => $max,
        ]);
    }
}
