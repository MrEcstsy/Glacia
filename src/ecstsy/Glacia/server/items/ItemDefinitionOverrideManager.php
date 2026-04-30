<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;

final class ItemDefinitionOverrideManager{

    public static function apply(): void{
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "item_overrides.yml");
        $items = (array) ($config?->get("items", []) ?? []);

        foreach($items as $key => $override){
            if(!is_array($override) || !isset(GlaciaItemFactory::$definitions[$key])){
                continue;
            }

            if(isset($override["name"]) && is_string($override["name"])){
                GlaciaItemFactory::$definitions[$key]["name"] = $override["name"];
            }

            if(isset($override["lore"]) && is_array($override["lore"])){
                GlaciaItemFactory::$definitions[$key]["lore"] = array_values(array_map(
                    static fn(mixed $line): string => (string) $line,
                    $override["lore"]
                ));
            }

            if(isset($override["nbt"]) && is_array($override["nbt"])){
                $existing = (array) (GlaciaItemFactory::$definitions[$key]["nbt"] ?? []);
                GlaciaItemFactory::$definitions[$key]["nbt"] = $override["nbt"] + $existing;
            }
        }
    }
}

