<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\Loader;
use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class EnchantDebugManager
{
    public static function logProc(Living $owner, EnchantProcResult $result): void
    {
        $message = self::buildProcMessage($owner, $result);
        if ($message === null) {
            return;
        }

        foreach (self::resolveRecipients($owner) as $recipient) {
            $recipient->sendMessage(C::colorize($message));
        }

        self::logToConsoleIfEnabled($message);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function logAppliedValues(Living $owner, EnchantProcResult $result, array $values): void
    {
        if (!$result->success || $values === []) {
            return;
        }

        $message = self::buildAppliedValuesMessage($owner, $result, $values);
        if ($message === null) {
            return;
        }

        foreach (self::resolveRecipients($owner) as $recipient) {
            $recipient->sendMessage(C::colorize($message));
        }

        self::logToConsoleIfEnabled($message);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function logPassiveValues(Living $owner, string $enchantId, int $level, array $values): void
    {
        if ($values === []) {
            return;
        }

        $result = new EnchantProcResult(
            enchantId: $enchantId,
            level: $level,
            success: true,
            reason: "passive",
            baseChance: 100.0,
            finalChance: 100.0,
            roll: 0.01,
            cooldownSeconds: 0,
            cooldownRemainingSeconds: 0,
            missesBefore: 0,
            missesAfter: 0,
            chanceBreakdown: []
        );

        $message = self::buildAppliedValuesMessage($owner, $result, $values);
        if ($message === null) {
            return;
        }

        foreach (self::resolveRecipients($owner) as $recipient) {
            $recipient->sendMessage(C::colorize($message));
        }

        self::logToConsoleIfEnabled($message);
    }

    /**
     * @return list<Player>
     */
    private static function resolveRecipients(Living $owner): array
    {
        $debug = EnchantTuningManager::getSettingsArray("debug");
        if (!((bool) ($debug["enabled"] ?? false))) {
            return [];
        }

        $recipients = [];
        if ($owner instanceof Player && self::shouldSendToOwner($owner, $debug)) {
            $recipients[strtolower($owner->getName())] = $owner;
        }

        $staffPermission = trim((string) ($debug["staff-permission"] ?? ""));
        if ($staffPermission !== "") {
            foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $player) {
                if ($player->hasPermission($staffPermission)) {
                    $recipients[strtolower($player->getName())] = $player;
                }
            }
        }

        foreach (self::resolveNamedTargets($debug) as $targetName) {
            $player = Loader::getInstance()->getServer()->getPlayerExact($targetName);
            if ($player instanceof Player) {
                $recipients[strtolower($player->getName())] = $player;
            }
        }

        return array_values($recipients);
    }

    /**
     * @param array<string, mixed> $debug
     */
    private static function shouldSendToOwner(Player $player, array $debug): bool
    {
        if (!((bool) ($debug["owner"] ?? true))) {
            return false;
        }

        $ownerTargetsOnly = (bool) ($debug["owner-targets-only"] ?? false);
        $targets = self::resolveNamedTargets($debug);
        return !$ownerTargetsOnly || $targets === [] || in_array(strtolower($player->getName()), $targets, true);
    }

    /**
     * @param array<string, mixed> $debug
     * @return list<string>
     */
    private static function resolveNamedTargets(array $debug): array
    {
        $targets = [];
        foreach ((array) ($debug["targets"] ?? []) as $target) {
            $name = strtolower(trim((string) $target));
            if ($name !== "") {
                $targets[] = $name;
            }
        }

        return array_values(array_unique($targets));
    }

    private static function logToConsoleIfEnabled(string $message): void
    {
        $debug = EnchantTuningManager::getSettingsArray("debug");
        if (!((bool) ($debug["console"] ?? false))) {
            return;
        }

        Loader::getInstance()->getLogger()->info(C::clean($message));
    }

    private static function buildProcMessage(Living $owner, EnchantProcResult $result): ?string
    {
        $breakdown = $result->chanceBreakdown;
        $ownerName = $owner instanceof Player ? $owner->getName() : $owner->getNameTag();
        $parts = [
            "&8[&bEnchantDebug&8] &f{$ownerName} &7{$result->enchantId} &fL{$result->level}",
            "&7chance=&f" . self::fmt((float) ($breakdown["base_chance"] ?? $result->baseChance)),
            "&7final=&f" . self::fmt($result->finalChance),
            "&7roll=&f" . self::fmt($result->roll),
            "&7cd=&f{$result->cooldownRemainingSeconds}/{$result->cooldownSeconds}s",
            "&7misses=&f{$result->missesBefore}&8->&f{$result->missesAfter}",
            "&7reason=&f{$result->reason}"
        ];

        if (isset($breakdown["fail_stack_bonus"]) && (float) $breakdown["fail_stack_bonus"] !== 0.0) {
            $parts[] = "&7fail+&f" . self::fmt((float) $breakdown["fail_stack_bonus"]);
        }
        if (isset($breakdown["rune_bonus"]) && (float) $breakdown["rune_bonus"] !== 0.0) {
            $parts[] = "&7rune+&f" . self::fmt((float) $breakdown["rune_bonus"]);
        }
        if (isset($breakdown["combat_bonus"]) && (float) $breakdown["combat_bonus"] !== 0.0) {
            $parts[] = "&7combat+&f" . self::fmt((float) $breakdown["combat_bonus"]);
        }
        if (isset($breakdown["runeswell_bonus"]) && (float) $breakdown["runeswell_bonus"] !== 0.0) {
            $parts[] = "&7runeswell+&f" . self::fmt((float) $breakdown["runeswell_bonus"]);
        }

        return implode(" ", $parts);
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function buildAppliedValuesMessage(Living $owner, EnchantProcResult $result, array $values): ?string
    {
        $ownerName = $owner instanceof Player ? $owner->getName() : $owner->getNameTag();
        $parts = [];
        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $parts[] = "{$key}=" . ($value ? "true" : "false");
                continue;
            }
            if (is_int($value) || is_float($value)) {
                $parts[] = "{$key}=" . self::fmt((float) $value);
                continue;
            }

            $resolved = self::stringifyValue($value);
            if ($resolved === null) {
                continue;
            }

            $parts[] = "{$key}=" . $resolved;
        }

        if ($parts === []) {
            return null;
        }

        return "&8[&bEnchantDebug&8] &f{$ownerName} &7{$result->enchantId} &7applied &f" . implode(" &7", $parts);
    }

    private static function fmt(float $value): string
    {
        return number_format($value, 2, ".", "");
    }

    private static function stringifyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value)) {
            return self::fmt((float) $value);
        }
        if (is_bool($value)) {
            return $value ? "true" : "false";
        }
        if (is_object($value)) {
            if (method_exists($value, "getText")) {
                $text = $value->getText();
                if (is_string($text)) {
                    return trim($text);
                }
            }
            if (method_exists($value, "__toString")) {
                return trim((string) $value);
            }

            return (new \ReflectionClass($value))->getShortName();
        }
        if (is_array($value)) {
            $encoded = json_encode($value);
            return is_string($encoded) ? $encoded : null;
        }

        return trim((string) $value);
    }
}
