<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\world\events\WorldEventManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class WorldEventCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("zone", true));
        $this->registerArgument(2, new RawStringArgument("value", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $action = strtolower((string) ($args["action"] ?? "status"));
        $zoneId = strtolower((string) ($args["zone"] ?? ""));
        $value = (string) ($args["value"] ?? "");

        switch ($action) {
            case "status":
            case "info":
                $this->sendStatus($sender, $zoneId);
                return;

            case "list":
            case "zones":
                $zones = WorldEventManager::getZoneIds();
                $sender->sendMessage(C::colorize("&r&l&bWorld Events"));
                $sender->sendMessage(C::colorize("&r&7Zones: &f" . ($zones === [] ? "none" : implode("&7, &f", $zones))));
                return;

            case "events":
                if ($zoneId === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /worldevent events <zone>"));
                    return;
                }
                $events = WorldEventManager::getEventIds($zoneId);
                $sender->sendMessage(C::colorize("&r&bEvents for &f{$zoneId}&7: &f" . ($events === [] ? "none" : implode("&7, &f", $events))));
                return;

            case "next":
                $seconds = GeneralUtils::parseTimeString($value);
                if ($zoneId === "" || $seconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /worldevent next <zone> <duration>"));
                    return;
                }
                $sender->sendMessage(C::colorize(WorldEventManager::setNextDelay($zoneId, $seconds)
                    ? "&r&aSet next world event for &f{$zoneId} &ato start in &f" . GeneralUtils::translateTime($seconds) . "&a."
                    : "&r&cUnknown world event zone &f{$zoneId}&c."
                ));
                return;

            case "time":
                $seconds = GeneralUtils::parseTimeString($value);
                if ($zoneId === "" || $seconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /worldevent time <zone> <duration>"));
                    return;
                }
                $sender->sendMessage(C::colorize(WorldEventManager::setActiveTime($zoneId, $seconds)
                    ? "&r&aSet active world event in &f{$zoneId} &ato end in &f" . GeneralUtils::translateTime($seconds) . "&a."
                    : "&r&cThere is no active world event in &f{$zoneId}&c."
                ));
                return;

            case "start":
                if ($zoneId === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /worldevent start <zone> [event]"));
                    return;
                }
                $sender->sendMessage(C::colorize(WorldEventManager::forceStart($zoneId, $value !== "" ? $value : null)
                    ? "&r&aStarted a world event in &f{$zoneId}&a."
                    : "&r&cCould not start a world event in &f{$zoneId}&c. Check the zone/event ID or stop the active event first."
                ));
                return;

            case "stop":
            case "end":
                if ($zoneId === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /worldevent stop <zone>"));
                    return;
                }
                $sender->sendMessage(C::colorize(WorldEventManager::forceStop($zoneId)
                    ? "&r&aStopped the active world event in &f{$zoneId}&a."
                    : "&r&cThere is no active world event in &f{$zoneId}&c."
                ));
                return;
        }

        $this->sendHelp($sender);
    }

    private function sendStatus(CommandSender $sender, string $zoneId): void
    {
        $zones = $zoneId !== "" ? [$zoneId] : WorldEventManager::getZoneIds();
        if ($zones === []) {
            $sender->sendMessage(C::colorize("&r&cNo world event zones are configured."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&l&bWorld Event Timers"));
        $now = time();
        foreach ($zones as $id) {
            $active = WorldEventManager::getActiveEvent($id);
            if (is_array($active)) {
                $name = C::clean((string) (($active["config"]["display"] ?? $active["event_id"] ?? $id) ?? $id));
                $left = max(0, (int) ($active["ends_at"] ?? $now) - $now);
                $sender->sendMessage(C::colorize("&r&f{$id} &8- &aACTIVE &7{$name} &8(&f" . GeneralUtils::translateTime($left) . "&8)"));
                continue;
            }

            $nextAt = WorldEventManager::getNextStartAt($id);
            if ($nextAt === null) {
                $sender->sendMessage(C::colorize("&r&f{$id} &8- &cnot scheduled"));
                continue;
            }

            $sender->sendMessage(C::colorize("&r&f{$id} &8- &7next in &f" . GeneralUtils::translateTime(max(0, $nextAt - $now))));
        }
    }

    private function sendHelp(CommandSender $sender): void
    {
        $sender->sendMessage(C::colorize("&r&l&bWorld Event Timers"));
        $sender->sendMessage(C::colorize("&r&b/worldevent status [zone] &7- view timers"));
        $sender->sendMessage(C::colorize("&r&b/worldevent next <zone> <duration> &7- set next start"));
        $sender->sendMessage(C::colorize("&r&b/worldevent time <zone> <duration> &7- set active remaining time"));
        $sender->sendMessage(C::colorize("&r&b/worldevent start <zone> [event] &7- force start"));
        $sender->sendMessage(C::colorize("&r&b/worldevent stop <zone> &7- force stop"));
        $sender->sendMessage(C::colorize("&r&b/worldevent events <zone> &7- list event IDs"));
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
