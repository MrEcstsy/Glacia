<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat\modules;

use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatProfile;
use ecstsy\Glacia\server\staff\anticheat\GuardDetectionModule;
use ecstsy\Glacia\server\staff\anticheat\GuardFlag;
use pocketmine\player\Player;

/** Multiple distinct victim entity hits in the same server tick. */
final class MultiAuraGuardModule implements GuardDetectionModule
{
    public function getName(): string
    {
        return "MultiAura";
    }

    public function configKey(): string
    {
        return "multi_aura";
    }

    public function check(AntiCheatManager $guard, Player $player, AntiCheatProfile $profile, array $ctx): ?GuardFlag
    {
        if (!$guard->isGuardModuleEnabled($this->configKey())) {
            return null;
        }

        $tick = (int) ($ctx["combat_tick"] ?? 0);
        if ($tick <= 0) {
            return null;
        }

        $hits = $profile->getHitsByCombatTick()[$tick] ?? [];
        $unique = array_values(array_unique($hits));
        if (count($unique) <= 1) {
            return null;
        }

        return new GuardFlag($this->getName(), 96.0, "multiple victim runtime ids in one tick", [
            "tick" => $tick,
            "victimRuntimeIds" => $unique,
        ]);
    }
}
