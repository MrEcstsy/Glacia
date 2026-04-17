<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\economy;

use ecstsy\Glacia\player\PlayerManager;
use pocketmine\player\Player;

final class EconomyManager {

    public static function has(Player $player, float $amount): bool {
        $session = PlayerManager::getInstance()->getSession($player);
        return $session !== null && $session->getBalance() >= max(0.0, $amount);
    }

    public static function take(Player $player, float $amount): bool {
        $session = PlayerManager::getInstance()->getSession($player);
        $amount = max(0.0, round($amount, 2));

        if ($session === null || $session->getBalance() < $amount) {
            return false;
        }

        $session->removeBalance($amount);
        return true;
    }
}
