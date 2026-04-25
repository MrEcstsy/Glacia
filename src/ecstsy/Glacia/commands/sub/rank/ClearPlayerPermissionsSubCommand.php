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

final class ClearPlayerPermissionsSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $name = (string) $args["player"];
        $session = Loader::getPlayerManager()->getSessionByPrefix($name);

        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cError: Player '$name' not found."));
            return;
        }

        if ($session->getExtraPermissions() === []) {
            $sender->sendMessage(C::colorize("&r&e{$session->getUsername()} has no custom player permissions to clear."));
            return;
        }

        $session->clearExtraPermissions();
        $target = PlayerUtils::getPlayerByPrefix($session->getUsername());
        if ($target instanceof Player) {
            RankManager::getInstance()->updatePermission($target);
            $target->sendMessage(C::colorize("&r&cYour custom player permissions were cleared."));
        }

        $sender->sendMessage(C::colorize("&r&aCleared all custom permissions for &f{$session->getUsername()}&a."));
        GlaciaUtils::logAdminAction("Clear Player Permissions", [
            "admin" => $sender->getName(),
            "target" => $session->getUsername()
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
