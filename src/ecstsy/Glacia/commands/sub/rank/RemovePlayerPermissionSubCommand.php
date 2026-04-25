<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class RemovePlayerPermissionSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("permission"));
        $this->registerArgument(1, new RawStringArgument("player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $permission = trim((string) $args["permission"]);
        $name = (string) $args["player"];

        $session = Loader::getPlayerManager()->getSessionByPrefix($name);
        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cError: Player '$name' not found."));
            return;
        }

        if (!$session->hasExtraPermission($permission)) {
            $sender->sendMessage(C::colorize("&r&ePlayer does not have '$permission'."));
            return;
        }

        $session->removeExtraPermission($permission);
        $target = PlayerUtils::getPlayerByPrefix($session->getUsername());
        if ($target instanceof Player) {
            RankManager::getInstance()->updatePermission($target);
            $target->sendMessage(C::colorize("&r&cPermission '$permission' was removed from your account."));
        }

        $sender->sendMessage(C::colorize("&r&aRemoved permission '$permission' from '{$session->getUsername()}'."));
        GlaciaUtils::logAdminAction("Remove Player Permission", [
            "admin" => $sender->getName(),
            "target" => $session->getUsername(),
            "permission" => $permission
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
