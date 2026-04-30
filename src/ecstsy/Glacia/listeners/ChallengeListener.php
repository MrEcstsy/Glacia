<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\TextFormat as C;

final class ChallengeListener implements Listener {

    public function onJoin(PlayerJoinEvent $event): void
    {
        $manager = Loader::getChallengeManager();
        $manager->tick();
        $claimed = $manager->claimPendingRewards($event->getPlayer());
        if ($claimed > 0) {
            $event->getPlayer()->sendMessage(C::colorize("&r&aYour pending daily challenge rewards were delivered (&f{$claimed}&a)."));
        }
    }
}
