<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\collectionchests;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;

/**
 * Harvester chest loot + configurable farming skill families ({@see \ecstsy\Glacia\server\farming\FarmLevelRequirementManager}).
 */
final class HarvesterCropRegistry {

    public const PLAYER_THROW_PICKUP_DELAY_THRESHOLD = 32;

    /** @var array<int, true>|null */
    private static ?array $harvestBlockIds = null;
    /** @var array<int, true>|null */
    private static ?array $harvestItemIds = null;
    /** @var array<int, string>|null block type id → family key */
    private static ?array $farmFamilyForBlockType = null;
    /** @var array<int, string>|null item type id → family key */
    private static ?array $farmFamilyForItemType = null;

    public static function isHarvestFarmBlock(Block $block): bool
    {
        self::init();
        return isset(self::$harvestBlockIds[$block->getTypeId()]);
    }

    public static function isHarvestFarmItem(Item $item): bool
    {
        if ($item->isNull()) {
            return false;
        }
        self::init();
        return isset(self::$harvestItemIds[$item->getTypeId()]);
    }

    /**
     * Config key under levels.yml farming-requirements.crops — null if block is not a gated farm crop.
     */
    public static function getFarmFamilyForBlock(Block $block): ?string
    {
        self::init();
        return self::$farmFamilyForBlockType[$block->getTypeId()] ?? null;
    }

    /**
     * Config key for items (seeds, produce, placement items). Null if not a gated farm item.
     */
    public static function getFarmFamilyForItem(Item $item): ?string
    {
        if ($item->isNull()) {
            return null;
        }
        self::init();
        return self::$farmFamilyForItemType[$item->getTypeId()] ?? null;
    }

    /**
     * Item entities from {@see ItemSpawnEvent}: only vacuum likely world drops (not player Q drops).
     */
    public static function shouldVacuumLooseFarmItem(ItemEntity $entity, Item $item): bool
    {
        if ($item->isNull() || $item->getCount() <= 0) {
            return false;
        }
        if ($entity->getThrower() !== "") {
            return false;
        }
        if ($entity->getPickupDelay() >= self::PLAYER_THROW_PICKUP_DELAY_THRESHOLD) {
            return false;
        }
        return self::isHarvestFarmItem($item);
    }

    private static function init(): void
    {
        if (self::$harvestBlockIds !== null) {
            return;
        }

        /** @var array<string, int[]> */
        $blockFamilies = [
            "wheat" => [BlockTypeIds::WHEAT],
            "carrots" => [BlockTypeIds::CARROTS],
            "potatoes" => [BlockTypeIds::POTATOES],
            "beetroots" => [BlockTypeIds::BEETROOTS],
            "melon" => [BlockTypeIds::MELON],
            "melon_stem" => [BlockTypeIds::MELON_STEM],
            "pumpkin" => [BlockTypeIds::PUMPKIN],
            "pumpkin_stem" => [BlockTypeIds::PUMPKIN_STEM],
            "carved_pumpkin" => [BlockTypeIds::CARVED_PUMPKIN, BlockTypeIds::LIT_PUMPKIN],
            "sugar_cane" => [BlockTypeIds::SUGARCANE],
            "cactus" => [BlockTypeIds::CACTUS, BlockTypeIds::CACTUS_FLOWER],
            "nether_wart" => [BlockTypeIds::NETHER_WART],
            "nether_wart_block" => [BlockTypeIds::NETHER_WART_BLOCK],
            "cocoa" => [BlockTypeIds::COCOA_POD],
            "bamboo" => [BlockTypeIds::BAMBOO, BlockTypeIds::BAMBOO_SAPLING],
            "bamboo_block" => [BlockTypeIds::BAMBOO_BLOCK],
            "sweet_berries" => [BlockTypeIds::SWEET_BERRY_BUSH],
            "cave_vines" => [BlockTypeIds::CAVE_VINES],
            "torchflower" => [BlockTypeIds::TORCHFLOWER, BlockTypeIds::TORCHFLOWER_CROP],
            "pitcher" => [BlockTypeIds::PITCHER_CROP, BlockTypeIds::DOUBLE_PITCHER_CROP, BlockTypeIds::PITCHER_PLANT],
            "chorus" => [BlockTypeIds::CHORUS_FLOWER, BlockTypeIds::CHORUS_PLANT],
            "mushroom" => [BlockTypeIds::RED_MUSHROOM, BlockTypeIds::BROWN_MUSHROOM],
            "mushroom_block" => [BlockTypeIds::RED_MUSHROOM_BLOCK, BlockTypeIds::BROWN_MUSHROOM_BLOCK],
            "crimson_fungus" => [BlockTypeIds::CRIMSON_FUNGUS],
            "warped_fungus" => [BlockTypeIds::WARPED_FUNGUS],
            "shroomlight" => [BlockTypeIds::SHROOMLIGHT],
        ];

        /** @var array<string, int[]> */
        $itemFamilies = [
            "wheat" => [ItemTypeIds::WHEAT, ItemTypeIds::WHEAT_SEEDS],
            "carrots" => [ItemTypeIds::CARROT, ItemTypeIds::GOLDEN_CARROT],
            "potatoes" => [ItemTypeIds::POTATO, ItemTypeIds::BAKED_POTATO, ItemTypeIds::POISONOUS_POTATO],
            "beetroots" => [ItemTypeIds::BEETROOT, ItemTypeIds::BEETROOT_SEEDS],
            "melon" => [ItemTypeIds::MELON, ItemTypeIds::MELON_SEEDS, ItemTypeIds::GLISTERING_MELON],
            "pumpkin_stem" => [ItemTypeIds::PUMPKIN_SEEDS],
            "sweet_berries" => [ItemTypeIds::SWEET_BERRIES],
            "cave_vines" => [ItemTypeIds::GLOW_BERRIES],
            "sugar_cane" => [ItemTypeIds::SUGAR],
            "cocoa" => [ItemTypeIds::COCOA_BEANS],
            "bamboo" => [ItemTypeIds::BAMBOO],
            "torchflower" => [ItemTypeIds::TORCHFLOWER_SEEDS],
            "pitcher" => [ItemTypeIds::PITCHER_POD],
            "chorus" => [ItemTypeIds::CHORUS_FRUIT, ItemTypeIds::POPPED_CHORUS_FRUIT],
            "dried_kelp" => [ItemTypeIds::DRIED_KELP],
            "egg" => [ItemTypeIds::EGG],
            "apple" => [ItemTypeIds::APPLE],
            "mushroom" => [
                VanillaBlocks::RED_MUSHROOM()->asItem()->getTypeId(),
                VanillaBlocks::BROWN_MUSHROOM()->asItem()->getTypeId(),
            ],
        ];

        $blockTemplates = [
            "nether_wart" => VanillaBlocks::NETHER_WART()->asItem(),
            "melon" => VanillaBlocks::MELON()->asItem(),
            "pumpkin" => VanillaBlocks::PUMPKIN()->asItem(),
            "carved_pumpkin" => VanillaBlocks::CARVED_PUMPKIN()->asItem(),
            "sugar_cane" => VanillaBlocks::SUGARCANE()->asItem(),
            "cactus" => VanillaBlocks::CACTUS()->asItem(),
            "bamboo_block" => VanillaBlocks::BAMBOO_BLOCK()->asItem(),
            "shroomlight" => VanillaBlocks::SHROOMLIGHT()->asItem(),
            "crimson_fungus" => VanillaBlocks::CRIMSON_FUNGUS()->asItem(),
            "warped_fungus" => VanillaBlocks::WARPED_FUNGUS()->asItem(),
        ];
        foreach ($blockTemplates as $family => $tpl) {
            $itemFamilies[$family][] = $tpl->getTypeId();
        }

        self::$harvestBlockIds = [];
        self::$farmFamilyForBlockType = [];
        foreach ($blockFamilies as $family => $typeIds) {
            foreach ($typeIds as $typeId) {
                self::$harvestBlockIds[$typeId] = true;
                self::$farmFamilyForBlockType[$typeId] = $family;
            }
        }

        self::$harvestItemIds = [];
        self::$farmFamilyForItemType = [];
        foreach ($itemFamilies as $family => $typeIds) {
            foreach ($typeIds as $typeId) {
                self::$harvestItemIds[$typeId] = true;
                self::$farmFamilyForItemType[$typeId] = $family;
            }
        }
    }
}
