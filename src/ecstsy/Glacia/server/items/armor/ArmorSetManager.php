<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\armor;

use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\item\Armor;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\SingletonTrait;

final class ArmorSetManager {
    use SingletonTrait;

    /** @var array<string, array{pieces: string[], display: string}> */
    private array $sets = [
        'phantom' => [
            'pieces' => [
                'helmet' => 'phantom_helmet',
                'chestplate' => 'phantom_chestplate',
                'leggings' => 'phantom_leggings',
                'boots' => 'phantom_boots',
            ],
            'display' => 'Phantom Set'
        ],
        'yeti' => [
            'pieces' => [
                'helmet' => 'yeti_helmet',
                'chestplate' => 'yeti_chestplate',
                'leggings' => 'yeti_leggings',
                'boots' => 'yeti_boots',
            ],
            'display' => 'Yeti Set'
        ],
        'yijki' => [
            'pieces' => [
                'helmet' => 'yijki_helmet',
                'chestplate' => 'yijki_chestplate',
                'leggings' => 'yijki_leggings',
                'boots' => 'yijki_boots',
            ],
            'display' => 'Yijki Set'
        ]
    ];

    public function __construct() {
        self::setInstance($this);
    }

    public function isCustomArmor(Item $item): bool {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return false;
        }

        return $glacia->getInt("customArmor", 0) === 1;
    }

    public function getArmorSetId(Item $item): ?string {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return null;
        }

        $set = $glacia->getString("armorSet", "");
        return $set !== "" ? $set : null;
    }

    public function getArmorPiece(Item $item): ?string {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return null;
        }

        $piece = $glacia->getString("armorPiece", "");
        return $piece !== "" ? $piece : null;
    }

    public function getFullSet(Player $player): ?string {
        $armorInventory = $player->getArmorInventory();

        $helmet = $armorInventory->getHelmet();
        $chestplate = $armorInventory->getChestplate();
        $leggings = $armorInventory->getLeggings();
        $boots = $armorInventory->getBoots();

        $items = [
            'helmet' => $helmet,
            'chestplate' => $chestplate,
            'leggings' => $leggings,
            'boots' => $boots
        ];

        $firstSet = null;

        foreach ($items as $piece => $item) {
            if ($item->isNull()) {
                return null;
            }

            if (!$this->isCustomArmor($item)) {
                return null;
            }

            $setId = $this->getArmorSetId($item);
            $armorPiece = $this->getArmorPiece($item);
            $glaciaItem = $item->getNamedTag()->getCompoundTag("Glacia")?->getString("glaciaItem", "");

            if ($setId === null || $armorPiece !== $piece) {
                return null;
            }

            if ($firstSet === null) {
                $firstSet = $setId;
            }

            if ($setId !== $firstSet) {
                return null;
            }

            if (!isset($this->sets[$setId]['pieces'][$piece])) {
                return null;
            }

            if ($glaciaItem !== $this->sets[$setId]['pieces'][$piece]) {
                return null;
            }
        }

        return $firstSet;
    }

    public function hasFullSet(Player $player, string $setId): bool {
        return $this->getFullSet($player) === $setId;
    }

    public function syncPlayerCustomArmorLore(Player $player): void
    {
        foreach ([
            $player->getInventory(),
            $player->getArmorInventory(),
            $player->getOffHandInventory(),
            $player->getEnderInventory(),
        ] as $inventory) {
            $this->syncInventoryCustomArmorLore($inventory);
        }
    }

    public function getDisplayName(string $setId): string {
        return $this->sets[$setId]['display'] ?? $setId;
    }

    public function isRegisteredSet(string $setId): bool {
        return isset($this->sets[$setId]);
    }

    private function syncInventoryCustomArmorLore(Inventory $inventory): void
    {
        for ($slot = 0; $slot < $inventory->getSize(); $slot++) {
            $item = $inventory->getItem($slot);
            $updated = $this->syncCustomArmorAbilityLore($item);
            if (!$updated->equalsExact($item)) {
                $inventory->setItem($slot, $updated);
            }
        }
    }

    private function syncCustomArmorAbilityLore(Item $item): Item
    {
        if (!$this->isCustomArmor($item)) {
            return $item;
        }

        $setId = $this->getArmorSetId($item);
        $abilityLine = $setId !== null ? $this->getAbilityLoreLine($setId) : null;
        if ($abilityLine === null) {
            return $item;
        }

        $lore = $item->getLore();
        $updatedLore = [];
        $inserted = false;
        foreach ($lore as $line) {
            $cleanLine = strtolower(trim(TextFormat::clean($line)));
            if ($this->isAbilityLoreLine($cleanLine)) {
                continue;
            }
            if (!$inserted && str_contains($cleanLine, "requires all 4 ")) {
                $updatedLore[] = $abilityLine;
                $inserted = true;
            }
            $updatedLore[] = $line;
        }

        if (!$inserted) {
            if ($updatedLore !== [] && trim(TextFormat::clean((string) end($updatedLore))) !== "") {
                $updatedLore[] = "";
            }
            $updatedLore[] = $abilityLine;
        }

        $updated = clone $item;
        $updated->setLore($updatedLore);
        return $updated;
    }

    private function getAbilityLoreLine(string $setId): ?string
    {
        return match ($setId) {
            "phantom" => TextFormat::colorize(sprintf(
                "&r&l&5* &r&5Dreadveil Rend: +%s%% damage, +%s%% with weapon, %s%% rend proc.",
                $this->formatPercent(EnchantTuningManager::getArmorSetFloat('phantom', 'attack-bonus-fraction', 0.20) * 100),
                $this->formatPercent(EnchantTuningManager::getArmorSetFloat('phantom', 'weapon-bonus-fraction', 0.08) * 100),
                $this->formatPercent(EnchantTuningManager::getArmorSetFloat('phantom', 'rend-proc-chance-percent', 18))
            )),
            "yeti" => TextFormat::colorize(sprintf(
                "&r&l&b* &r&bPermafrost Pulse: up to %s%% reduction, %s%% pulse proc.",
                $this->formatPercent(EnchantTuningManager::getArmorSetFloat('yeti', 'damage-reduction-cap', 0.90) * 100),
                $this->formatPercent(EnchantTuningManager::getArmorSetFloat('yeti', 'pulse-proc-chance-percent', 14))
            )),
            "yijki" => TextFormat::colorize(sprintf(
                "&r&l&f* &r&fStormcaller: up to %s%% reduction, %s%% lightning revenge proc.",
                $this->formatPercent(EnchantTuningManager::getArmorSetFloat('yijki', 'damage-reduction-cap', 0.05) * 100),
                $this->formatPercent(EnchantTuningManager::getArmorSetFloat('yijki', 'revenge-proc-chance-percent', 6))
            )),
            default => null,
        };
    }

    private function isAbilityLoreLine(string $cleanLine): bool
    {
        return str_contains($cleanLine, "dreadveil rend:")
            || str_contains($cleanLine, "permafrost pulse:")
            || str_contains($cleanLine, "stormcaller:")
            || str_contains($cleanLine, "enemies deal 20% less damage to you")
            || str_contains($cleanLine, "tempest surge passive ability");
    }

    private function formatPercent(float $value): string
    {
        $rounded = round($value, 2);
        if (abs($rounded - round($rounded)) < 0.001) {
            return (string) (int) round($rounded);
        }

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }
}
