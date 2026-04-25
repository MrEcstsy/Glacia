<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\utils\TextFormat as C;

final class Announcer {

    private static int $index = 0;
    private static int $elapsed = 0;

    public static function tick(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "announcer.yml");
        $messages = $config?->get("messages", []);
        if (!is_array($messages) || $messages === []) {
            return;
        }

        self::$elapsed++;
        $interval = max(30, (int) ($config?->get("interval-seconds", 180) ?? 180));
        if (self::$elapsed < $interval) {
            return;
        }

        self::$elapsed = 0;
        $message = (string) $messages[self::$index % count($messages)];
        self::$index++;

        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $player) {
            $session = Loader::getPlayerManager()->getSession($player);
            if ((bool) ($session?->getSetting("broadcasts") ?? true)) {
                $player->sendMessage(C::colorize($message));
            }
        }
    }
}
