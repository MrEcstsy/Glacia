<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\vote;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\SplitConfigHelper;
use ecstsy\Glacia\tasks\vote\VoteClaimTask;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\Glacia\utils\StorageUtils;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\sound\XpCollectSound;

final class VoteManager{

	private bool $enabled = false;
	private string $apiKey = "";
	private bool $autoClaimOnJoin = true;
	private int $claimCooldownSeconds = 86400;

	/** @var string[] */
	private array $voteSites = [];

	/** @var array<int, array<string, mixed>> */
	private array $rewardItems = [];
	/** @var string[] */
	private array $rewardCommands = [];
	/** @var array<int, array<string, mixed>> */
	private array $bonusRewardItems = [];
	/** @var string[] */
	private array $bonusRewardCommands = [];
	private string $bonusRewardPermission = "glacia.vote.bonus_reward";

	/** @var array<string, int> */
	private array $voteTotals = [];
	/** @var array<string, int> */
	private array $nextEligibleAt = [];
	private string $storageFile;

	/** @var array<string, bool> */
	private array $claimInFlight = [];

	public function __construct(){
		$this->storageFile = Loader::getInstance()->getDataFolder() . "vote_data.json";
		$this->loadStorage();
	}

	public function loadFromConfig() : void{
		$section = SplitConfigHelper::loadRootSection(Loader::getInstance(), "voting.yml", "voting");

		$this->enabled = (bool) ($section["enabled"] ?? false);
		$this->apiKey = trim((string) ($section["api-key"] ?? ""));
		$this->autoClaimOnJoin = (bool) ($section["auto-claim-on-join"] ?? true);
		$this->claimCooldownSeconds = max(0, (int) ($section["claim-cooldown-seconds"] ?? 86400));
		$this->voteSites = array_values(array_filter(array_map("strval", (array) ($section["vote-sites"] ?? [])), static fn(string $s) : bool => trim($s) !== ""));
		$this->rewardItems = array_values(array_filter((array) ($section["rewards"]["items"] ?? []), static fn(mixed $row) : bool => is_array($row)));
		$this->rewardCommands = array_values(array_filter(array_map("strval", (array) ($section["rewards"]["commands"] ?? [])), static fn(string $s) : bool => trim($s) !== ""));
		$this->bonusRewardPermission = trim((string) ($section["rewards"]["bonus"]["permission"] ?? "glacia.vote.bonus_reward"));
		$this->bonusRewardItems = array_values(array_filter((array) ($section["rewards"]["bonus"]["items"] ?? []), static fn(mixed $row) : bool => is_array($row)));
		$this->bonusRewardCommands = array_values(array_filter(array_map("strval", (array) ($section["rewards"]["bonus"]["commands"] ?? [])), static fn(string $s) : bool => trim($s) !== ""));
	}

	public function isEnabled() : bool{
		return $this->enabled && $this->apiKey !== "";
	}

	public function shouldAutoClaimOnJoin() : bool{
		return $this->autoClaimOnJoin;
	}

	public function queueClaim(Player $player, bool $manual = true) : bool{
		if(!$this->isEnabled()){
			return false;
		}

		if($this->isOnLocalCooldown($player->getName())){
			return false;
		}

		$key = strtolower($player->getName());
		if(isset($this->claimInFlight[$key])){
			return false;
		}
		$this->claimInFlight[$key] = true;
		Loader::getInstance()->getServer()->getAsyncPool()->submitTask(new VoteClaimTask(
			$this->apiKey,
			$player->getName(),
			$manual
		));
		return true;
	}

	public function handleClaimResult(string $playerName, bool $manual, bool $claimed, string $message = "") : void{
		unset($this->claimInFlight[strtolower($playerName)]);
		$player = Loader::getInstance()->getServer()->getPlayerExact($playerName);
		if($player === null || !$player->isConnected()){
			return;
		}

		if(!$claimed){
			if($manual){
				$friendly = $this->getFriendlyFailureMessage($message);
				$player->sendMessage(C::colorize("&r&eNo pending vote found right now." . ($friendly !== "" ? " &7(" . $friendly . ")" : "")));
			}
			return;
		}

		$this->grantRewards($player);
		$this->incrementVoteCount($player->getName());
		$this->setNextEligibleAt($player->getName(), time() + $this->claimCooldownSeconds);
		$this->saveStorage();
        $this->announceClaim($player);
	}

	public function isOnLocalCooldown(string $playerName) : bool{
		return $this->getRemainingLocalCooldown($playerName) > 0;
	}

	public function getRemainingLocalCooldown(string $playerName) : int{
		$key = strtolower($playerName);
		$next = (int) ($this->nextEligibleAt[$key] ?? 0);
		return max(0, $next - time());
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getStatus() : array{
		return [
			"enabled" => $this->isEnabled(),
			"sites" => $this->voteSites,
			"tracked_voters" => count($this->voteTotals)
		];
	}

	/**
	 * @return array<string, int>
	 */
	public function getTopVoters(int $limit = 10) : array{
		$data = $this->voteTotals;
		arsort($data);
		return array_slice($data, 0, max(1, $limit), true);
	}

	/**
	 * @return string[]
	 */
	public function getVoteSites() : array{
		return $this->voteSites;
	}

	private function grantRewards(Player $player) : void{
		$this->grantConfiguredRewards($player, $this->rewardItems, $this->rewardCommands);

		if (
			$this->bonusRewardPermission !== "" &&
			$player->hasPermission($this->bonusRewardPermission) &&
			($this->bonusRewardItems !== [] || $this->bonusRewardCommands !== [])
		) {
			$this->grantConfiguredRewards($player, $this->bonusRewardItems, $this->bonusRewardCommands);
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $rewardItems
	 * @param string[] $rewardCommands
	 */
	private function grantConfiguredRewards(Player $player, array $rewardItems, array $rewardCommands) : void{
		foreach($rewardItems as $entry){
			$item = RewardResolver::resolveItem($entry, $player);
			if($item === null){
				continue;
			}
			foreach($player->getInventory()->addItem($item) as $leftover){
				$player->getWorld()->dropItem($player->getPosition(), $leftover);
			}
		}

		$console = new ConsoleCommandSender($player->getServer(), $player->getServer()->getLanguage());
		foreach($rewardCommands as $raw){
			$command = str_replace(
				["{player}", "{name}", "{username}", "{uuid}"],
				[$player->getName(), $player->getName(), $player->getName(), $player->getUniqueId()->toString()],
				$raw
			);
			$player->getServer()->dispatchCommand($console, $command);
		}
	}

	private function incrementVoteCount(string $playerName) : void{
		$key = strtolower($playerName);
		$this->voteTotals[$key] = ($this->voteTotals[$key] ?? 0) + 1;
	}

	private function setNextEligibleAt(string $playerName, int $unixTime) : void{
		$key = strtolower($playerName);
		$this->nextEligibleAt[$key] = max(0, $unixTime);
	}

    private function announceClaim(Player $player) : void{
        $name = $player->getName();
        $count = $this->voteTotals[strtolower($name)] ?? 1;

        $player->sendTitle(
            C::colorize("&l&aVOTE CLAIMED"),
            C::colorize("&7Rewards delivered to your inventory"),
            8,
            40,
            8
        );
        $player->sendActionBarMessage(C::colorize("&6✦ &eThanks for voting! &7(total claims: &f{$count}&7)"));
        $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());

        $broadcast = TextFormat::colorize(
            "&r&8[&aVote&8] &f{$name} &7just claimed vote rewards! &d✦"
        );
        foreach(Loader::getInstance()->getServer()->getOnlinePlayers() as $online){
            $online->sendMessage($broadcast);
        }
    }

	private function loadStorage() : void{
		if(!file_exists($this->storageFile)){
			return;
		}
		$decoded = StorageUtils::readJsonFile($this->storageFile, "vote storage");
		if(!is_array($decoded)){
			return;
		}
		$votes = (array) ($decoded["votes"] ?? []);
		foreach($votes as $name => $count){
			$this->voteTotals[strtolower((string) $name)] = max(0, (int) $count);
		}

		$cooldowns = (array) ($decoded["nextEligibleAt"] ?? []);
		foreach($cooldowns as $name => $unixTime){
			$value = max(0, (int) $unixTime);
			if($value > 0){
				$this->nextEligibleAt[strtolower((string) $name)] = $value;
			}
		}
	}

	private function saveStorage() : void{
		StorageUtils::writeJsonFile(
			$this->storageFile,
			[
				"votes" => $this->voteTotals,
				"nextEligibleAt" => $this->nextEligibleAt
			],
			"vote storage"
		);
	}

	private function getFriendlyFailureMessage(string $raw) : string{
		$message = trim($raw);
		if($message === ""){
			return "";
		}

		$lower = strtolower($message);
		if(str_contains($lower, "typeerror") || str_contains($lower, "stack trace") || str_contains($lower, "called in")){
			return "vote service unavailable";
		}

		if(str_contains($lower, "timed out") || str_contains($lower, "could not resolve host") || str_contains($lower, "failed to initialize curl")){
			return "temporary connection issue";
		}

		return strlen($message) > 72 ? substr($message, 0, 72) . "..." : $message;
	}
}
