<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\staff\ModerationManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class ReportsCommand extends BaseCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new IntegerArgument("id", true));
        $this->registerArgument(2, new TextArgument("note", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $action = strtolower(trim((string) ($args["action"] ?? "list")));
        $mgr = ModerationManager::getInstance();

        if ($action === "claim") {
            $id = (int) ($args["id"] ?? 0);
            if ($id <= 0) {
                $sender->sendMessage(C::colorize("&r&cUsage: /reports claim <id>"));
                return;
            }
            if (!$mgr->claimReport($id, $sender->getName())) {
                $sender->sendMessage(C::colorize("&r&cCould not claim report #{$id} (missing, closed, or already claimed)."));
                return;
            }
            $sender->sendMessage(C::colorize("&r&aYou claimed report #{$id}."));
            return;
        }

        if ($action === "close") {
            $id = (int) ($args["id"] ?? 0);
            if ($id <= 0) {
                $sender->sendMessage(C::colorize("&r&cUsage: /reports close <id> [note]"));
                return;
            }
            $note = trim((string) ($args["note"] ?? ""));
            $closed = $mgr->closeReport($id, $sender->getName(), $note);
            if ($closed === null) {
                $sender->sendMessage(C::colorize("&r&cReport #{$id} was not found."));
                return;
            }
            $sender->sendMessage(C::colorize("&r&aClosed report #{$id}." . ($note !== "" ? " &7Note: &f{$note}" : "")));

            $reporter = (string) ($closed["reporter"] ?? "");
            if ($reporter !== "") {
                $reporterPlayer = PlayerUtils::getPlayerByPrefix($reporter);
                if ($reporterPlayer !== null && strcasecmp($reporterPlayer->getName(), $reporter) === 0) {
                    $target = (string) ($closed["target"] ?? "that player");
                    $reporterPlayer->sendMessage(C::colorize("&r&aYour report #{$id} against &f{$target}&a was reviewed and closed by staff."));
                }
            }
            return;
        }

        $rows = $mgr->getOpenReports();
        $sender->sendMessage(C::colorize("&r&8-=-=-=&7-=-=-= &cOpen Reports &7(" . count($rows) . ") &8-=-=-=&7-=-=-="));
        if ($rows === []) {
            $sender->sendMessage(C::colorize("&r&7No open reports."));
            return;
        }
        foreach (array_slice($rows, 0, 10) as $row) {
            $id = (int) ($row["id"] ?? 0);
            $at = isset($row["created_at"]) ? date("M j g:i A", (int) $row["created_at"]) : "?";
            $reporter = (string) ($row["reporter"] ?? "unknown");
            $target = (string) ($row["target"] ?? "unknown");
            $reason = (string) ($row["reason"] ?? "");
            $sender->sendMessage(C::colorize("&r&c#{$id} &8[{$at}] &f{$reporter} &7-> &f{$target}&7: &f{$reason}"));
        }
        $sender->sendMessage(C::colorize("&r&7Use &f/reports claim <id> &7or &f/reports close <id> [note]&7."));
    }

    public function getPermission(): string {
        return "glacia.staff.reports";
    }
}

