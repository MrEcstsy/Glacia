<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class BoosterCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("type", true));
        $this->registerArgument(2, new RawStringArgument("zone", true));
        $this->registerArgument(3, new RawStringArgument("value", true));
        $this->registerArgument(4, new RawStringArgument("duration", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $manager = Loader::getZoneBoosterManager();
        $action = strtolower((string) ($args["action"] ?? "list"));

        switch ($action) {
            case "list":
            case "status":
            case "info":
                $this->sendList($sender, strtolower((string) ($args["type"] ?? "")));
                return;

            case "activate":
            case "start":
                if (!$sender->hasPermission("glacia.admin")) {
                    $sender->sendMessage(C::colorize("&r&cYou do not have permission to activate boosters."));
                    return;
                }

                $type = $manager->normalizeType((string) ($args["type"] ?? ""));
                $zoneId = strtolower((string) ($args["zone"] ?? ""));
                $multiplierText = (string) ($args["value"] ?? "");
                $durationText = (string) ($args["duration"] ?? "");
                $multiplier = is_numeric($multiplierText) ? (float) $multiplierText : -1.0;
                $durationSeconds = GeneralUtils::parseTimeString($durationText);

                if ($type === null || $zoneId === "" || $multiplier < 1.0 || $durationSeconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /booster activate <zone_xp|fishing> <zone> <multiplier> <duration>"));
                    $sender->sendMessage(C::colorize("&r&7Example: &f/booster activate zone_xp frostreach 2.5 15m"));
                    return;
                }

                if (!$manager->activate($zoneId, $type, $multiplier, $durationSeconds, $sender->getName())) {
                    $sender->sendMessage(C::colorize("&r&cFailed to activate booster. Check the zone/type."));
                    return;
                }

                $sender->sendMessage(C::colorize("&r&aActivated &f" . $manager->getTypeDisplayName($type) . " &afor zone &f{$zoneId}&a."));
                return;

            case "deactivate":
            case "stop":
            case "end":
                if (!$sender->hasPermission("glacia.admin")) {
                    $sender->sendMessage(C::colorize("&r&cYou do not have permission to deactivate boosters."));
                    return;
                }

                $type = $manager->normalizeType((string) ($args["type"] ?? ""));
                $zoneId = strtolower((string) ($args["zone"] ?? ""));
                if ($type === null || $zoneId === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /booster deactivate <zone_xp|fishing> <zone>"));
                    return;
                }

                $ok = $manager->deactivate(
                    $zoneId,
                    $type,
                    C::colorize("&r&c" . $manager->getTypeDisplayName($type) . " &7was manually ended.")
                );
                if (!$ok) {
                    $sender->sendMessage(C::colorize("&r&cThat booster was not active."));
                    return;
                }

                $sender->sendMessage(C::colorize("&r&aStopped &f" . $manager->getTypeDisplayName($type) . " &ain &f{$zoneId}&a."));
                return;
        }

        $this->sendHelp($sender);
    }

    private function sendList(CommandSender $sender, string $zoneFilter): void
    {
        $entries = Loader::getZoneBoosterManager()->getAllActiveBoosters($zoneFilter !== "" ? $zoneFilter : null);
        $sender->sendMessage(C::colorize("&r&l&bActive Boosters"));
        if ($entries === []) {
            $sender->sendMessage(C::colorize("&r&7No active zone/fishing boosters right now."));
            return;
        }

        $now = time();
        foreach ($entries as $entry) {
            $remaining = max(0, $entry["expires_at"] - $now);
            $sender->sendMessage(C::colorize(
                "&r&f" . $entry["zone_display"] .
                " &8| &b" . Loader::getZoneBoosterManager()->getTypeDisplayName($entry["type"]) .
                " &7x" . rtrim(rtrim(number_format($entry["multiplier"], 2), "0"), ".") .
                " &8| &7by &f" . $entry["activated_by"] .
                " &8| &7left: &f" . GeneralUtils::translateTime($remaining)
            ));
        }

        $sender->sendMessage(C::colorize("&r&8Tip: bossbar shows only the newest active booster in your current zone."));
    }

    private function sendHelp(CommandSender $sender): void
    {
        $sender->sendMessage(C::colorize("&r&l&bBooster Commands"));
        $sender->sendMessage(C::colorize("&r&b/booster &7- list active boosters"));
        $sender->sendMessage(C::colorize("&r&b/booster list [zone] &7- list active boosters"));
        $sender->sendMessage(C::colorize("&r&b/booster activate <zone_xp|fishing> <zone> <multiplier> <duration> &8(admin)"));
        $sender->sendMessage(C::colorize("&r&b/booster deactivate <zone_xp|fishing> <zone> &8(admin)"));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
