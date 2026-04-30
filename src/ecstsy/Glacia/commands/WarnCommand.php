<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\staff\ModerationManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class WarnCommand extends BaseCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
        $this->registerArgument(1, new TextArgument("reason"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $targetInput = trim((string) ($args["player"] ?? ""));
        $reason = trim((string) ($args["reason"] ?? ""));
        if ($targetInput === "" || $reason === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /warn <player> <reason>"));
            return;
        }

        $targetPlayer = PlayerUtils::getPlayerByPrefix($targetInput);
        $targetSession = $targetPlayer !== null
            ? Loader::getPlayerManager()->getSession($targetPlayer)
            : Loader::getPlayerManager()->getSessionByPrefix($targetInput);

        if ($targetSession === null) {
            $sender->sendMessage(C::colorize("&r&cPlayer profile not found: &f{$targetInput}&c."));
            return;
        }

        $targetName = $targetSession->getUsername();
        if (strcasecmp($sender->getName(), $targetName) === 0) {
            $sender->sendMessage(C::colorize("&r&cYou cannot warn yourself."));
            return;
        }

        $mgr = ModerationManager::getInstance();
        $targetKey = $mgr->resolveTargetKey($targetName);
        $count = $mgr->incrementWarn($targetName, $targetKey, $sender->getName(), $reason);

        $sender->sendMessage(C::colorize("&r&eWarned &f{$targetName}&e. &7Warn count: &f{$count}&7. &7Reason: &f{$reason}"));
        if ($targetPlayer !== null) {
            $targetPlayer->sendTitle(C::colorize("&r&c&lWARNING"), C::colorize("&r&f{$reason}"), 5, 30, 5);
            $targetPlayer->sendMessage(C::colorize("&r&cYou received a warning (&f{$count}&c). Reason: &f{$reason}"));
            PlayerUtils::playSound($targetPlayer, "random.orb", 1, 0.6);
        }

        GlaciaUtils::logAdminAction("Warn Player", [
            "admin" => $sender->getName(),
            "target" => $targetName,
            "count" => $count,
            "reason" => $reason,
        ]);

        $escalation = $mgr->getEscalationDurationForCount($count);
        if ($escalation !== null) {
            $seconds = GeneralUtils::parseTimeString($escalation);
            if ($seconds > 0) {
                $banList = Server::getInstance()->getNameBans();
                if (!$banList->isBanned($targetName)) {
                    $expires = new \DateTime("+" . $seconds . " seconds");
                    $banReason = "Warn escalation ({$count} warnings)";
                    $banList->addBan($targetName, $banReason, $expires, $sender->getName());
                    if ($targetPlayer !== null) {
                        $targetPlayer->kick(C::colorize("&cBanned: &f{$banReason}"));
                    }
                    $sender->sendMessage(C::colorize("&r&cEscalation applied: &f{$targetName} &7banned for &f" . GeneralUtils::translateTime($seconds)));
                    GlaciaUtils::logAdminAction("Warn Escalation Ban", [
                        "admin" => $sender->getName(),
                        "target" => $targetName,
                        "warnings" => $count,
                        "duration" => $escalation,
                    ]);
                }
            }
        }
    }

    public function getPermission(): string {
        return "glacia.staff.warn";
    }
}

