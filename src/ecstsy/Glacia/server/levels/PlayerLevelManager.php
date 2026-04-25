<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\levels;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PlayerLevelManager {

    private static int $maxLevel = 100;
    private static int $baseXp = 100;
    private static int $xpPerLevel = 35;
    private static float $growth = 1.08;
    private static bool $broadcastMilestones = false;
    /** @var int[] */
    private static array $milestones = [10, 25, 50, 75, 100];
    private static int $defaultMobXp = 8;
    /** @var array<string, int> */
    private static array $mobXp = [];

    public static function loadFromConfig() : void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "levels.yml");

        self::$maxLevel = max(1, (int) ($config?->get("max-level", 100) ?? 100));

        $curve = (array) ($config?->get("xp-curve", []) ?? []);
        self::$baseXp = max(1, (int) ($curve["base"] ?? 100));
        self::$xpPerLevel = max(0, (int) ($curve["per-level"] ?? 35));
        self::$growth = max(1.0, (float) ($curve["growth"] ?? 1.08));

        self::$broadcastMilestones = (bool) ($config?->get("broadcast-milestones", false) ?? false);
        self::$milestones = array_values(array_unique(array_map(
            static fn(mixed $level) : int => max(1, (int) $level),
            (array) ($config?->get("milestones", [10, 25, 50, 75, 100]) ?? [10, 25, 50, 75, 100])
        )));
        sort(self::$milestones);

        $mobXp = (array) ($config?->get("mob-xp", []) ?? []);
        self::$defaultMobXp = max(0, (int) ($mobXp["default"] ?? 8));
        self::$mobXp = [];
        foreach ((array) ($mobXp["overrides"] ?? []) as $key => $value) {
            self::$mobXp[self::normalizeMobKey((string) $key)] = max(0, (int) $value);
        }
    }

    public static function getMaxLevel() : int
    {
        return self::$maxLevel;
    }

    public static function getRequiredXpForLevel(int $level) : int
    {
        $level = max(1, $level);
        $raw = (self::$baseXp + (($level - 1) * self::$xpPerLevel)) * (self::$growth ** max(0, $level - 1));
        return max(1, (int) round($raw));
    }

    public static function getProgressPercent(GlaciaPlayer $session) : float
    {
        if ($session->getLevel() >= self::$maxLevel) {
            return 100.0;
        }

        return min(100.0, ($session->getLevelXp() / self::getRequiredXpForLevel($session->getLevel())) * 100);
    }

    public static function addXp(Player $player, int $amount, string $source = "combat") : void
    {
        if ($amount <= 0) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return;
        }

        $level = $session->getLevel();
        $xp = $session->getLevelXp();

        if ($level >= self::$maxLevel) {
            $session->setLevelData(self::$maxLevel, 0);
            return;
        }

        $xp += $amount;
        $leveledUp = false;
        while ($level < self::$maxLevel) {
            $needed = self::getRequiredXpForLevel($level);
            if ($xp < $needed) {
                break;
            }

            $xp -= $needed;
            $level++;
            $leveledUp = true;
            self::handleLevelUp($player, $level, $source);

            if ($level >= self::$maxLevel) {
                $xp = 0;
                break;
            }
        }

        $session->setLevelData($level, $xp);

        if ($leveledUp) {
            return;
        }

        $player->sendTip(C::colorize("&r&b+" . number_format($amount) . " Level XP"));
    }

    public static function getMobXpFor(Entity $entity) : int
    {
        if (!$entity instanceof Living || $entity instanceof Player) {
            return 0;
        }

        if (method_exists($entity, "getLevelXpDropAmount")) {
            return max(0, (int) $entity->getLevelXpDropAmount());
        }

        $key = self::normalizeMobKey($entity::class);
        return self::$mobXp[$key] ?? self::$defaultMobXp;
    }

    private static function handleLevelUp(Player $player, int $level, string $source) : void
    {
        $player->sendTitle(
            C::colorize("&r&b&lLEVEL UP"),
            C::colorize("&r&7You are now level &f" . number_format($level) . "&7."),
            10,
            40,
            10
        );
        PlayerUtils::playSound($player, "random.levelup");
        $player->sendMessage(C::colorize("&r&a✔ &r&7You reached level &f" . number_format($level) . "&7."));

        if (in_array($level, self::$milestones, true)) {
            $player->sendMessage(C::colorize("&r&b• &r&7Milestone reached at level &f" . number_format($level) . "&7."));

            if (self::$broadcastMilestones) {
                Loader::getInstance()->getServer()->broadcastMessage(
                    C::colorize("&r&bLevel &8| &r&f" . $player->getName() . " &7has reached level &b" . number_format($level) . "&7.")
                );
            }
        }
    }

    private static function normalizeMobKey(string $value) : string
    {
        $value = ltrim($value, "\\");
        if (str_contains($value, "\\")) {
            $parts = explode("\\", $value);
            $value = end($parts);
        }

        $value = strtolower(trim($value));
        $value = str_replace(["minecraft:", " ", "-"], ["", "_", "_"], $value);
        return preg_replace('/[^a-z0-9_]/', '', $value) ?? "";
    }
}
