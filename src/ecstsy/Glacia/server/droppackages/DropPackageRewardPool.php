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

    /**
     * @return array<int, array{name: string, entries: array<int, array<string, mixed>>}>
     */
    public static function getPreviewGroups(string $packageType): array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "dps/" . ucfirst(strtolower($packageType)) . ".yml");
        if ($config === null) {
            return [];
        }

        $path = strtolower($packageType) . "-loot";
        $loot = $config->getNested($path, []);
        if (!is_array($loot)) {
            return [];
        }

        $groups = [];
        foreach ($loot as $rarity => $entries) {
            if (!is_array($entries)) {
                continue;
            }

            $filtered = array_values(array_filter($entries, static fn(mixed $entry): bool => is_array($entry)));
            if ($filtered === []) {
                continue;
            }

            $groups[] = [
                "name" => ucfirst((string) $rarity),
                "entries" => $filtered
            ];
        }

        return $groups;
    }

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
