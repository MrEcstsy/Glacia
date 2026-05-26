<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\farming;

use ecstsy\Glacia\server\collectionchests\HarvesterCropRegistry;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat as C;

final class FarmItemLoreHelper {

    private const LORE_PREFIX = "Requires Farming Level ";

    public static function applyToItem(Item $item): Item
    {
        if ($item->isNull()) {
            return $item;
        }
        if (!FarmLevelRequirementManager::isEnabled()) {
            return $item;
        }

        $family = HarvesterCropRegistry::getFarmFamilyForItem($item);
        if ($family === null) {
            return $item;
        }

        $required = FarmLevelRequirementManager::getRequiredLevelForFamily($family);
        $line = C::colorize("&r&7" . self::LORE_PREFIX . "&f" . $required);

        $out = clone $item;
        $lore = self::stripLines($out->getLore());
        $lore[] = $line;
        $out->setLore($lore);

        return $out;
    }

    /**
     * @param Item[] $drops
     * @return Item[]
     */
    public static function applyToDrops(array $drops): array
    {
        if (!FarmLevelRequirementManager::isEnabled()) {
            return $drops;
        }

        $out = [];
        foreach ($drops as $drop) {
            if ($drop instanceof Item) {
                $out[] = self::applyToItem($drop);
            }
        }

        return $out;
    }

    /**
     * @param string[] $lore
     * @return string[]
     */
    private static function stripLines(array $lore): array
    {
        return array_values(array_filter($lore, static function(string $line): bool {
            return stripos(C::clean($line), self::LORE_PREFIX) === false;
        }));
    }
}
