<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\items\armor\ArmorSetEnchants;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GlaciaItemFactory {

    public static array $definitions = [];

    public static function create(string $key, array $args = []): Item {
        $def = self::$definitions[$key] ?? throw new \InvalidArgumentException("Unknown item definition: $key");

        /** @var Item $item */
        $item = clone $def['material'];

        $item->setCustomName(C::colorize(self::template($def['name'] ?? '', $args)));
        $loreLines = [];
        foreach ($def['lore'] ?? [] as $line) {
            $loreLines[] = C::colorize(self::template((string) $line, $args));
        }
        if ($loreLines) {
            $item->setLore($loreLines);
        }

        $root = $item->getNamedTag();
        $aTag = new CompoundTag();
        foreach ($def['nbt'] ?? [] as $tag => $valueTpl) {
            $resolved = self::template((string) $valueTpl, $args);
            if (is_numeric($resolved) && (string)(int)$resolved === $resolved) {
                $aTag->setInt($tag, (int)$resolved);
            } else {
                $aTag->setString($tag, $resolved);
            }
        }
        $root->setTag("Glacia", $aTag);

        return $item;
    }

    public static function bankNote(?Player $player, int $amount): Item {
        $signer = $player?->getName() ?? 'Ethereal Hub';
        return self::create('bank_note', [
            'amount' => number_format($amount),
            'rawAmount' => $amount,
            'signer' => $signer
        ]);
    }

    public static function xpNote(?Player $player, int $amount): Item {
        $signer = $player?->getName() ?? 'Ethereal Hub';
        return self::create('xpnote', [
            'amount' => number_format($amount),
            'rawAmount' => $amount,
            'signer' => $signer
        ]);
    }

    public static function boss(string $boss): Item {
        return self::create("boss_$boss", ['boss' => strtolower($boss)]);
    }

    public static function enchantmentBook(CustomEnchantment $enchantment, int $level = 1, ?int $forcedSuccessChance = null, ?int $forcedDestroyChance = null): ?Item {
        $success = $forcedSuccessChance !== null ? $forcedSuccessChance : mt_rand(1, 100);
        $destroy = $forcedDestroyChance !== null ? $forcedDestroyChance : mt_rand(1, 100);

        $appliesTo = array_map('ucfirst', $enchantment->getApplicableItems());

        return self::create('enchantment_book', [
            'enchantment' => CustomEnchantments::getEnchantmentDisplayName($enchantment->getName(), C::colorize(Groups::translateGroupToColor($enchantment->getRarity()))),
            'enchant-no-color' => $enchantment->getName(),
            'level' => $level,
            'roman-level' => GeneralUtils::getRomanNumeral($level),
            'success' => $success,
            'destroy' => $destroy,
            'description' => $enchantment->getDescription(),
            'group-color' => C::colorize(Groups::translateGroupToColor($enchantment->getRarity())),
            'applies-to' => implode(", ", $appliesTo),
        ]);
    }

    public static function enchantOrb(string $type, int $max, int $new, int $success): ?Item {
        if (!in_array($type, ['weapon', 'armor', 'tool'])) {
            throw new \InvalidArgumentException("Invalid orb type: $type");
        }

        return self::create('orb', [
            'type' => ucfirst($type),
            'upper-type' => strtoupper($type),
            'low_type' => strtolower($type),
            'max' => $max,
            'new' => $new,
            'success' => $success,
        ]);
    }

    public static function whitescroll(): Item{ 
        return self::create('whitescroll');
    }

    public static function blackscroll(?int $success): Item {
        if ($success === null) {
            $success = mt_rand(1, 100);
        }

        return self::create('blackscroll', [
            'success' => $success
        ]);
    }

    public static function renametag(): Item {
        return self::create('renametag');
    }

    public static function randomEnchantBook(string $group): Item {
        return self::create('random_book', [
            'group-name' => ucfirst($group),
            'group-color' => C::colorize(Groups::translateGroupToColor(Groups::getGroupId($group))),
            'group' => $group,
        ]);
    }

    /**
     * Available packages: simple_chest, unique_chest, elite_chest, ultimate_chest, legendary_chest, godly_chest
     */
    public static function dropPackage(string $package): Item {
        return self::create('ethereal_' . $package);
    }

    public static function mysteryMobSpawner(): Item {
        return self::create('mysterymobspawner');
    }

    public static function heroicMysteryMobSpawner(): Item {
        return self::create('heroicmysterymobspawner');
    }

    public static function randomMagicDust(string $group): Item {
        return self::create('random_magicdust', [
            'group-name' => ucfirst($group),
            'group-color' => C::colorize(Groups::translateGroupToColor(Groups::getGroupId($group))),
            'group' => $group,
        ]);
    }


    public static function primalDust(string $group, int $percentage = 100): Item {
        return self::create('primal_dust', [
            'group-name' => ucfirst($group),
            'group-color' => C::colorize(Groups::translateGroupToColor(Groups::getGroupId($group))),
            'group' => $group,
            'dust-percent' => $percentage
        ]);
    }

    public static function magicDust(string $group, int $percentage = 100): Item {
        return self::create('magicdust', [
            'group-name' => ucfirst($group),
            'group-color' => C::colorize(Groups::translateGroupToColor(Groups::getGroupId($group))),
            'group' => $group,
            'dust-percent' => $percentage
        ]);
    }

    public static function mysteryDust(): Item {
        return self::create('mysterydust');
    } 

    public static function tnt(string $type): Item {
        return self::create($type, [
            'type' => $type
        ]);
    }

    public static function cegg(string $type): Item {
        return self::create($type, [
            'type' => $type
        ]);
    }

    public static function customArmor(string $set, ?string $piece = null, bool $enchanted = false): Item|array {
        $set = strtolower($set);
        $piece = $piece !== null ? strtolower($piece) : "full";

        return match ($piece) {
            "helmet", "chestplate", "leggings", "boots" => self::buildCustomArmorPiece($set, $piece, $enchanted),
            "full", "set" => self::customArmorSet($set, $enchanted),
            default => throw new \InvalidArgumentException("Invalid armor piece '{$piece}'. Use helmet, chestplate, leggings, boots, or full.")
        };
    }

    public static function customArmorSet(string $set, bool $enchanted = false): array {
        $set = strtolower($set);

        return [
            self::buildCustomArmorPiece($set, "helmet", $enchanted),
            self::buildCustomArmorPiece($set, "chestplate", $enchanted),
            self::buildCustomArmorPiece($set, "leggings", $enchanted),
            self::buildCustomArmorPiece($set, "boots", $enchanted),
        ];
    }

    private static function buildCustomArmorPiece(string $set, string $piece, bool $enchanted = false): Item {
        $identifier = "{$set}_{$piece}";

        if (!isset(self::$definitions[$identifier])) {
            throw new \InvalidArgumentException("Unknown custom armor piece '{$identifier}'.");
        }

        $item = self::create($identifier, [
            'type' => $identifier
        ]);

        self::addArmorBaseEnchantments($item);

        if ($enchanted) {
            self::applyCustomArmorEnchantments($item, $set, $piece);
        }

        return $item;
    }

    private static function applyCustomArmorEnchantments(Item $item, string $set, string $piece): void {
        self::addArmorBaseEnchantments($item);

        switch ($set) {
            case "phantom":
                ArmorSetEnchants::apply($item, "phantom", $piece);
                break;
            case "yeti":
                ArmorSetEnchants::apply($item, "yeti", $piece);
                break;
            case "yijki":
                ArmorSetEnchants::apply($item, "yijki", $piece);
                break;
        }
    }

    private static function addArmorBaseEnchantments(Item $item): void {
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4));
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));
    }

    public static function customWeapon(string $set, string $type = "sword"): Item {
        $set = strtolower($set);
        $type = strtolower($type);

        $identifier = "{$set}_{$type}";

        if (!isset(self::$definitions[$identifier])) {
            throw new \InvalidArgumentException("Unknown custom weapon '{$identifier}'.");
        }

        $item = self::create($identifier, [
            'type' => $identifier
        ]);

        self::addWeaponBaseEnchantments($item, $type);

        return $item;
    }

    private static function addWeaponBaseEnchantments(Item $item, string $type): void {
        switch (strtolower($type)) {
            case 'bow':
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::POWER(), 5));
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FLAME(), 2));
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::INFINITY(), 1));
                break;

            default:
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 5));
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));
                break;
        }
    }

    public static function mobSpawner(string $type): Item {
        return StringToItemParser::getInstance()->parse($type . "_spawner");
    }

    public static function questSigil(string $tier, int $questIndex = 0): Item {
        $player = null; 
        $data = QuestInstance::getTierData($tier);

        if ($data === null) {
            throw new \InvalidArgumentException("Unknown quest sigil tier: {$tier}");
        }

        $material = \pocketmine\item\StringToItemParser::getInstance()->parse($data["material"]) ?? \pocketmine\item\VanillaItems::AMETHYST_SHARD();

        $tierColor = match($tier) {
            "dormant" => "&b",
            "awakened" => "&5",
            "ascendent" => "&e",
            "eternal" => "&c",
            default => "&7",
        };

        $symbol = match($tier) {
            "dormant" => "❄",
            "awakened" => "✦",
            "ascendent" => "✪",
            "eternal" => "☯",
            default => "•",
        };

        $quest = $data["quests"][$questIndex] ?? null;
        if ($quest === null) {
            throw new \InvalidArgumentException("Invalid quest index {$questIndex} for sigil tier {$tier}");
        }

        $item = $material->setCustomName(C::colorize("&r&7{$symbol} {$tierColor}{$data['display']} Sigil &7{$symbol}"));

        $nbt = $item->getNamedTag();
        $nbt->setString("glacia_quest_tier", $tier);
        $nbt->setInt("glacia_quest_index", $questIndex);
        $nbt->setString("glacia_quest_type", $quest["type"]);
        $nbt->setString("glacia_quest_progress", json_encode(array_fill(0, count($quest["objectives"]), 0)));
        $item->setNamedTag($nbt);

        QuestInstance::updateQuestLore($item);

        return $item;
    }

    public static function slotTicket(): Item {
        return self::create("slot_ticket");
    }

    public static function getGlaciaIdentifier(Item $item): string
    {
        return $item->getNamedTag()->getCompoundTag("Glacia")?->getString("glaciaItem", "") ?? "";
    }

    public static function isGlaciaItem(Item $item, string $identifier): bool
    {
        return self::getGlaciaIdentifier($item) === $identifier;
    }

    private static function template(string $template, array $args): string {
        foreach ($args as $k => $v) {
            $template = str_replace("{" . $k . "}", (string)$v, $template);
            $template = str_replace("{{" . $k . "}}", (string)$v, $template);
        }

        return $template;
    }
}
