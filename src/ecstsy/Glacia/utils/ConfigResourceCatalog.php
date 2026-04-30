<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;

final class ConfigResourceCatalog {

    /**
     * @return string[]
     */
    public static function getBundledConfigFiles(): array {
        return array_merge(
            [
                "items.yml",
                "blackmarket.yml",
                "shop.yml",
                "sell.yml",
                "kits.yml",
                "warlords.yml",
                "slotbot.yml",
                "enchanter.yml",
                "auctionhouse.yml",
                "quests.yml",
                "spawners.yml",
                "misc.yml",
                "messages.yml",
                "announcer.yml",
                "zone_mobs.yml",
                "custom_mob_items.yml",
                "faction_upgrades.yml",
                "faction_name_blocklist.yml",
                "pets.yml",
                "runes.yml",
                "fund.yml",
                "envoy.yml",
                "genbuckets.yml",
                "challenges.yml",
                "levels.yml",
                "zones.yml",
                "capturepoints.yml",
                "combat.yml",
                "ce_values.yml",
                "fishing.yml",
                "titles.yml",
                "item_overrides.yml",
                "scoreboards.yml",
                "dimension_themes.yml",
                "admin_bazaar.yml",
                "cc_system.yml",
                "essences.yml",
                "fragments.yml",
                "config.yml",
                "dps/Simple.yml",
                "dps/Unique.yml",
                "dps/Elite.yml",
                "dps/Exotic.yml",
                "dps/Legendary.yml",
                "dps/Godly.yml",
            ],
            LootboxRepository::getBundledResourceFiles(),
            EtherealCrateRepository::getBundledResourceFiles()
        );
    }
}
