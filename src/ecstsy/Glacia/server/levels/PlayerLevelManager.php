<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\levels;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\console\ConsoleCommandSender;
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
    /** @var array<int, array{items: array<int, array<string, mixed>>, commands: string[]}> */
    private static array $levelRewards = [];

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

        self::$levelRewards = [];
        foreach ((array) ($config?->get("level-rewards", []) ?? []) as $level => $row) {
            if (!is_numeric((string) $level) || !is_array($row)) {
                continue;
            }

            $rewardLevel = (int) $level;
            if ($rewardLevel <= 0) {
                continue;
            }

            self::$levelRewards[$rewardLevel] = [
                "items" => array_values(array_filter((array) ($row["items"] ?? []), static fn(mixed $entry): bool => is_array($entry))),
                "commands" => array_values(array_filter(array_map("strval", (array) ($row["commands"] ?? [])), static fn(string $cmd): bool => trim($cmd) !== "")),
            ];
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

        self::grantLevelRewards($player, $level);
    }

    private static function grantLevelRewards(Player $player, int $level): void
    {
        $rewardSet = self::$levelRewards[$level] ?? null;
        if (!is_array($rewardSet)) {
            return;
        }

        $granted = 0;
        foreach ((array) ($rewardSet["items"] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $item = RewardResolver::resolveItem($entry, $player);
            if ($item === null) {
                continue;
            }

            foreach ($player->getInventory()->addItem($item) as $leftover) {
                $player->getWorld()->dropItem($player->getPosition(), $leftover);
            }
            $granted++;
        }

        $console = new ConsoleCommandSender(Loader::getInstance()->getServer(), Loader::getInstance()->getServer()->getLanguage());
        foreach ((array) ($rewardSet["commands"] ?? []) as $raw) {
            if (!is_string($raw) || trim($raw) === "") {
                continue;
            }

            $command = str_replace(
                ["{player}", "{name}", "{username}", "{uuid}", "{level}"],
                [$player->getName(), $player->getName(), $player->getName(), $player->getUniqueId()->toString(), (string) $level],
                $raw
            );
            Loader::getInstance()->getServer()->dispatchCommand($console, $command);
            $granted++;
        }

        if ($granted > 0) {
            $player->sendMessage(C::colorize("&r&6★ &r&7You received &f" . number_format($granted) . " &7reward(s) for reaching level &f" . number_format($level) . "&7."));
            PlayerUtils::playSound($player, "random.orb");
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
