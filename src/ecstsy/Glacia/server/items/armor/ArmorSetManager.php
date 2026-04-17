<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\armor;

use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\player\Player;
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

    public function getDisplayName(string $setId): string {
        return $this->sets[$setId]['display'] ?? $setId;
    }

    public function isRegisteredSet(string $setId): bool {
        return isset($this->sets[$setId]);
    }
}