<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

final class VoteListener implements Listener{

	public function onJoin(PlayerJoinEvent $event) : void{
		$manager = Loader::getVoteManager();
		if(!$manager->isEnabled() || !$manager->shouldAutoClaimOnJoin()){
			return;
		}
		$manager->queueClaim($event->getPlayer(), false);
	}
}

