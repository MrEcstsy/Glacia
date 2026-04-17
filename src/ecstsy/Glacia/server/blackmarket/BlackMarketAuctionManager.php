<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\blackmarket;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use RuntimeException;

final class BlackMarketAuctionManager {
    use SingletonTrait;

    private array $config;

    public function __construct() {
        self::setInstance($this);
        $this->config = GeneralUtils::getConfiguration(Loader::getInstance(), "blackmarket.yml")->getAll();
    }

    public function reload(): void {
        $this->config = GeneralUtils::getConfiguration(Loader::getInstance(), "blackmarket.yml")->getAll();
    }

    public function getConfig(): array {
        return $this->config;
    }

    public function getInterval(): int {
        return (int)($this->config["settings"]["interval"] ?? 1800);
    }

    public function getAntiSnipeSeconds(): int {
        return (int)($this->config["settings"]["anti-snipe-seconds"] ?? 10);
    }

    public function getBroadcast(string $key): string {
        return (string)($this->config["settings"]["broadcast"][$key] ?? "");
    }

    public function rollListing(?Player $player = null): ?BlackMarketAuctionListing {
        $pool = $this->config["pool"] ?? [];
        if(!is_array($pool) || $pool === []){
            return null;
        }

        $entryKey = $this->rollPoolKey($pool);
        if($entryKey === null){
            return null;
        }

        $entry = $pool[$entryKey] ?? null;
        if(!is_array($entry)){
            return null;
        }

        $rewardData = $entry["reward"] ?? null;
        $listingData = $entry["listing"] ?? null;

        if(!is_array($rewardData) || !is_array($listingData)){
            return null;
        }

        $reward = RewardResolver::resolveItem($rewardData, $player);
        if(!$reward instanceof Item){
            throw new RuntimeException("Failed to resolve BAH reward for entry '{$entryKey}'");
        }

        $displayName = (string)($listingData["name"] ?? $reward->getName());
        $startingBidRaw = $listingData["starting-bid"] ?? 100000;
        $startingBid = GeneralUtils::parseRandomNumber($startingBidRaw);

        return new BlackMarketAuctionListing(
            $entryKey,
            $displayName,
            $reward,
            max(1, $startingBid)
        );
    }

    public function getBidIncrement(int $currentBid): int {
        $default = (int)($this->config["settings"]["bid-increments"]["default"] ?? 100000);
        $increment = $default;

        $thresholds = $this->config["settings"]["bid-increments"]["thresholds"] ?? [];
        if(!is_array($thresholds)){
            return $increment;
        }

        foreach($thresholds as $threshold){
            if(!is_array($threshold)) continue;

            $min = (int)($threshold["min"] ?? 0);
            $value = (int)($threshold["increment"] ?? $increment);

            if($currentBid >= $min){
                $increment = $value;
            }
        }

        return max(1, $increment);
    }

    private function rollPoolKey(array $pool): ?string {
        $weighted = [];
        $total = 0;

        foreach($pool as $key => $entry){
            if(!is_array($entry)) continue;

            $chance = (int)($entry["chance"] ?? 0);
            if($chance <= 0) continue;

            $weighted[] = [
                "key" => (string)$key,
                "chance" => $chance
            ];

            $total += $chance;
        }

        if($total <= 0){
            return null;
        }

        $roll = mt_rand(1, $total);
        $current = 0;

        foreach($weighted as $data){
            $current += $data["chance"];
            if($roll <= $current){
                return $data["key"];
            }
        }

        return null;
    }

    public function giveAuctionToWinner(string $playerName, Item $item, int $bid): void {
        $player = Loader::getInstance()->getServer()->getPlayerExact($playerName);

        if ($player !== null && $player->isOnline()) {
            $session = Loader::getPlayerManager()->getSession($player);
            if ($session !== null) {
                $session->addItemToCollection(clone $item);
                $player->sendMessage("§aYou won the Black Market Auction for §f$" . number_format($bid) . "§a!");
            }
            return;
        }

    }

    public function getStartingCooldown(): int {
        return (int)($this->config["settings"]["interval"] ?? 1800);
    }

    public function getAuctionDuration(): int {
        return (int)($this->config["settings"]["duration"] ?? 1800);
    }

    public function formatBroadcast(string $key, array $replacements = []): string {
        $message = $this->getBroadcast($key);

        foreach ($replacements as $search => $replace) {
            $message = str_replace("{" . $search . "}", (string) $replace, $message);
        }

        return TextFormat::colorize($message);
    }

    public function messageAll(string $message): void {
        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $online) {
            $online->sendMessage($message);
        }
    }
}