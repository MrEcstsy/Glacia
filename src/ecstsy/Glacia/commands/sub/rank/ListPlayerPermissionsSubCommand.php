<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class ListPlayerPermissionsSubCommand extends BaseSubCommand {

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

        $permissions = $session->getExtraPermissions();
        if ($permissions === []) {
            $sender->sendMessage(C::colorize("&r&e{$session->getUsername()} has no custom player permissions."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&bCustom permissions for &f{$session->getUsername()}&b:"));
        foreach ($permissions as $permission) {
            $sender->sendMessage(C::colorize("&r&7- &f{$permission}"));
        }
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
