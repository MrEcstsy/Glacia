<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\gkits;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;

final class WarlordRepository
{
    /**
     * @return array<string, mixed>
     */
    private static function getConfig(): array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "warlords.yml");
        $data = $config?->getAll();
        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getWarlords(): array
    {
        $config = self::getConfig();
        $entries = $config["warlords"] ?? $config;
        if (!is_array($entries)) {
            return [];
        }

        $normalized = [];
        foreach ($entries as $key => $warlord) {
            if (!is_array($warlord)) {
                continue;
            }

            $warlordId = strtolower((string) ($warlord["id"] ?? $key));
            if ($warlordId === "") {
                continue;
            }

            $warlord["id"] = $warlordId;
            $normalized[$warlordId] = $warlord;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getDefaults(): array
    {
        $config = self::getConfig();
        $defaults = $config["defaults"] ?? [];

        if (!is_array($defaults)) {
            $defaults = [];
        }

        return array_replace_recursive([
            "summoner-name" => "&r&8&lWarlord&7: &r&f{name}",
            "summoner-lore" => [
                "&r&7Summons a warlord equipped with",
                "&r&7the &f{name} &r&7equipment.",
                "",
                "&r&7Defeat this warlord for a guaranteed",
                "&r&7drop of the &f/gkit item(s) &7and a",
                "&r&fchance &7to get a &f/gkit Redemption Gem",
                "&r&7that can be used to unlock the /gkit",
                "&r&7on your account for regular use!",
                "",
                "&r&l&cWARNING&r&c: &7This boss can only be summoned in warzone."
            ],
            "entity-name" => "&r&8Warlord &7| &r&f{name}",
            "spawn-title" => "&r&8Warlord Summoned",
            "spawn-subtitle" => "&r&f{name} &7has answered the challenge.",
            "spawn-message" => "&r&8A fractured warlord of &f{name} &r&8has emerged in the Warzone.",
            "death-message" => "&r&f{killer} &r&7defeated the &8Warlord &7of &f{name}&7.",
            "gem-redeem-message" => "&r&aYou have {verb} &f{name}&r&a to level &f{level}&a.",
            "gem-redeem-title" => "&r&bGKit Empowered",
            "gem-redeem-subtitle" => "&r&7Level &f{level}",
            "health" => 80,
            "damage" => 9,
            "move-speed" => 0.24,
            "attack-reach" => 2.2,
            "attack-cooldown" => 9,
            "target-range" => 24,
            "jump-height" => 0.42,
            "gear-drop-chance" => 65,
            "gem-drop-chance" => 35,
        ], $defaults);
    }

    public static function getWarlordById(string $id): ?array
    {
        $id = strtolower(trim($id));
        if ($id === "") {
            return null;
        }

        return self::getWarlords()[$id] ?? null;
    }

    public static function getForKit(array $kit): array
    {
        $kitId = strtolower((string) ($kit["id"] ?? ""));
        $configuredId = strtolower((string) ($kit["warlord"] ?? $kit["warlord-id"] ?? $kitId));

        $legacyFallback = self::getLegacyFallback($kit);
        $defaults = self::getDefaults();
        $profile = self::getWarlordById($configuredId) ?? [];

        $merged = array_replace_recursive($legacyFallback, $defaults, $profile);
        $merged["id"] = strtolower((string) ($merged["id"] ?? $configuredId));
        $merged["gkit"] = strtolower((string) ($merged["gkit"] ?? $kitId));

        return self::resolvePlaceholders($merged, [
            "id" => (string) $merged["id"],
            "gkit" => (string) $merged["gkit"],
            "name" => (string) ($merged["display"] ?? $legacyFallback["display"] ?? ucfirst($kitId)),
            "display" => (string) ($merged["display"] ?? $legacyFallback["display"] ?? ucfirst($kitId)),
        ]);
    }

    private static function getLegacyFallback(array $kit): array
    {
        $display = (string) ($kit["display"] ?? ucfirst((string) ($kit["id"] ?? "Warlord")));

        return [
            "id" => strtolower((string) ($kit["id"] ?? "warlord")),
            "gkit" => strtolower((string) ($kit["id"] ?? "warlord")),
            "display" => $display,
            "summoner-name" => (string) ($kit["summoner-name"] ?? "&r&8&lWarlord&7: &r&f{name}"),
            "summoner-lore" => [
                "&r&7Call forth the shattered champion of",
                "&r&f{name} &r&7and challenge it in Warzone.",
                "",
                "&r&cWarzone Only",
                "&r&7Defeat it for a chance at a",
                "&r&bGKit Gem &7or a piece of its gear."
            ],
            "entity-name" => (string) ($kit["fallen-name"] ?? "&r&8Warlord &7| &r&f{name}"),
            "spawn-title" => "&r&8Warlord Summoned",
            "spawn-subtitle" => "&r&f{name} &7has answered the challenge.",
            "spawn-message" => "&r&8A fractured warlord of &f{name} &r&8has emerged in the Warzone.",
            "death-message" => "&r&f{killer} &r&7defeated the &8Warlord &7of &f{name}&7.",
            "gem-redeem-message" => "&r&aYour bond with &f{name} &r&ahas strengthened.",
            "gem-redeem-title" => "&r&bGKit Empowered",
            "gem-redeem-subtitle" => "&r&f{name}",
            "health" => max(20, (int) ($kit["fallen-health"] ?? 80)),
            "damage" => max(1, (int) ($kit["fallen-damage"] ?? 9)),
            "move-speed" => 0.24,
            "attack-reach" => 2.2,
            "attack-cooldown" => 9,
            "target-range" => 24,
            "jump-height" => 0.42,
            "gear-drop-chance" => max(0, min(100, (int) ($kit["gear-drop-chance"] ?? 35))),
            "gem-drop-chance" => max(0, min(100, (int) ($kit["gem-drop-chance"] ?? 65))),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private static function resolvePlaceholders(array $data, array $context): array
    {
        $resolved = [];
        foreach ($data as $key => $value) {
            $resolved[$key] = self::resolveValue($value, $context);
        }

        return $resolved;
    }

    private static function resolveValue(mixed $value, array $context): mixed
    {
        if (is_string($value)) {
            return str_replace(
                ["{name}", "{display}", "{gkit}", "{id}"],
                [
                    (string) ($context["name"] ?? ""),
                    (string) ($context["display"] ?? ""),
                    (string) ($context["gkit"] ?? ""),
                    (string) ($context["id"] ?? ""),
                ],
                $value
            );
        }

        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $innerKey => $innerValue) {
                $resolved[$innerKey] = self::resolveValue($innerValue, $context);
            }
            return $resolved;
        }

        return $value;
    }
}
