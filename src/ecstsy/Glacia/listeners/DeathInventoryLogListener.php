<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\lang\Translatable;

final class DeathInventoryLogListener implements Listener{

	public function onDeath(PlayerDeathEvent $event) : void{
		$player = $event->getPlayer();
		$cause = $event->getDeathMessage();
		$message = is_string($cause) ? $cause : $this->formatTranslatable($cause);
		Loader::getDeathInventoryLogManager()->logDeath($player, $message);
	}

	private function formatTranslatable(Translatable $message) : string{
		$params = array_map(static fn(mixed $value) : string => (string) $value, $message->getParameters());

		if ($params === []) {
			return $message->getText();
		}

		$formatted = @vsprintf($message->getText(), $params);
		return is_string($formatted) ? $formatted : $message->getText();
	}
}

