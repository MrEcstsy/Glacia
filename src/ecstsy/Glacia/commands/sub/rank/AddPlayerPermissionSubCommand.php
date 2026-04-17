<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AddPlayerPermissionSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("permission"));
        $this->registerArgument(1, new RawStringArgument("player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $permission = trim((string) $args["permission"]);
        $name = (string) $args["player"];

        $target = Loader::getPlayerManager()->getSessionByName($name)?->getPlayer();
        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cError: Player '$name' not found or offline."));
            return;
        }

        $rankManager = RankManager::getInstance();
        try {
            $attachment = $rankManager->getAttachment($target);
        } catch (\RuntimeException) {
            $attachment = $target->addAttachment(Loader::getInstance());
            $rankManager->setAttachment($target, $attachment);
        }

        if (($attachment->getPermissions()[$permission] ?? false) === true) {
            $sender->sendMessage(C::colorize("&r&ePlayer already has '$permission'."));
            return;
        }

        $attachment->setPermission($permission, true);
        $target->recalculatePermissions();

        $sender->sendMessage(C::colorize("&r&aGave player '{$target->getName()}' permission '$permission'."));
        $target->sendMessage(C::colorize("&r&aYou have been granted permission '$permission'."));

        GlaciaUtils::logAdminAction("Add Player Permission", [
            "admin" => $sender->getName(),
            "target" => $target->getName(),
            "permission" => $permission
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
