<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks;

use ecstsy\Glacia\Loader;
use pocketmine\scheduler\Task;

final class PowerRegenTask extends Task {

    private const PLAYER_MAX_POWER = 100;
    private const REGEN_AMOUNT = 1;

    public function onRun(): void {
        foreach (Loader::getPlayerManager()->getSessions() as $session) {
            if (!$session->isConnected()) {
                continue;
            }
            if ($session->getFactionId() === null) {
                continue;
            }

            $cur = (int)$session->getPower();

            if ($cur >= self::PLAYER_MAX_POWER) {
                continue;
            }

            $session->setPower(min(self::PLAYER_MAX_POWER, $cur + self::REGEN_AMOUNT));
        }
    }
}
