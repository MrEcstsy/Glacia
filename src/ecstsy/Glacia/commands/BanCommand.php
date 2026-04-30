<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class BanCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
        $this->registerArgument(1, new RawStringArgument("preset_or_duration", true));
        $this->registerArgument(2, new TextArgument("reason", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $targetName = trim((string) $args["player"]);
        $inputToken = trim((string) ($args["preset_or_duration"] ?? ""));
        $inputReason = trim((string) ($args["reason"] ?? ""));

        if (strcasecmp($targetName, "presets") === 0) {
            $this->sendPresetList($sender);
            return;
        }

        if (strcasecmp($sender->getName(), $targetName) === 0) {
            $sender->sendMessage(C::colorize("&r&cYou cannot ban yourself."));
            return;
        }

        $banList = Server::getInstance()->getNameBans();
        if ($banList->isBanned($targetName)) {
            $sender->sendMessage(C::colorize("&r&e{$targetName} is already banned."));
            return;
        }

        $preset = $this->resolvePreset($inputToken);
        $durationSpec = "";
        $durationLabel = "Permanent";
        $reason = $inputReason;

        if ($preset !== null) {
            $durationSpec = $preset["duration"];
            $durationLabel = $preset["duration_text"];
            if ($reason === "") {
                $reason = $preset["reason"];
            }
        } elseif ($inputToken !== "") {
            $seconds = GeneralUtils::parseTimeString($inputToken);
            if ($seconds > 0) {
                $durationSpec = $inputToken;
                $durationLabel = GeneralUtils::translateTime($seconds);
            } elseif (in_array(strtolower($inputToken), ["perm", "permanent"], true)) {
                $durationSpec = "";
                $durationLabel = "Permanent";
            } else {
                // Backward compatibility: /ban <player> <reason...>
                $reason = trim($inputToken . ($inputReason !== "" ? " " . $inputReason : ""));
            }
        }

        if ($reason === "") {
            $reason = "No reason provided";
        }

        $expires = null;
        if ($durationSpec !== "") {
            $seconds = GeneralUtils::parseTimeString($durationSpec);
            if ($seconds <= 0) {
                $sender->sendMessage(C::colorize("&r&cInvalid duration. Use /ban presets for examples."));
                return;
            }
            $expires = new \DateTime("+" . $seconds . " seconds");
        }

        $banList->addBan($targetName, $reason, $expires, $sender->getName());

        $onlineTarget = PlayerUtils::getPlayerByPrefix($targetName);
        if ($onlineTarget !== null && strcasecmp($onlineTarget->getName(), $targetName) === 0) {
            $onlineTarget->kick(C::colorize("&r&cYou are banned.\n&7Duration: &f{$durationLabel}\n&7Reason: &f{$reason}"));
            $targetName = $onlineTarget->getName();
        }

        $sender->sendMessage(C::colorize("&r&aBanned &f{$targetName}&a. &7Duration: &f{$durationLabel}&7. &7Reason: &f{$reason}"));
        if ($sender instanceof Player) {
            $sender->sendTitle(C::colorize("&r&c&lBAN APPLIED"), C::colorize("&r&f{$targetName} &7- &f{$durationLabel}"), 5, 30, 5);
            PlayerUtils::playSound($sender, "note.pling", 1, 1.2);
        }

        GlaciaUtils::logAdminAction("Ban Player", [
            "admin" => $sender->getName(),
            "target" => $targetName,
            "reason" => $reason,
            "duration" => $durationSpec !== "" ? $durationSpec : "permanent",
            "duration_label" => $durationLabel,
        ]);
    }

    private function sendPresetList(CommandSender $sender): void {
        $presets = $this->loadBanPresets();
        $sender->sendMessage(C::colorize("&r&8-=-=-=&7-=-=-= &cBan Presets &7-=-=-=&8-=-=-="));
        foreach ($presets as $preset) {
            $sender->sendMessage(C::colorize("&r&c" . $preset["id"] . " &7-> &f" . $preset["duration_text"] . " &8(" . $preset["reason"] . ")"));
        }
        $sender->sendMessage(C::colorize("&r&7Usage: &f/ban <player> <preset|duration> [reason]&7. Example: &f/ban Steve killaura"));
    }

    /**
     * @return array{id: string, duration: string, duration_text: string, reason: string}|null
     */
    private function resolvePreset(string $token): ?array {
        if ($token === "") {
            return null;
        }
        $normalized = strtolower(trim($token));
        foreach ($this->loadBanPresets() as $preset) {
            if ($normalized === $preset["id"]) {
                return $preset;
            }
        }
        return null;
    }

    /**
     * @return list<array{id: string, duration: string, duration_text: string, reason: string}>
     */
    private function loadBanPresets(): array {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $section = (array) ($cfg?->get("ban-presets", []));
        $rows = (array) ($section["entries"] ?? []);

        $presets = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = strtolower(trim((string) ($row["id"] ?? "")));
            if ($id === "") {
                continue;
            }
            $duration = trim((string) ($row["duration"] ?? ""));
            $reason = trim((string) ($row["reason"] ?? ""));
            if ($reason === "") {
                $reason = "Rule violation";
            }
            $durationText = $duration !== "" ? GeneralUtils::translateTime(max(1, GeneralUtils::parseTimeString($duration))) : "Permanent";
            $presets[] = [
                "id" => $id,
                "duration" => $duration,
                "duration_text" => $durationText,
                "reason" => $reason,
            ];
        }

        if ($presets === []) {
            return [
                ["id" => "killaura", "duration" => "7d", "duration_text" => "7 days", "reason" => "KillAura"],
                ["id" => "autoclicker", "duration" => "3d", "duration_text" => "3 days", "reason" => "AutoClicker"],
                ["id" => "xray", "duration" => "14d", "duration_text" => "14 days", "reason" => "X-Ray"],
                ["id" => "chargeback", "duration" => "", "duration_text" => "Permanent", "reason" => "Chargeback"],
            ];
        }
        return $presets;
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}
