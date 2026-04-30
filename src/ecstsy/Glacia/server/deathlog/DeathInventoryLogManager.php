<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\deathlog;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class DeathInventoryLogManager{

	private bool $enabled = true;
	private int $maxEntriesPerPlayer = 30;
	private string $storageFile;
	private int $nextId = 1;

	/** @var array<int, array<string, mixed>> */
	private array $entries = [];

	public function __construct(){
		$this->storageFile = Loader::getInstance()->getDataFolder() . "death_inventory_logs.json";
		$this->loadStorage();
	}

	public function loadFromConfig() : void{
		$config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
		$section = (array) ($config?->get("death-inventory-log", []) ?? []);
		$this->enabled = (bool) ($section["enabled"] ?? true);
		$this->maxEntriesPerPlayer = max(5, (int) ($section["max-entries-per-player"] ?? 30));
	}

	public function isEnabled() : bool{
		return $this->enabled;
	}

	public function logDeath(Player $player, string $cause = "") : ?int{
		if(!$this->enabled){
			return null;
		}

		$id = $this->nextId++;
		$entry = [
			"id" => $id,
			"player_name" => $player->getName(),
			"player_uuid" => $player->getUniqueId()->toString(),
			"created_at" => time(),
			"cause" => $cause,
			"inventory" => $this->serializeItems($player->getInventory()->getContents()),
			"armor" => $this->serializeItems($player->getArmorInventory()->getContents()),
			"offhand" => $this->safeEncodeItem($player->getOffHandInventory()->getItem(0))
		];
		$this->entries[$id] = $entry;

		$this->trimPlayerEntries($player->getName());
		$this->saveStorage();
		return $id;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getHistory(string $playerName, int $page = 1, int $perPage = 8) : array{
		$playerName = strtolower(trim($playerName));
		$list = array_values(array_filter($this->entries, static fn(array $entry) : bool => strtolower((string) ($entry["player_name"] ?? "")) === $playerName));
		usort($list, static fn(array $a, array $b) : int => ((int) ($b["created_at"] ?? 0)) <=> ((int) ($a["created_at"] ?? 0)));

		$offset = max(0, ($page - 1) * max(1, $perPage));
		return array_slice($list, $offset, max(1, $perPage));
	}

	public function getEntry(int $id) : ?array{
		return $this->entries[$id] ?? null;
	}

	/**
	 * @return Item[]
	 */
	public function decodeEntryItems(array $entry) : array{
		$out = [];
		foreach((array) ($entry["inventory"] ?? []) as $row){
			if(!is_array($row)){
				continue;
			}
			$data = (string) ($row["data"] ?? "");
			if($data === ""){
				continue;
			}
			try{
				$out[] = ItemUtils::decodeItem($data);
			}catch(\Throwable){
				continue;
			}
		}
		foreach((array) ($entry["armor"] ?? []) as $row){
			if(!is_array($row)){
				continue;
			}
			$data = (string) ($row["data"] ?? "");
			if($data === ""){
				continue;
			}
			try{
				$out[] = ItemUtils::decodeItem($data);
			}catch(\Throwable){
				continue;
			}
		}
		$offhand = (string) ($entry["offhand"] ?? "");
		if($offhand !== ""){
			try{
				$item = ItemUtils::decodeItem($offhand);
				if(!$item->isNull()){
					$out[] = $item;
				}
			}catch(\Throwable){
			}
		}
		return $out;
	}

	/**
	 * @param array<int, Item> $items
	 * @return array<int, array{slot:int,data:string}>
	 */
	private function serializeItems(array $items) : array{
		$result = [];
		foreach($items as $slot => $item){
			$data = $this->safeEncodeItem($item);
			if($data === ""){
				continue;
			}

			$result[] = [
				"slot" => (int) $slot,
				"data" => $data
			];
		}
		return $result;
	}

	private function safeEncodeItem(Item $item) : string{
		if($item->isNull()){
			return "";
		}

		try{
			return ItemUtils::encodeItem($item);
		}catch(\Throwable){
			return "";
		}
	}

	private function trimPlayerEntries(string $playerName) : void{
		$playerName = strtolower($playerName);
		$ids = [];
		foreach($this->entries as $id => $entry){
			if(strtolower((string) ($entry["player_name"] ?? "")) !== $playerName){
				continue;
			}
			$ids[$id] = (int) ($entry["created_at"] ?? 0);
		}
		arsort($ids);
		$keep = array_slice(array_keys($ids), 0, $this->maxEntriesPerPlayer);
		$keepSet = array_fill_keys($keep, true);
		foreach(array_keys($ids) as $id){
			if(!isset($keepSet[$id])){
				unset($this->entries[$id]);
			}
		}
	}

	private function loadStorage() : void{
		if(!file_exists($this->storageFile)){
			return;
		}
		$decoded = StorageUtils::readJsonFile($this->storageFile, "death inventory log storage");
		if(!is_array($decoded)){
			return;
		}
		$this->nextId = max(1, (int) ($decoded["next_id"] ?? 1));
		foreach((array) ($decoded["entries"] ?? []) as $row){
			if(!is_array($row)){
				continue;
			}
			$id = (int) ($row["id"] ?? 0);
			if($id > 0){
				$this->entries[$id] = $row;
			}
		}
	}

	private function saveStorage() : void{
		StorageUtils::writeJsonFile($this->storageFile, [
			"next_id" => $this->nextId,
			"entries" => array_values($this->entries)
		], "death inventory log storage");
	}
}

