<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\tebex;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\tasks\tebex\TebexAcknowledgeTask;
use ecstsy\Glacia\tasks\tebex\TebexPollTask;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class TebexManager{

	private bool $enabled = false;
	private string $secret = "";
	private int $pollSeconds = 30;

	private ?TaskHandler $pollTaskHandler = null;
	private bool $pollingInFlight = false;

	public function loadFromConfig() : void{
		$config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
		$section = (array) ($config?->get("tebex", []) ?? []);

		$this->enabled = (bool) ($section["enabled"] ?? false);
		$this->secret = trim((string) ($section["secret"] ?? ""));
		$this->pollSeconds = max(10, (int) ($section["poll-seconds"] ?? 30));

		$this->restartPollingTask();
	}

	public function shutdown() : void{
		if ($this->pollTaskHandler !== null) {
			$this->pollTaskHandler->cancel();
			$this->pollTaskHandler = null;
		}
	}

	public function isEnabled() : bool{
		return $this->enabled && $this->secret !== "";
	}

	public function getPollSeconds() : int{
		return $this->pollSeconds;
	}

	public function queuePoll(bool $manual = false) : bool{
		if (!$this->isEnabled()) {
			return false;
		}
		if ($this->pollingInFlight) {
			return false;
		}

		$this->pollingInFlight = true;
		Loader::getInstance()->getServer()->getAsyncPool()->submitTask(new TebexPollTask(
			$this->secret,
			$manual
		));
		return true;
	}

	/**
	 * @param array<int, array{id:int, command:string, player_name:string, player_uuid:string}> $commands
	 */
	public function handlePolledCommands(array $commands) : void{
		$this->pollingInFlight = false;
		if ($commands === []) {
			return;
		}

		$console = new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage());
		$executedIds = [];

		foreach ($commands as $row) {
			$command = trim((string) ($row["command"] ?? ""));
			if ($command === "") {
				continue;
			}

			$playerName = (string) ($row["player_name"] ?? "");
			$playerUuid = (string) ($row["player_uuid"] ?? "");
			$command = str_replace(
				["{player}", "{name}", "{username}", "{uuid}"],
				[$playerName, $playerName, $playerName, $playerUuid],
				$command
			);
			Server::getInstance()->dispatchCommand($console, $command);
			$executedIds[] = (int) ($row["id"] ?? 0);
		}

		$executedIds = array_values(array_filter($executedIds, static fn(int $id) : bool => $id > 0));
		if ($executedIds !== []) {
			Loader::getInstance()->getServer()->getAsyncPool()->submitTask(new TebexAcknowledgeTask(
				$this->secret,
				$executedIds
			));
		}
	}

	public function markPollingComplete() : void{
		$this->pollingInFlight = false;
	}

	private function restartPollingTask() : void{
		if ($this->pollTaskHandler !== null) {
			$this->pollTaskHandler->cancel();
			$this->pollTaskHandler = null;
		}

		if (!$this->isEnabled()) {
			return;
		}

		$this->pollTaskHandler = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
			new class extends \pocketmine\scheduler\Task{
				public function onRun() : void{
					Loader::getTebexManager()->queuePoll(false);
				}
			},
			$this->pollSeconds * 20
		);
	}

	public function getStatusMessage() : string{
		if (!$this->enabled) {
			return C::colorize("&r&7Tebex integration is &cdisabled&7.");
		}
		if ($this->secret === "") {
			return C::colorize("&r&7Tebex integration is enabled, but &csecret is empty&7.");
		}
		return C::colorize("&r&7Tebex polling every &f{$this->pollSeconds}s&7.");
	}
}

