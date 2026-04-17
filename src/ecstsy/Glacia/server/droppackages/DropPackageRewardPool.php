<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\droppackages;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\item\Item;
use pocketmine\plugin\PluginBase;

final class DropPackageRewardPool {

    public static function rollReward(string $packageType, string $rarity, ?Player $player = null): array{
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "dps/" . ucfirst(strtolower($packageType)) . ".yml");

        $path = strtolower($packageType) . "-loot." . strtolower($rarity);
        $pool = $config->getNested($path, []);

        if(!is_array($pool) || $pool === []){
            throw new \RuntimeException("Missing or empty reward pool for path: " . $path);
        }

        $entry = RewardResolver::rollEntry($pool);
        if($entry === null){
            throw new \RuntimeException("Failed to roll reward entry for path: " . $path);
        }

        $item = RewardResolver::resolveItem($entry, $player);
        if($item === null){
            throw new \RuntimeException("Failed to resolve reward item for path: " . $path);
        }

        return [
            "entry" => $entry,
            "item" => $item
        ];
    }
}