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
use pocketmine\utils\TextFormat as C;

final class FreezeCommand extends BaseCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
        $this->registerArgument(1, new RawStringArgument("duration", true));
        $this->registerArgument(2, new TextArgument("reason", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $target = PlayerUtils::getPlayerByPrefix((string) ($args["player"] ?? ""));
        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cThat player must be online to freeze."));
            return;
        }
        if (strcasecmp($sender->getName(), $target->getName()) === 0) {
            $sender->sendMessage(C::colorize("&r&cYou cannot freeze yourself."));
            return;
        }

        $mgr = ModerationManager::getInstance();
        $uuid = $target->getUniqueId()->toString();
        if ($mgr->isFrozen($uuid)) {
            $mgr->unfreeze($uuid, $sender->getName());
            $sender->sendMessage(C::colorize("&r&aUnfroze &f" . $target->getName() . "&a."));
            $target->sendMessage(C::colorize("&r&aYou are no longer frozen."));
            return;
        }

        $durationArg = trim((string) ($args["duration"] ?? ""));
        $reason = trim((string) ($args["reason"] ?? ""));
        $durationSpec = "10m";
        if ($durationArg !== "") {
            if (GeneralUtils::parseTimeString($durationArg) > 0) {
                $durationSpec = $durationArg;
            } else {
                $reason = trim($durationArg . ($reason !== "" ? " " . $reason : ""));
            }
        }
        if ($reason === "") {
            $reason = "Under investigation";
        }

        $seconds = GeneralUtils::parseTimeString($durationSpec);
        $until = $seconds > 0 ? time() + $seconds : null;
        $mgr->setFrozen($uuid, $target->getName(), $sender->getName(), $reason, $until);

        $durationText = $seconds > 0 ? GeneralUtils::translateTime($seconds) : "until manual unfreeze";
        $sender->sendMessage(C::colorize("&r&bFroze &f{$target->getName()} &7for &f{$durationText}&7. &7Reason: &f{$reason}"));
        $target->sendTitle(C::colorize("&r&c&lFROZEN"), C::colorize("&r&7Do not log out. Staff are reviewing your account."), 5, 40, 5);
        $target->sendMessage(C::colorize("&r&cYou have been frozen by staff for &f{$durationText}&c. Reason: &f{$reason}&c."));
        PlayerUtils::playSound($target, "note.bass", 1, 0.8);

        GlaciaUtils::logAdminAction("Freeze Player", [
            "admin" => $sender->getName(),
            "target" => $target->getName(),
            "duration" => $durationSpec,
            "reason" => $reason,
        ]);
    }

    public function getPermission(): string {
        return "glacia.staff.freeze";
    }
}

