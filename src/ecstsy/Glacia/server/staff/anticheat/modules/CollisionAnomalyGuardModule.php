<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat\modules;

use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatProfile;
use ecstsy\Glacia\server\staff\anticheat\GuardDetectionModule;
use ecstsy\Glacia\server\staff\anticheat\GuardFlag;
use pocketmine\player\Player;

/**
 * Observes unusually dense collision overlap (possible phase/wall clipping).
 * Never teleports players — log-only unlike common AC phase modules.
 */
final class CollisionAnomalyGuardModule implements GuardDetectionModule
{
    public function getName(): string
    {
        return "CollisionAnomaly";
    }

    public function configKey(): string
    {
        return "collision_anomaly";
    }

    public function check(AntiCheatManager $guard, Player $player, AntiCheatProfile $profile, array $ctx): ?GuardFlag
    {
        if (!$guard->isGuardModuleEnabled($this->configKey())) {
            return null;
        }

        if (($ctx["phase"] ?? "") !== "movement") {
            return null;
        }

        $tick = (int) ($ctx["tick"] ?? 0);
        if ($tick % 10 !== 0) {
            return null;
        }

        $world = $player->getWorld();
        $bb = $player->getBoundingBox()->expandedCopy(-0.01, -0.01, -0.01);
        $boxes = $world->getCollisionBoxes($player, $bb);
        $n = count($boxes);

        if ($n === 0) {
            if ($player->isOnGround()) {
                $profile->setLastProbablySafeGround($player->getPosition());
            }

            return null;
        }

        if ($n < $guard->getGuardCollisionBoxThreshold()) {
            return null;
        }

        return new GuardFlag($this->getName(), 78.0, "dense AABB collisions ({$n} boxes)", [
            "boxes" => $n,
            "xuid" => $player->getXuid(),
            "pos" => [
                "x" => $player->getPosition()->getX(),
                "y" => $player->getPosition()->getY(),
                "z" => $player->getPosition()->getZ(),
            ],
        ]);
    }
}
