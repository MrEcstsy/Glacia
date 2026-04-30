<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\staff\ModerationManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class UnfreezeCommand extends BaseCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $target = PlayerUtils::getPlayerByPrefix((string) ($args["player"] ?? ""));
        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cThat player must be online to unfreeze."));
            return;
        }

        $mgr = ModerationManager::getInstance();
        $uuid = $target->getUniqueId()->toString();
        if (!$mgr->unfreeze($uuid, $sender->getName())) {
            $sender->sendMessage(C::colorize("&r&e" . $target->getName() . " is not currently frozen."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&aUnfroze &f" . $target->getName() . "&a."));
        $target->sendMessage(C::colorize("&r&aA staff member has unfrozen you."));
        GlaciaUtils::logAdminAction("Unfreeze Player", [
            "admin" => $sender->getName(),
            "target" => $target->getName(),
        ]);
    }

    public function getPermission(): string {
        return "glacia.staff.freeze";
    }
}

