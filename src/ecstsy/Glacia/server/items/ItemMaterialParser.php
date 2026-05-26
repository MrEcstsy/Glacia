<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items;

use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;

final class ItemMaterialParser
{
    /**
     * @param string[] $identifiers Tried in order until one parses.
     */
    public static function parse(string ...$identifiers): ?Item
    {
        $candidates = [];

        foreach ($identifiers as $identifier) {
            $identifier = trim($identifier);
            if ($identifier === "") {
                continue;
            }

            $candidates[] = $identifier;
            if (!str_contains($identifier, ":")) {
                $candidates[] = "minecraft:" . $identifier;
            }
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $parsed = StringToItemParser::getInstance()->parse($candidate);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }
}
