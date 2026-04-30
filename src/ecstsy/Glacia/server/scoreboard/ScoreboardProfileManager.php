<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\scoreboard;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\world\theme\DimensionThemeManager;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;

final class ScoreboardProfileManager {

    public const PROFILE_AUTO = "auto";
    public const PROFILE_CUSTOM = "custom";
    private const MAX_LINES = 15;

    /** @var array<string, array<string, mixed>> */
    private static array $profiles = [];
    /** @var list<array<string, mixed>> */
    private static array $automaticRules = [];

    public static function loadFromConfig() : void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "scoreboards.yml");
        self::$profiles = [];
        self::$automaticRules = [];

        foreach ((array) ($config?->get("profiles", []) ?? []) as $profileId => $profileData) {
            if (!is_array($profileData)) {
                continue;
            }

            $id = strtolower(trim((string) $profileId));
            if ($id === "") {
                continue;
            }

            self::$profiles[$id] = self::sanitizeProfile($id, $profileData);
        }

        foreach ((array) ($config?->get("automatic", []) ?? []) as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $profile = strtolower(trim((string) ($rule["profile"] ?? "")));
            $when = is_array($rule["when"] ?? null) ? $rule["when"] : [];
            if ($profile === "" || !isset(self::$profiles[$profile]) || $when === []) {
                continue;
            }

            self::$automaticRules[] = [
                "profile" => $profile,
                "zone" => strtolower(trim((string) ($when["zone"] ?? ""))),
                "world" => strtolower(trim((string) ($when["world"] ?? ""))),
                "theme" => strtolower(trim((string) ($when["theme"] ?? ""))),
            ];
        }

        if (!isset(self::$profiles["default"])) {
            self::$profiles["default"] = [
                "id" => "default",
                "display" => "Default",
                "title" => "&bGlacia",
                "lines" => ["&7No scoreboard profile configured."],
            ];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getProfiles() : array
    {
        return self::$profiles;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getProfile(string $profileId) : ?array
    {
        return self::$profiles[strtolower(trim($profileId))] ?? null;
    }

    public static function getProfileDisplayName(string $profileId) : string
    {
        if ($profileId === self::PROFILE_AUTO) {
            return "Auto";
        }
        if ($profileId === self::PROFILE_CUSTOM) {
            return "Custom";
        }

        return (string) ((self::$profiles[$profileId]["display"] ?? ucfirst(str_replace("_", " ", $profileId))));
    }

    /**
     * @return array{id:string,title:string,lines:string[]}
     */
    public static function renderForPlayer(Player $player) : array
    {
        $session = Loader::getPlayerManager()->getSession($player);
        $selected = self::getSelectedProfileId($session);
        $profile = self::resolveProfile($player, $session, $selected);
        $title = self::interpolate((string) ($profile["title"] ?? "&bGlacia"), $player, 0);

        $lines = [];
        foreach ((array) ($profile["lines"] ?? []) as $index => $line) {
            $lines[] = self::interpolate((string) $line, $player, $index + 1);
        }

        return [
            "id" => (string) ($profile["id"] ?? "default"),
            "title" => $title,
            "lines" => array_slice($lines, 0, self::MAX_LINES),
        ];
    }

    public static function getSelectedProfileId(?GlaciaPlayer $session) : string
    {
        $selected = strtolower(trim((string) ($session?->getSetting("scoreboard_profile") ?? self::PROFILE_AUTO)));
        if ($selected === "" || ($selected !== self::PROFILE_AUTO && $selected !== self::PROFILE_CUSTOM && !isset(self::$profiles[$selected]))) {
            return self::PROFILE_AUTO;
        }

        return $selected;
    }

    public static function setSelectedProfileId(GlaciaPlayer $session, string $profileId) : void
    {
        $profileId = strtolower(trim($profileId));
        if ($profileId !== self::PROFILE_AUTO && $profileId !== self::PROFILE_CUSTOM && !isset(self::$profiles[$profileId])) {
            $profileId = self::PROFILE_AUTO;
        }

        $session->setSetting("scoreboard_profile", $profileId);
    }

    /**
     * @param string[] $lines
     */
    public static function saveCustomProfile(GlaciaPlayer $session, string $title, array $lines) : void
    {
        $cleanLines = [];
        foreach (array_slice($lines, 0, self::MAX_LINES) as $line) {
            $text = trim((string) $line);
            if ($text !== "") {
                $cleanLines[] = $text;
            }
        }

        $session->setSetting("scoreboard_custom", [
            "title" => trim($title) !== "" ? trim($title) : "&bCustom Board",
            "lines" => $cleanLines,
        ]);
        $session->setSetting("scoreboard_profile", self::PROFILE_CUSTOM);
    }

    /**
     * @return array{id:string,display:string,title:string,lines:string[]}
     */
    public static function getCustomProfile(?GlaciaPlayer $session) : array
    {
        $stored = $session?->getSetting("scoreboard_custom");
        if (!is_array($stored)) {
            return [
                "id" => self::PROFILE_CUSTOM,
                "display" => "Custom",
                "title" => "&bCustom Board",
                "lines" => [],
            ];
        }

        return self::sanitizeProfile(self::PROFILE_CUSTOM, $stored);
    }

    /**
     * @return string[]
     */
    public static function getTokenHints() : array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "scoreboards.yml");
        $tokens = (array) ($config?->getNested("tokens.hint", []) ?? []);
        return array_values(array_filter(array_map("strval", $tokens), static fn(string $line): bool => trim($line) !== ""));
    }

    /**
     * @return array{id:string,display:string,title:string,lines:string[]}
     */
    private static function resolveProfile(Player $player, ?GlaciaPlayer $session, string $selected) : array
    {
        if ($selected === self::PROFILE_CUSTOM) {
            $custom = self::getCustomProfile($session);
            if ($custom["lines"] !== []) {
                return $custom;
            }
        }

        if ($selected === self::PROFILE_AUTO) {
            $automatic = self::resolveAutomaticProfileId($player);
            if ($automatic !== null && isset(self::$profiles[$automatic])) {
                return self::$profiles[$automatic];
            }
            return self::$profiles["default"];
        }

        return self::$profiles[$selected] ?? self::$profiles["default"];
    }

    private static function resolveAutomaticProfileId(Player $player) : ?string
    {
        $zone = ZoneManager::getZoneAt($player->getPosition());
        $zoneId = strtolower((string) ($zone["id"] ?? ""));
        $world = strtolower($player->getWorld()->getFolderName());
        $theme = DimensionThemeManager::getThemeForPlayer($player);
        $themeId = strtolower((string) ($theme["id"] ?? ""));

        foreach (self::$automaticRules as $rule) {
            if (($rule["zone"] ?? "") !== "" && ($rule["zone"] ?? "") !== $zoneId) {
                continue;
            }
            if (($rule["world"] ?? "") !== "" && ($rule["world"] ?? "") !== $world) {
                continue;
            }
            if (($rule["theme"] ?? "") !== "" && ($rule["theme"] ?? "") !== $themeId) {
                continue;
            }
            return (string) $rule["profile"];
        }

        return null;
    }

    /**
     * @return array{id:string,display:string,title:string,lines:string[]}
     */
    private static function sanitizeProfile(string $id, array $profileData) : array
    {
        $lines = [];
        foreach ((array) ($profileData["lines"] ?? []) as $line) {
            if (count($lines) >= self::MAX_LINES) {
                break;
            }
            $lines[] = (string) $line;
        }

        return [
            "id" => strtolower(trim($id)),
            "display" => (string) ($profileData["display"] ?? ucfirst(str_replace("_", " ", $id))),
            "title" => (string) ($profileData["title"] ?? "&bGlacia"),
            "lines" => $lines,
        ];
    }

    /**
     * Like strtoupper(), but lowercase each character after '&' where it is a valid legacy Minecraft code (TextFormat::colorize: [0-9a-v]).
     */
    private static function strtoupperPreserveMinecraftAmpersandFormats(string $string) : string
    {
        $upper = strtoupper($string);

        return (string) (preg_replace_callback(
            '/&(.)/us',
            static function (array $m) : string {
                $code = $m[1];
                if ($code === '') {
                    return '&';
                }
                if (ctype_digit($code) || preg_match('/^[a-v]$/iu', $code) === 1) {
                    return '&' . strtolower($code);
                }
                return '&' . $code;
            },
            $upper
        ) ?? $upper);
    }

    private static function interpolate(string $template, Player $player, int $lineIndex) : string
    {
        $session = Loader::getPlayerManager()->getSession($player);
        $faction = $session?->getFaction();
        $zone = ZoneManager::getZoneAt($player->getPosition());
        $theme = DimensionThemeManager::getThemeForPlayer($player);

        $tokens = [
            "{date}" => date("M d, Y"),
            "{player_name}" => $player->getName(),
            "{player_upper}" => self::strtoupperPreserveMinecraftAmpersandFormats($player->getName()),
            "{rank}" => $session?->getRankObject()?->getDisplayName() ?? ucfirst((string) ($session?->getRank() ?? "member")),
            "{balance}" => self::formatMoney((float) ($session?->getBalance() ?? 0)),
            "{kdr}" => self::formatKdr(
                (int) ($session?->getKills() ?? 0),
                (int) ($session?->getDeaths() ?? 0),
                (float) ($session?->getKDRRatio() ?? 0)
            ),
            "{power}" => number_format((float) ($session?->getPower() ?? 0)),
            "{level}" => number_format((int) ($session?->getLevel() ?? 1)),
            "{faction_name}" => $faction?->getName() ?? "No Faction",
            "{faction_name_upper}" => self::strtoupperPreserveMinecraftAmpersandFormats($faction?->getName() ?? "No Faction"),
            "{faction_members}" => $faction !== null ? count($faction->getOnlineMembers()) . "/" . count($faction->getMembers()) : "0/0",
            "{faction_power}" => $faction !== null ? $faction->getFactionPower() . "/" . $faction->getMaxPower() : "0/0",
            "{faction_contribution}" => self::formatContributionPercent($faction?->getFactionId()),
            "{zone_name}" => (string) ($zone["display"] ?? "Wilderness"),
            "{zone_recommended}" => number_format((int) ($zone["recommended_level"] ?? 1)),
            "{zone_unlock}" => number_format((int) ($zone["unlock_level"] ?? 1)),
            "{theme_name}" => (string) ($theme["display"] ?? "Frontier"),
            "{theme_name_upper}" => self::strtoupperPreserveMinecraftAmpersandFormats((string) ($theme["display"] ?? "Frontier")),
            "{coords_short}" => $player->getPosition()->getFloorX() . ", " . $player->getPosition()->getFloorZ(),
            "{world}" => $player->getWorld()->getFolderName(),
        ];

        foreach (self::getClaimMapTokens($player) as $token => $value) {
            $tokens[$token] = $value;
        }

        $line = strtr($template, $tokens);
        if (trim($line) === "") {
            $line = str_repeat(" ", max(1, $lineIndex));
        }

        return \pocketmine\utils\TextFormat::colorize("  " . $line . "  ");
    }

    /**
     * @return array<string, string>
     */
    private static function getClaimMapTokens(Player $player) : array
    {
        $rows = [];
        $world = $player->getWorld();
        $centerChunkX = $player->getPosition()->getFloorX() >> 4;
        $centerChunkZ = $player->getPosition()->getFloorZ() >> 4;
        $session = Loader::getPlayerManager()->getSession($player);
        $playerFaction = $session?->getFaction();

        for ($dz = -2; $dz <= 2; $dz++) {
            $row = "";
            for ($dx = -2; $dx <= 2; $dx++) {
                $chunkX = $centerChunkX + $dx;
                $chunkZ = $centerChunkZ + $dz;
                if ($dx === 0 && $dz === 0) {
                    $row .= "&fX";
                    continue;
                }

                $claim = ClaimManager::getInstance()->getClaim($world->getFolderName(), $chunkX, $chunkZ);
                if ($claim === null) {
                    $row .= "&8.";
                    continue;
                }

                if ($playerFaction !== null && $claim->getFactionId() === $playerFaction->getFactionId()) {
                    $row .= "&aO";
                    continue;
                }

                if ($playerFaction !== null) {
                    $relation = RelationManager::getInstance()->getRelation($playerFaction->getFactionId(), $claim->getFactionId());
                    $row .= match ($relation) {
                        "ally" => "&bA",
                        "enemy" => "&cE",
                        default => "&7N",
                    };
                    continue;
                }

                $row .= "&7N";
            }
            $rows[] = $row;
        }

        $tokens = [];
        foreach ($rows as $index => $row) {
            $tokens["{claim_map_" . ($index + 1) . "}"] = $row;
        }

        return $tokens;
    }

    private static function formatContributionPercent(?string $factionId) : string
    {
        if ($factionId === null || $factionId === "") {
            return "0%";
        }

        $progress = \ecstsy\Glacia\server\factions\contribution\FactionContributionManager::getProgressBarData($factionId);
        return (string) ((int) round(((float) ($progress["percent"] ?? 0.0)) * 100)) . "%";
    }

    private static function formatKdr(int $kills, int $deaths, float $ratio) : string
    {
        return $kills . "&7/&b" . $deaths . " &7(" . number_format($ratio, 2) . ")";
    }

    private static function formatMoney(float $amount) : string
    {
        if ($amount < 10_000_000) {
            return number_format($amount, 2);
        }

        foreach ([1_000_000_000 => "B", 1_000_000 => "M"] as $value => $suffix) {
            if ($amount >= $value) {
                $formatted = number_format(round($amount / $value, 1), 1, ".", "");
                return (str_ends_with($formatted, ".0") ? substr($formatted, 0, -2) : $formatted) . $suffix;
            }
        }

        return number_format($amount, 2);
    }
}
