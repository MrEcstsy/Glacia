<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat\modules;

use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatProfile;
use ecstsy\Glacia\server\staff\anticheat\GuardDetectionModule;
use ecstsy\Glacia\server\staff\anticheat\GuardFlag;
use pocketmine\player\Player;

/** Large yaw step near a hit, deviating from learned baseline. */
final class KillAuraGuardModule implements GuardDetectionModule
{
    public function getName(): string
    {
        return "KillAuraHeuristic";
    }

    public function configKey(): string
    {
        return "kill_aura";
    }

    public function check(AntiCheatManager $guard, Player $player, AntiCheatProfile $profile, array $ctx): ?GuardFlag
    {
        if (!$guard->isGuardModuleEnabled($this->configKey())) {
            return null;
        }

        if (($ctx["phase"] ?? "") !== "attack") {
            return null;
        }

        $engine = $guard->getFingerprintEngine();
        if ($engine === null) {
            return null;
        }

        if ((microtime(true) - $profile->getLastHitWallTime()) > 0.25) {
            return null;
        }

        if ($profile->getLastYawDelta() <= 60.0) {
            return null;
        }

        if (!$engine->isAnomalous($profile, "yaw_delta", $profile->getLastYawDelta())) {
            return null;
        }

        return new GuardFlag($this->getName(), 93.0, "large yaw step near hit (fingerprint outlier)", [
            "yawDelta" => $profile->getLastYawDelta(),
            "lastVictimRuntimeId" => $profile->getLastHitVictimEntityRuntimeId(),
        ]);
    }
}
