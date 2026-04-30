<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\lootboxes;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItems;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class CcProgressManager
{
    public const ENHANCER_DEFINITION = "cc_enhancer";

    /** @var array<string, mixed> */
    private static array $config = [];

    public static function loadFromConfig() : void
    {
        self::$config = GeneralUtils::getConfiguration(Loader::getInstance(), "cc_system.yml")?->getAll() ?? [];
    }

    public static function registerItemDefinitions() : void
    {
        if (!self::isEnhancerEnabled()) {
            return;
        }

        $enhancer = (array) (self::$config["enhancer"] ?? []);
        $material = StringToItemParser::getInstance()->parse((string) ($enhancer["material"] ?? "amethyst_shard"))
            ?? StringToItemParser::getInstance()->parse("amethyst_shard");
        $success = self::getEnhancerSuccessRate();
        $lore = [];
        foreach ((array) ($enhancer["lore"] ?? []) as $line) {
            if (is_string($line)) {
                $lore[] = str_replace("{success}", (string) $success, $line);
            }
        }

        GlaciaItemFactory::$definitions[self::ENHANCER_DEFINITION] = [
            "material" => $material,
            "name" => (string) ($enhancer["name"] ?? "&r&l&dCC Enhancer"),
            "lore" => $lore,
            "nbt" => [
                "glaciaItem" => self::ENHANCER_DEFINITION,
                "success" => (string) $success,
            ],
        ];

        GlaciaItems::registerCategory("progression", "Progression", [
            self::ENHANCER_DEFINITION,
        ]);
    }

    public static function isEnabled() : bool
    {
        return (bool) ((self::$config["settings"]["enabled"] ?? true));
    }

    public static function isEnhancerEnabled() : bool
    {
        return self::isEnabled() && (bool) ((self::$config["enhancer"]["enabled"] ?? true));
    }

    public static function getEnhancerSuccessRate() : int
    {
        return max(1, min(100, (int) ((self::$config["enhancer"]["success-rate"] ?? 35))));
    }

    public static function handleLootboxOpened(Player $player, array $box) : void
    {
        if (!self::isEnabled()) {
            return;
        }

        $boxId = strtolower((string) ($box["id"] ?? ""));
        if (!self::isEligibleLootbox($boxId)) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        if (!$session instanceof GlaciaPlayer) {
            return;
        }

        $pointReward = (array) (self::$config["point-reward"] ?? []);
        if (!((bool) ($pointReward["enabled"] ?? true))) {
            return;
        }

        $threshold = max(1, (int) ($pointReward["threshold"] ?? 100));
        $pointsPerOpen = max(1, (int) ($pointReward["points-per-open"] ?? 1));
        $current = max(0, (int) ($session->getSetting("cc_points") ?? 0));
        $current += $pointsPerOpen;

        $rewardLabel = (string) ($pointReward["current-label"] ?? "Monthly CC");
        $rewardId = self::getCurrentRewardId();
        $session->setSetting("cc_points", $current);
        $player->sendMessage(C::colorize("&r&dCC Points: &f{$current}/{$threshold} &7({$rewardLabel})"));

        if ($current < $threshold || $rewardId === null) {
            return;
        }

        $current -= $threshold;
        $session->setSetting("cc_points", $current);

        try {
            $reward = GlaciaItemFactory::lootbox($rewardId, $player);
        } catch (\Throwable) {
            $player->sendMessage(C::colorize("&r&cYour CC point reward is configured to an invalid lootbox."));
            return;
        }

        foreach ($player->getInventory()->addItem($reward) as $leftover) {
            $player->getWorld()->dropItem($player->getPosition(), $leftover);
        }

        $player->sendMessage(C::colorize("&r&aCC Reward unlocked! You received &f{$rewardLabel}&a for free."));
    }

    public static function handleEtherealCrateOpened(Player $player, array $crate) : void
    {
        if (!self::isEnabled()) {
            return;
        }

        // Ethereal crate repository uses `id`; normalize to keep config matching consistent.
        $crateId = strtolower((string) ($crate["id"] ?? ""));
        if (!self::isEligibleEtherealCrate($crateId)) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        if (!$session instanceof GlaciaPlayer) {
            return;
        }

        $pointReward = (array) (self::$config["point-reward"] ?? []);
        if (!((bool) ($pointReward["enabled"] ?? true))) {
            return;
        }

        $threshold = max(1, (int) ($pointReward["threshold"] ?? 100));
        $pointsPerOpen = max(1, (int) ($pointReward["points-per-open"] ?? 1));
        $current = max(0, (int) ($session->getSetting("cc_points") ?? 0));
        $current += $pointsPerOpen;

        $rewardLabel = (string) ($pointReward["current-label"] ?? "Monthly CC");
        $rewardId = self::getCurrentRewardId();
        $session->setSetting("cc_points", $current);
        $player->sendMessage(C::colorize("&r&dCC Points: &f{$current}/{$threshold} &7({$rewardLabel})"));

        if ($current < $threshold || $rewardId === null) {
            return;
        }

        $current -= $threshold;
        $session->setSetting("cc_points", $current);

        try {
            $reward = GlaciaItemFactory::lootbox($rewardId, $player);
        } catch (\Throwable) {
            $player->sendMessage(C::colorize("&r&cYour CC point reward is configured to an invalid lootbox."));
            return;
        }

        foreach ($player->getInventory()->addItem($reward) as $leftover) {
            $player->getWorld()->dropItem($player->getPosition(), $leftover);
        }

        $player->sendMessage(C::colorize("&r&aCC Reward unlocked! You received &f{$rewardLabel}&a for free."));
    }

    public static function getCurrentRewardId() : ?string
    {
        $configured = strtolower(trim((string) (self::$config["point-reward"]["current-id"] ?? "")));
        if ($configured !== "") {
            return $configured;
        }

        $order = self::getOrder();
        return $order === [] ? null : end($order);
    }

    /**
     * @return string[]
     */
    public static function getOrder() : array
    {
        return array_values(array_filter(array_map(
            static fn(mixed $value) : string => strtolower(trim((string) $value)),
            (array) (self::$config["order"] ?? [])
        ), static fn(string $value) : bool => $value !== ""));
    }

    public static function getNextLootboxId(string $currentId) : ?string
    {
        $order = self::getOrder();
        $index = array_search(strtolower($currentId), $order, true);
        if ($index === false) {
            return null;
        }

        $next = $order[$index + 1] ?? null;
        return is_string($next) && $next !== "" ? $next : null;
    }

    private static function isEligibleLootbox(string $boxId) : bool
    {
        $eligible = (array) (self::$config["settings"]["eligible-lootboxes"] ?? ["*"]);
        $normalized = array_values(array_filter(array_map(
            static fn(mixed $value) : string => strtolower(trim((string) $value)),
            $eligible
        ), static fn(string $value) : bool => $value !== ""));

        return in_array("*", $normalized, true) || in_array($boxId, $normalized, true);
    }

    private static function isEligibleEtherealCrate(string $crateId) : bool
    {
        // New key (preferred), but keep backward compatibility with `eligible-lootboxes`.
        $eligible = (array) (
            self::$config["settings"]["eligible-ethereal-crates"]
                ?? self::$config["settings"]["eligible-lootboxes"]
                ?? ["*"]
        );
        $normalized = array_values(array_filter(array_map(
            static fn(mixed $value) : string => strtolower(trim((string) $value)),
            $eligible
        ), static fn(string $value) : bool => $value !== ""));

        return in_array("*", $normalized, true) || in_array($crateId, $normalized, true);
    }
}
