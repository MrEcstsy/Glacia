<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat;

use pocketmine\player\Player;

interface GuardDetectionModule
{
    public function getName(): string;

    /** Config / cooldown key (snake_case). */
    public function configKey(): string;

    /**
     * @param array<string, mixed> $ctx
     */
    public function check(AntiCheatManager $guard, Player $player, AntiCheatProfile $profile, array $ctx): ?GuardFlag;
}
