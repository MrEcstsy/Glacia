<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat\modules;

use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatProfile;
use ecstsy\Glacia\server\staff\anticheat\GuardDetectionModule;
use ecstsy\Glacia\server\staff\anticheat\GuardFlag;
use pocketmine\player\Player;

/** Unnaturally smooth yaw jerk profile vs learned baseline. */
final class AimAssistGuardModule implements GuardDetectionModule
{
    public function getName(): string
    {
        return "AimAssistHeuristic";
    }

    public function configKey(): string
    {
        return "aim_assist";
    }

    public function check(AntiCheatManager $guard, Player $player, AntiCheatProfile $profile, array $ctx): ?GuardFlag
    {
        if (($ctx["phase"] ?? "") !== "movement") {
            return null;
        }

        if (!$guard->isGuardModuleEnabled($this->configKey())) {
            return null;
        }

        $engine = $guard->getFingerprintEngine();
        if ($engine === null) {
            return null;
        }

        $jerks = $profile->getYawJerks();
        if (count($jerks) < 40) {
            return null;
        }

        $variance = AntiCheatProfile::variance($jerks);
        $engine->record($profile, "yaw_jerk_variance", $variance);

        if ($variance >= 0.08) {
            return null;
        }

        if (!$engine->isAnomalous($profile, "yaw_jerk_variance", $variance)) {
            return null;
        }

        return new GuardFlag($this->getName(), 88.0, "low yaw jerk variance vs baseline", [
            "jerkVariance" => $variance,
        ]);
    }
}
