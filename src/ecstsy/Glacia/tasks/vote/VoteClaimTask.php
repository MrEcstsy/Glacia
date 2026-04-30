<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks\vote;

use ecstsy\Glacia\Loader;
use pocketmine\scheduler\AsyncTask;

final class VoteClaimTask extends AsyncTask{

	public function __construct(
		private string $apiKey,
		private string $username,
		private bool $manual
	){}

	public function onRun() : void{
		$result = [
			"username" => $this->username,
			"manual" => $this->manual,
			"claimed" => false,
			"message" => ""
		];

		try{
			$url = "https://minecraftpocket-servers.com/api/?object=votes&element=claim&key=" . rawurlencode($this->apiKey) . "&username=" . rawurlencode($this->username) . "&format=json";
			$raw = $this->get($url);
			$message = "";
			$result["claimed"] = $this->parseClaimed($raw, $message);
			$result["message"] = $message;
		}catch(\Throwable $e){
			$result["message"] = $e->getMessage();
		}

		$this->setResult($result);
	}

	public function onCompletion() : void{
		$result = (array) $this->getResult();
		Loader::getVoteManager()->handleClaimResult(
			(string) ($result["username"] ?? ""),
			(bool) ($result["manual"] ?? false),
			(bool) ($result["claimed"] ?? false),
			(string) ($result["message"] ?? "")
		);
	}

	private function get(string $url) : string{
		$ch = curl_init($url);
		if($ch === false){
			throw new \RuntimeException("Failed to initialize cURL");
		}
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 12,
			CURLOPT_CONNECTTIMEOUT => 8,
			CURLOPT_HTTPHEADER => ["Accept: application/json"]
		]);
		try{
			$raw = curl_exec($ch);
			if(!is_string($raw)){
				throw new \RuntimeException("Vote API request failed: " . curl_error($ch));
			}
			return $raw;
		}finally{
			curl_close($ch);
		}
	}

	private function parseClaimed(string $raw, string &$message) : bool{
		$message = "";
		$trim = trim($raw);
		if($trim === ""){
			$message = "empty response";
			return false;
		}

		if($trim === "1"){
			return true;
		}
		if($trim === "0"){
			return false;
		}

		try{
			$decoded = json_decode($trim, true, 512, JSON_THROW_ON_ERROR);
		}catch(\JsonException){
			$message = $trim;
			return false;
		}
		if(!is_array($decoded)){
			$message = $trim;
			return false;
		}

		$message = (string) ($decoded["message"] ?? $decoded["status"] ?? "");
		$truthy = ["1", "true", "success", "ok", "claimed"];
		foreach(["claimed", "success", "vote", "status"] as $k){
			if(!array_key_exists($k, $decoded)){
				continue;
			}
			$v = $decoded[$k];
			if(is_bool($v)){
				return $v;
			}
			if(is_int($v)){
				return $v === 1;
			}
			if(is_string($v)){
				return in_array(strtolower(trim($v)), $truthy, true);
			}
		}

		return false;
	}
}

