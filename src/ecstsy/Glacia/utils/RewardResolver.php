<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class RewardResolver {

    /**
     * Rolls a weighted reward entry from a config pool.
     *
     * @param array<int, array<string, mixed>> $pool
     */
    public static function rollEntry(array $pool): ?array{
        $weighted = [];
        $totalWeight = 0;

        foreach($pool as $entry){
            $chance = (int)($entry["chance"] ?? 0);
            if($chance <= 0){
                continue;
            }

            $weighted[] = [
                "weight" => $chance,
                "entry" => $entry
            ];

            $totalWeight += $chance;
        }

        if($totalWeight <= 0){
            return null;
        }

        $roll = mt_rand(1, $totalWeight);
        $current = 0;

        foreach($weighted as $data){
            $current += $data["weight"];
            if($roll <= $current){
                return $data["entry"];
            }
        }

        return null;
    }

    /**
     * Resolves a config reward entry into an Item.
     *
     * @param array<string, mixed> $entry
     */
    public static function resolveItem(array $entry, ?Player $player = null): ?Item{
        $type = strtolower((string)($entry["type"] ?? "item"));

        return match($type){
            "item" => self::resolveGenericItem($entry, $player),
            "money_note" => self::resolveFactoryItem("money_note", $entry, $player),
            "xp_note", "xpnote" => self::resolveFactoryItem("xp_note", $entry, $player),
            default => self::resolveGenericItem($entry, $player)
        };
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveGenericItem(array $entry, ?Player $player = null): ?Item{
        $item = null;

        if(isset($entry["id"])){
            $item = self::resolveFactoryItem((string)$entry["id"], $entry, $player);
        }

        if($item === null && isset($entry["material"])){
            $item = StringToItemParser::getInstance()->parse((string)$entry["material"]);
        }

        if($item === null){
            return null;
        }

        $count = GeneralUtils::parseRandomNumber($entry["count"] ?? 1);
        $item->setCount(max(1, $count));

        if(isset($entry["name"])){
            $item->setCustomName(C::colorize((string)$entry["name"]));
        }

        if(isset($entry["lore"]) && is_array($entry["lore"])){
            $item->setLore(array_map(
                fn(string $line) => C::colorize($line),
                $entry["lore"]
            ));
        }

        if(isset($entry["enchantments"]) && is_array($entry["enchantments"])){
            self::applyEnchantments($item, $entry["enchantments"]);
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveFactoryItem(string $id, array $entry, ?Player $player = null): ?Item {
        $id = strtolower($id);

        $customArmor = self::resolveCustomArmorItem($id, $entry);
        if ($customArmor !== null) {
            return $customArmor;
        }

        $customWeapon = self::resolveCustomWeaponItem($id, $entry);
        if ($customWeapon !== null) {
            return $customWeapon;
        }

        return match($id) {
            "money_note" => GlaciaItemFactory::bankNote($player, GeneralUtils::parseRandomNumber($entry["amount"] ?? 1)),
            "xp_note", "xpnote" => GlaciaItemFactory::xpNote($player, GeneralUtils::parseRandomNumber($entry["amount"] ?? 1)),

            "simplebook" => GlaciaItemFactory::randomEnchantBook("simple"),
            "uniquebook" => GlaciaItemFactory::randomEnchantBook("unique"),
            "elitebook" => GlaciaItemFactory::randomEnchantBook("elite"),
            "ultimatebook" => GlaciaItemFactory::randomEnchantBook("exotic"),
            "legendarybook" => GlaciaItemFactory::randomEnchantBook("legendary"),

            "whitescroll" => GlaciaItemFactory::whitescroll(),
            "blackscroll" => GlaciaItemFactory::blackscroll((int)($entry["success"] ?? 100)),
            "renametag", "itemnametag" => GlaciaItemFactory::renametag(),

            "simpledp" => GlaciaItemFactory::dropPackage("simple_chest"),
            "uniquedp" => GlaciaItemFactory::dropPackage("unique_chest"),
            "elitedp" => GlaciaItemFactory::dropPackage("elite_chest"),
            "ultimatedp" => GlaciaItemFactory::dropPackage("ultimate_chest"),
            "legendarydp" => GlaciaItemFactory::dropPackage("legendary_chest"),
            "goddp" => GlaciaItemFactory::dropPackage("godly_chest"),

            "simpledust" => GlaciaItemFactory::randomMagicDust("simple"),
            "uniquedust" => GlaciaItemFactory::randomMagicDust("unique"),
            "elitedust" => GlaciaItemFactory::randomMagicDust("elite"),
            "ultimatedust" => GlaciaItemFactory::randomMagicDust("exotic"),
            "legendarydust" => GlaciaItemFactory::randomMagicDust("legendary"),

            "mysterymobspawner" => GlaciaItemFactory::mysteryMobSpawner(),
            'heroicmysterymobspawner' => GlaciaItemFactory::heroicMysteryMobSpawner(),

            "blaze_spawner" => GlaciaItemFactory::mobSpawner("blaze"),
            "creeper_spawner" => GlaciaItemFactory::mobSpawner("creeper"),
            "enderman_spawner" => GlaciaItemFactory::mobSpawner("enderman"),
            "ghast_spawner" => GlaciaItemFactory::mobSpawner("ghast"),
            "magma_cube_spawner" => GlaciaItemFactory::mobSpawner("magma_cube"),
            "skeleton_spawner" => GlaciaItemFactory::mobSpawner("skeleton"),
            "slime_spawner" => GlaciaItemFactory::mobSpawner("slime"),
            "squid_spawner" => GlaciaItemFactory::mobSpawner("squid"),
            "zombie_spawner" => GlaciaItemFactory::mobSpawner("zombie"),
            "zombie_pigman_spawner" => GlaciaItemFactory::mobSpawner("zombie_pigman"),
            "iron_golem_spawner" => GlaciaItemFactory::mobSpawner("iron_golem"),
            "cow_spawner" => GlaciaItemFactory::mobSpawner("cow"),
            "mooshroom_spawner" => GlaciaItemFactory::mobSpawner("mooshroom"),
            "pig_spawner" => GlaciaItemFactory::mobSpawner("pig"), 
            "chicken_spawner" => GlaciaItemFactory::mobSpawner("chicken"),
            "sheep_spawner" => GlaciaItemFactory::mobSpawner("sheep"),
            'spider_spawner' => GlaciaItemFactory::mobSpawner("spider"),

            default => GlaciaItemFactory::create($id)
        };
    }

    private static function resolveCustomArmorItem(string $id, array $entry): ?Item {
        if (!preg_match('/^([a-z0-9]+)_(helmet|chestplate|leggings|boots)$/', $id, $matches)) {
            return null;
        }

        $set = $matches[1];
        $piece = $matches[2];
        $enchanted = (bool)($entry["enchanted"] ?? true);

        return GlaciaItemFactory::customArmor($set, $piece, $enchanted);
    }

    private static function resolveCustomWeaponItem(string $id, array $entry): ?Item {
        if (!preg_match('/^([a-z0-9]+)_(sword|bow)$/', $id, $matches)) {
            return null;
        }

        $set = $matches[1];
        $type = $matches[2];

        return GlaciaItemFactory::customWeapon($set, $type);
    }

    /**
     * @param array<int, array<string, mixed>> $enchantments
     */
    private static function applyEnchantments(Item $item, array $enchantments): void{
        foreach($enchantments as $enchantmentData){
            $enchantName = strtolower((string)($enchantmentData["enchant"] ?? ""));
            $level = (int)($enchantmentData["level"] ?? 1);

            if($enchantName === ""){
                continue;
            }

            // Try vanilla enchant parser first
            $vanilla = StringToEnchantmentParser::getInstance()->parse($enchantName);
            if($vanilla !== null){
                $item->addEnchantment(new EnchantmentInstance($vanilla, $level));
                continue;
            }

            // TODO: hook your custom enchant parser/registry here if needed
            // Example:
            // $custom = CustomEnchantments::getByName($enchantName);
            // if($custom !== null){
            //     CustomEnchantments::applyToItem($item, $custom, $level);
            // }

            continue;
        }
    }
}