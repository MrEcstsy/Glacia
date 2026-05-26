<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat\modules;

use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatProfile;
use ecstsy\Glacia\server\staff\anticheat\GuardDetectionModule;
use ecstsy\Glacia\server\staff\anticheat\GuardFlag;
use pocketmine\player\Player;

/** Hits that land without a recent swing (common with some combat bots). Requires fingerprint warmup. */
final class NoSwingGuardModule implements GuardDetectionModule
{
    public function getName(): string
    {
        return "NoSwing";
    }

    public function configKey(): string
    {
        return "no_swing";
    }

    public function check(AntiCheatManager $guard, Player $player, AntiCheatProfile $profile, array $ctx): ?GuardFlag
    {
        if (!$guard->isGuardModuleEnabled($this->configKey())) {
            return null;
        }

        $now = microtime(true);
        if (($now - $profile->getJoinWallTime()) < 30.0) {
            return null;
        }

        if (($ctx["phase"] ?? "") !== "attack") {
            return null;
        }

        if (($now - $profile->getLastHitWallTime()) > 0.25) {
            return null;
        }

        if (($now - $profile->getLastSwingWallTime()) <= 0.25) {
            return null;
        }

        $stats = $profile->getFingerprintMetric("swing_hit_ratio");
        $normallySwings = $stats !== null && $stats["n"] >= 18 && ($stats["mean"] > 0.72);
        if (!$normallySwings) {
            return null;
        }

        return new GuardFlag($this->getName(), 90.0, "hit without recent swing (fingerprint baseline expects swings)", [
            "lastSwingAgo" => $now - $profile->getLastSwingWallTime(),
            "swingHitRatio" => $profile->swingHitRatio(),
        ]);
    }
}
