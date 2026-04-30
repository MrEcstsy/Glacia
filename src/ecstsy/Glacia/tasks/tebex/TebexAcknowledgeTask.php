<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks\tebex;

use pocketmine\scheduler\AsyncTask;

final class TebexAcknowledgeTask extends AsyncTask{

	/**
	 * @param int[] $ids
	 */
	public function __construct(
		private string $secret,
		private array $ids
	){}

	public function onRun() : void{
		if($this->ids === []){
			return;
		}

		$ch = curl_init("https://plugin.tebex.io/queue");
		if($ch === false){
			return;
		}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 12,
			CURLOPT_CONNECTTIMEOUT => 8,
			CURLOPT_CUSTOMREQUEST => "DELETE",
			CURLOPT_POSTFIELDS => http_build_query(["ids" => $this->ids]),
			CURLOPT_HTTPHEADER => [
				"X-Tebex-Secret: " . $this->secret,
				"Accept: application/json"
			]
		]);
		curl_exec($ch);
		curl_close($ch);
	}
}

