<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\staff\ModerationManager;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class StaffHistoryCommand extends BaseCommand {
    private const PER_PAGE = 8;

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
        $this->registerArgument(1, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $target = strtolower(trim((string) ($args["player"] ?? "")));
        if ($target === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /staffhistory <player> [page]"));
            return;
        }
        $page = max(1, (int) ($args["page"] ?? 1));
        $mgr = ModerationManager::getInstance();
        $max = $mgr->getHistoryPages($target, self::PER_PAGE);
        if ($page > $max) {
            $sender->sendMessage(C::colorize("&r&cThat page does not exist. Max: &f{$max}"));
            return;
        }
        $rows = $mgr->getHistoryPage($target, $page, self::PER_PAGE);
        $sender->sendMessage(C::colorize("&r&8-=-=-=&7-=-=-= &bStaff History &7({$target}) &f{$page}/{$max} &7-=-=-=&8-=-=-="));
        if ($rows === []) {
            $sender->sendMessage(C::colorize("&r&7No moderation history recorded yet."));
            return;
        }
        foreach ($rows as $row) {
            $at = isset($row["at"]) ? date("M j g:i A", (int) $row["at"]) : "?";
            $action = (string) ($row["action"] ?? "action");
            $staff = (string) ($row["staff"] ?? "unknown");
            $reason = (string) ($row["reason"] ?? "");
            $extra = isset($row["count"]) ? " (#" . $row["count"] . ")" : "";
            $sender->sendMessage(C::colorize("&r&8[&7{$at}&8] &b{$action}{$extra} &7by &f{$staff}" . ($reason !== "" ? "&7 - &f{$reason}" : "")));
        }
    }

    public function getPermission(): string {
        return "glacia.staff.history";
    }
}

