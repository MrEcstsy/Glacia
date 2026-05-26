<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\commands\CommandRateLimiter;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\event\Listener;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

/**
 * @priority HIGH
 */
final class CommandRateLimitListener implements Listener {

    /**
     * PM5+: player chat commands dispatch via Server::dispatchCommand → CommandEvent
     * (PlayerCommandPreprocessEvent is no longer emitted for ordinary /commands).
     */
    public function onCommand(CommandEvent $event): void
    {
        $sender = $event->getSender();
        if (!$sender instanceof Player) {
            return;
        }

        $remainingMs = CommandRateLimiter::check($sender, $event->getCommand());
        if ($remainingMs === null) {
            return;
        }

        $event->cancel();
        $sender->sendMessage(C::colorize(GlaciaUtils::format(
            "player.command.rate_limit",
            ["wait" => CommandRateLimiter::formatRemaining($remainingMs)],
            "error"
        )));
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        CommandRateLimiter::forgetPlayer($event->getPlayer());
    }
}
