<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\color\Color;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\VanillaItems;
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
            $material = (string)$entry["material"];
            $item = StringToItemParser::getInstance()->parse($material);

            if($item === null){
                $item = self::resolveFactoryItem($material, $entry, $player);
            }
        }

        if($item === null){
            return null;
        }

        $countInput = $entry["count"] ?? self::getDefaultStackCount($entry);
        $count = GeneralUtils::parseRandomNumber($countInput);
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

        if(isset($entry["color"])){
            self::applyColor($item, $entry["color"]);
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
            "blackscroll" => GlaciaItemFactory::blackscroll(self::parsePercentValue($entry["success"] ?? "{90-100}")),
            "renametag", "itemnametag" => GlaciaItemFactory::renametag(),

            "simpledp" => GlaciaItemFactory::dropPackage("simple_chest"),
            "uniquedp" => GlaciaItemFactory::dropPackage("unique_chest"),
            "elitedp" => GlaciaItemFactory::dropPackage("elite_chest"),
            "ultimatedp" => GlaciaItemFactory::dropPackage("ultimate_chest"),
            "legendarydp" => GlaciaItemFactory::dropPackage("legendary_chest"),
            "goddp" => GlaciaItemFactory::dropPackage("godly_chest"),

            "simpledust" => self::resolveDustItem("simple", $entry),
            "uniquedust" => self::resolveDustItem("unique", $entry),
            "elitedust" => self::resolveDustItem("elite", $entry),
            "ultimatedust" => self::resolveDustItem("exotic", $entry),
            "legendarydust" => self::resolveDustItem("legendary", $entry),

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
            'snowman_spawner', 'snow_golem_spawner' => GlaciaItemFactory::mobSpawner("snowman"),

            'dormant_sigil' => GlaciaItemFactory::questSigil('dormant'),
            'awakened_sigil' => GlaciaItemFactory::questSigil('awakened'),
            'ascendent_sigil' => GlaciaItemFactory::questSigil('ascendent'),
            'eternal_sigil' => GlaciaItemFactory::questSigil('eternal'),

            'astral_devourer' => GlaciaItemFactory::boss("astral_devourer"),

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
     * @param array<string, mixed> $entry
     */
    private static function resolveDustItem(string $group, array $entry): Item{
        if(isset($entry["percent"])){
            return GlaciaItemFactory::magicDust($group, self::parsePercentValue($entry["percent"]));
        }

        return GlaciaItemFactory::randomMagicDust($group);
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

            $custom = CustomEnchantments::getEnchantmentByName($enchantName);
            if($custom !== null){
                CustomEnchantmentManager::applyEnchantment($item, $custom, $level);
            }

            continue;
        }
    }

    private static function applyColor(Item $item, mixed $colorData): void{
        $color = self::parseColor($colorData);
        if($color === null){
            return;
        }

        if ($item instanceof Armor) {
            $item->setCustomColor($color);
        }
        
    }

    private static function parseColor(mixed $colorData): ?Color{
        if(is_string($colorData)){
            $colorKey = strtolower(trim($colorData));

            $named = match($colorKey){
                "white" => [255, 255, 255],
                "silver", "light_gray", "lightgrey" => [157, 157, 151],
                "gray", "grey" => [71, 79, 82],
                "black" => [29, 29, 33],
                "red" => [176, 46, 38],
                "orange" => [249, 128, 29],
                "yellow" => [254, 216, 61],
                "lime" => [128, 199, 31],
                "green" => [94, 124, 22],
                "light_blue" => [58, 179, 218],
                "cyan" => [22, 156, 156],
                "blue" => [60, 68, 170],
                "purple" => [137, 50, 184],
                "magenta" => [199, 78, 189],
                "pink" => [243, 139, 170],
                "brown" => [131, 84, 50],
                default => null
            };

            if($named !== null){
                return new Color($named[0], $named[1], $named[2]);
            }

            $hex = ltrim($colorKey, "#");
            if(preg_match('/^[0-9a-f]{6}$/i', $hex) === 1){
                return Color::fromRGB((int) hexdec($hex));
            }
        }

        if(is_array($colorData) && isset($colorData["r"], $colorData["g"], $colorData["b"])){
            return new Color(
                (int) $colorData["r"],
                (int) $colorData["g"],
                (int) $colorData["b"]
            );
        }

        return null;
    }

    private static function parsePercentValue(int|string $input): int{
        return max(1, min(100, GeneralUtils::parseRandomNumber($input)));
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function getDefaultStackCount(array $entry): int|string
    {
        $id = strtolower((string) ($entry["id"] ?? $entry["material"] ?? $entry["type"] ?? ""));

        return match ($id) {
            "money_note", "xp_note", "xpnote", "moneynote" => 1,
            default => $entry["amount"] ?? 1
        };
    }
}
