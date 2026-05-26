<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;

final class ConfigResourceCatalog {

    /**
     * Every bundled YAML except {@code dps/}, {@code lootboxes/}, and {@code etherealcrates/}.
     * Bump {@code schema-version} only in plugin resources when the canonical shape changes.
     *
     * @return string[]
     */
    public static function getSchemaTrackedConfigFiles(): array {
        $out = [];
        foreach (self::getBundledConfigFiles() as $path) {
            $p = str_replace('\\', '/', $path);
            if (str_starts_with($p, 'dps/')) {
                continue;
            }
            if (str_starts_with($p, 'lootboxes/')) {
                continue;
            }
            if (str_starts_with($p, 'etherealcrates/')) {
                continue;
            }
            $out[] = $p;
        }

        sort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /**
     * Deep additive merge only (missing keys from defaults added; nothing removed or overwritten).
     * Use for configs where staff add open-ended keys (items, messages, guide, …).
     */
    public static function isSchemaAdditiveFile(string $relativePath): bool {
        $p = str_replace('\\', '/', $relativePath);

        return in_array($p, [
            'items.yml',
            'messages.yml',
            'guide.yml',
            'enchant_descriptions.yml',
            'custom_mob_items.yml',
            'admin_items.yml',
            'item_overrides.yml',
            'titles.yml',
            'faction_name_blocklist.yml',
            // Open-ended recipe IDs under recipes — strict merge would drop staff-only fragments every schema bump.
            'fragments.yml',
        ], true);
    }

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
                "world_events.yml",
                "bosses.yml",
                "custom_mob_items.yml",
                "admin_items.yml",
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
                "enchant_descriptions.yml",
                "fishing.yml",
                "regions.yml",
                "titles.yml",
                "item_overrides.yml",
                "scoreboards.yml",
                "floating_texts.yml",
                "dimension_themes.yml",
                "admin_bazaar.yml",
                "cc_system.yml",
                "essences.yml",
                "fragments.yml",
                "redstone.yml",
                "voting.yml",
                "tebex.yml",
                "config.yml",
                "guide.yml",
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
