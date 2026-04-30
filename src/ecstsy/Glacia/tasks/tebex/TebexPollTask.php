<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks\tebex;

use ecstsy\Glacia\Loader;
use pocketmine\scheduler\AsyncTask;

final class TebexPollTask extends AsyncTask{

	public function __construct(
		private string $secret,
		private bool $manual
	){}

	public function onRun() : void{
		$result = [
			"ok" => true,
			"manual" => $this->manual,
			"commands" => [],
			"error" => ""
		];

		try{
			$due = $this->request("GET", "/queue");
			$players = (array) ($due["players"] ?? []);
			$executeOffline = (bool) (($due["meta"]["execute_offline"] ?? false));

			foreach($players as $playerRow){
				$playerId = (int) ($playerRow["id"] ?? 0);
				if($playerId <= 0){
					continue;
				}
				$name = (string) ($playerRow["name"] ?? "");
				$uuid = (string) ($playerRow["uuid"] ?? "");

				$online = $this->request("GET", "/queue/online-commands/" . $playerId);
				foreach((array) ($online["commands"] ?? []) as $cmd){
					$command = trim((string) ($cmd["command"] ?? ""));
					if($command === ""){
						continue;
					}
					$result["commands"][] = [
						"id" => (int) ($cmd["id"] ?? 0),
						"command" => $command,
						"player_name" => $name,
						"player_uuid" => $uuid
					];
				}
			}

			if($executeOffline){
				$offline = $this->request("GET", "/queue/offline-commands");
				foreach((array) ($offline["commands"] ?? []) as $cmd){
					$player = (array) ($cmd["player"] ?? []);
					$command = trim((string) ($cmd["command"] ?? ""));
					if($command === ""){
						continue;
					}
					$result["commands"][] = [
						"id" => (int) ($cmd["id"] ?? 0),
						"command" => $command,
						"player_name" => (string) ($player["name"] ?? ""),
						"player_uuid" => (string) ($player["uuid"] ?? "")
					];
				}
			}
		}catch(\Throwable $e){
			$result["ok"] = false;
			$result["error"] = $e->getMessage();
		}

		$this->setResult($result);
	}

	public function onCompletion() : void{
		$manager = Loader::getTebexManager();
		$result = (array) $this->getResult();
		$ok = (bool) ($result["ok"] ?? false);
		$manual = (bool) ($result["manual"] ?? false);
		if(!$ok){
			$manager->markPollingComplete();
			$error = (string) ($result["error"] ?? "unknown_error");
			if($manual){
				Loader::getInstance()->getLogger()->warning("Manual Tebex poll failed: " . $error);
			}
			return;
		}

		$commands = (array) ($result["commands"] ?? []);
		$manager->handlePolledCommands($commands);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function request(string $method, string $endpoint, ?array $postFields = null) : array{
		$url = "https://plugin.tebex.io" . $endpoint;
		$ch = curl_init($url);
		if($ch === false){
			throw new \RuntimeException("Failed to initialize cURL");
		}

		$options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 15,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_HTTPHEADER => [
				"X-Tebex-Secret: " . $this->secret,
				"Accept: application/json"
			]
		];
		if($method === "DELETE"){
			$options[CURLOPT_CUSTOMREQUEST] = "DELETE";
			$options[CURLOPT_POSTFIELDS] = http_build_query($postFields ?? []);
		}elseif($method === "POST"){
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = http_build_query($postFields ?? []);
		}
		curl_setopt_array($ch, $options);

		try{
			$raw = curl_exec($ch);
			if(!is_string($raw)){
				throw new \RuntimeException("cURL failed: " . curl_error($ch));
			}
			$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if($code < 200 || $code >= 300){
				throw new \RuntimeException("Tebex responded with HTTP " . $code);
			}
			if(trim($raw) === ""){
				return [];
			}
			try{
				$decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
			}catch(\JsonException $e){
				throw new \RuntimeException("Invalid JSON from Tebex: " . $e->getMessage(), 0, $e);
			}
			if(!is_array($decoded)){
				throw new \RuntimeException("Invalid JSON from Tebex");
			}
			return $decoded;
		}finally{
			curl_close($ch);
		}
	}
}

