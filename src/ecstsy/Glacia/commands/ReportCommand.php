<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\staff\ModerationManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class ReportCommand extends BaseCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
        $this->registerArgument(1, new TextArgument("reason"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            return;
        }
        $targetName = trim((string) ($args["player"] ?? ""));
        $reason = trim((string) ($args["reason"] ?? ""));
        if ($targetName === "" || $reason === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /report <player> <reason>"));
            return;
        }
        if (strcasecmp($sender->getName(), $targetName) === 0) {
            $sender->sendMessage(C::colorize("&r&cYou cannot report yourself."));
            return;
        }

        $mgr = ModerationManager::getInstance();
        $uuid = $sender->getUniqueId()->toString();
        if (!$mgr->canSubmitReport($uuid)) {
            $remaining = $mgr->getReportCooldownRemaining($uuid);
            $sender->sendMessage(C::colorize("&r&ePlease wait &f" . GeneralUtils::translateTime($remaining) . " &ebefore sending another report."));
            return;
        }

        $duplicate = $mgr->findRecentDuplicateOpenReport($uuid, $targetName);
        if ($duplicate !== null) {
            $existingId = (int) ($duplicate["id"] ?? 0);
            $sender->sendMessage(C::colorize("&r&eYou already have an open recent report on &f{$targetName}&e (#{$existingId}). Please wait for staff."));
            return;
        }

        $mgr->markReportSubmitted($uuid);

        $id = $mgr->createReport($sender->getName(), $uuid, $targetName, $reason);
        $sender->sendMessage(C::colorize("&r&aReport #{$id} submitted against &f{$targetName}&a. Staff have been notified."));
        PlayerUtils::playSound($sender, "note.pling", 1, 1.2);

        $notifyPerm = $mgr->getReportNotifyPermission();
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if (!$player->hasPermission($notifyPerm)) {
                continue;
            }
            $player->sendTitle(C::colorize("&r&c&lNEW REPORT"), C::colorize("&r&f#{$id} {$sender->getName()} -> {$targetName}"), 5, 25, 5);
            $player->sendMessage(C::colorize("&r&c[REPORT #{$id}] &f{$sender->getName()} &7reported &f{$targetName}&7: &f{$reason}"));
            PlayerUtils::playSound($player, "note.bass", 1, 1.1);
        }

        if ($mgr->isReportDiscordEnabled()) {
            GlaciaUtils::logAdminAction("Player Report", [
                "report_id" => $id,
                "reporter" => $sender->getName(),
                "target" => $targetName,
                "reason" => $reason,
            ]);
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}

