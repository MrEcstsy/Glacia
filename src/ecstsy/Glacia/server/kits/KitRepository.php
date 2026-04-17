<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\kits;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

final class KitRepository {

    public static function getKits(string $section = "rank-kits"): array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "kits.yml");
        $data = $config?->get($section, []);

        if (!is_array($data)) {
            Loader::getInstance()->getLogger()->warning("Kit section '$section' is not a valid array.");
            return [];
        }

        $kits = [];
        $nextSlot = 0;

        foreach ($data as $id => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $normalized = self::normalizeKit((string) $id, $entry, $nextSlot);
            if ($normalized === null) {
                continue;
            }

            $kits[] = $normalized;
            $nextSlot = max($nextSlot, ((int) $normalized["slot"]) + 1);
        }

        usort($kits, fn(array $a, array $b) => ((int) $a["slot"]) <=> ((int) $b["slot"]));
        return $kits;
    }

    public static function getRankKits(): array
    {
        return self::getKits("rank-kits");
    }

    public static function getKitById(string $kitId, string $section = "rank-kits"): ?array
    {
        $kitId = strtolower(trim($kitId));

        foreach (self::getKits($section) as $kit) {
            if (strtolower((string) ($kit["id"] ?? "")) === $kitId) {
                return $kit;
            }
        }

        return null;
    }

    public static function createIcon(array $kit, ?Player $player = null): Item
    {
        $iconData = $kit["icon"] ?? null;
        $icon = is_array($iconData) ? RewardResolver::resolveItem($iconData) : null;

        if ($icon === null) {
            $icon = VanillaItems::CHEST();
        }

        $icon->setCustomName((string) $kit["display"]);

        $lore = [];
        $description = (string) ($kit["description"] ?? "");
        if ($description !== "") {
            foreach (explode("\n", $description) as $line) {
                $lore[] = $line;
            }
            $lore[] = "";
        }

        $permission = (string) ($kit["permission"] ?? "");

        $lore[] = "&r&7Items: &f" . self::getDisplayItemCount($kit);
        $lore[] = "";

        if ($player !== null) {
            if ($permission !== "" && !$player->hasPermission($permission)) {
                $lore[] = "&r&l&cLOCKED";
                $lore[] = "&r&7Click to claim.";
            } else {
                $remaining = self::getRemainingCooldown($player, $kit);
                if ($remaining > 0) {
                    $lore[] = "&r&l&eCOOLING DOWN";
                    $lore[] = "&r&7Ready in " . self::formatCooldown($remaining) . ".";
                } else {
                    $lore[] = "&r&l&aUNLOCKED";
                    $lore[] = "&r&7Click to claim.";
                }
            }
        } else {
            $lore[] = "&r&eClick to claim";
        }

        $icon->setLore(array_map(fn(string $line): string => TextFormat::colorize($line), $lore));
        return $icon;
    }

    public static function resolveItems(array $kit): array
    {
        $resolved = [];

        foreach ($kit["items"] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $item = RewardResolver::resolveItem($entry);
            if ($item !== null) {
                $resolved[] = $item;
            }
        }

        $randomPool = $kit["random-items"] ?? [];
        $rolls = max(0, (int) ($kit["random-rolls"] ?? 0));

        if (is_array($randomPool) && $randomPool !== [] && $rolls > 0) {
            for ($i = 0; $i < $rolls; $i++) {
                $entry = RewardResolver::rollEntry($randomPool);
                if (!is_array($entry)) {
                    continue;
                }

                $item = RewardResolver::resolveItem($entry);
                if ($item !== null) {
                    $resolved[] = $item;
                }
            }
        }

        return $resolved;
    }

    private static function normalizeKit(string $id, array $entry, int $fallbackSlot): ?array
    {
        $display = (string) ($entry["display"] ?? $id);
        $items = $entry["items"] ?? [];
        $randomItems = $entry["random-items"] ?? [];
        $randomRolls = max(0, (int) ($entry["random-rolls"] ?? 0));

        $hasFixedItems = is_array($items) && $items !== [];
        $hasRandomItems = is_array($randomItems) && $randomItems !== [] && $randomRolls > 0;

        if (!$hasFixedItems && !$hasRandomItems) {
            Loader::getInstance()->getLogger()->warning("Kit '$id' has no valid items configured.");
            return null;
        }

        return [
            "id" => $id,
            "display" => TextFormat::colorize($display),
            "description" => (string) ($entry["description"] ?? ""),
            "permission" => (string) ($entry["permission"] ?? ""),
            "cooldown" => GeneralUtils::parseTimeString((string) ($entry["cooldown"] ?? "0s")),
            "slot" => max(0, (int) ($entry["slot"] ?? $fallbackSlot)),
            "icon" => is_array($entry["icon"] ?? null) ? $entry["icon"] : null,
            "items" => $hasFixedItems ? $items : [],
            "random-items" => $hasRandomItems ? $randomItems : [],
            "random-rolls" => $hasRandomItems ? $randomRolls : 0
        ];
    }

    public static function claimKit(Player $player, array $kit): bool
    {
        $permission = (string) ($kit["permission"] ?? "");
        if ($permission !== "" && !$player->hasPermission($permission)) {
            PlayerUtils::playSound($player, "note.bass", 1.0, 0.8);
            $player->sendMessage(TextFormat::colorize("&r&cYou do not have access to this kit."));
            return false;
        }

        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            PlayerUtils::playSound($player, "note.bass", 1.0, 0.8);
            $player->sendMessage(TextFormat::colorize("&r&cCould not load your profile."));
            return false;
        }

        $remaining = self::getRemainingCooldown($player, $kit);
        if ($remaining > 0) {
            PlayerUtils::playSound($player, "random.click", 1.0, 0.7);
            $player->sendMessage(TextFormat::colorize("&r&cThis kit is on cooldown for &f" . GeneralUtils::translateTime($remaining) . "&c."));
            return false;
        }

        $items = self::resolveItems($kit);
        if ($items === []) {
            PlayerUtils::playSound($player, "note.bass", 1.0, 0.8);
            $player->sendMessage(TextFormat::colorize("&r&cThis kit has no valid items configured."));
            return false;
        }

        $overflow = [];
        foreach ($items as $item) {
            $leftovers = $player->getInventory()->addItem(clone $item);
            foreach ($leftovers as $leftover) {
                $overflow[] = $leftover;
            }
        }

        foreach ($overflow as $item) {
            $session->addItemToCollection($item);
        }

        $cooldown = (int) ($kit["cooldown"] ?? 0);
        if ($cooldown > 0) {
            $session->addCooldown(self::getCooldownKey((string) $kit["id"]), $cooldown);
        }

        PlayerUtils::playSound($player, "random.levelup");
        $player->sendMessage(TextFormat::colorize("&r&aClaimed &f" . ((string) $kit["display"]) . "&r&a."));
        if ($overflow !== []) {
            $player->sendMessage(TextFormat::colorize("&r&eSome items were sent to your collection because your inventory was full."));
        }

        return true;
    }

    public static function grantKit(Player $player, array $kit): bool
    {
        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            PlayerUtils::playSound($player, "note.bass", 1.0, 0.8);
            $player->sendMessage(TextFormat::colorize("&r&cCould not load your profile."));
            return false;
        }

        $items = self::resolveItems($kit);
        if ($items === []) {
            PlayerUtils::playSound($player, "note.bass", 1.0, 0.8);
            $player->sendMessage(TextFormat::colorize("&r&cThis kit has no valid items configured."));
            return false;
        }

        $overflow = [];
        foreach ($items as $item) {
            $leftovers = $player->getInventory()->addItem(clone $item);
            foreach ($leftovers as $leftover) {
                $overflow[] = $leftover;
            }
        }

        foreach ($overflow as $item) {
            $session->addItemToCollection($item);
        }

        PlayerUtils::playSound($player, "random.levelup");
        $player->sendMessage(TextFormat::colorize("&r&aReceived &f" . ((string) $kit["display"]) . "&r&a."));
        if ($overflow !== []) {
            $player->sendMessage(TextFormat::colorize("&r&eSome items were sent to your collection because your inventory was full."));
        }

        return true;
    }

    public static function getRemainingCooldown(Player $player, array $kit): int
    {
        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return 0;
        }

        return $session->getCooldown(self::getCooldownKey((string) $kit["id"])) ?? 0;
    }

    public static function clearKitCooldown(Player $player, array $kit): bool
    {
        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return false;
        }

        $session->clearCooldown(self::getCooldownKey((string) $kit["id"]));
        return true;
    }

    private static function getCooldownKey(string $kitId): string
    {
        return "kit_" . strtolower($kitId);
    }

    private static function formatCooldown(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . "d";
        }

        if ($hours > 0 || $days > 0) {
            $parts[] = $hours . "h";
        }

        if ($minutes > 0 || $hours > 0 || $days > 0) {
            $parts[] = $minutes . "m";
        }

        $parts[] = $seconds . "s";

        return implode(" ", $parts);
    }

    private static function getDisplayItemCount(array $kit): int
    {
        return count($kit["items"] ?? []) + max(0, (int) ($kit["random-rolls"] ?? 0));
    }
}
