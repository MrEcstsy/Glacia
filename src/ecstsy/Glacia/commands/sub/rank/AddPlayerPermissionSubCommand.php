<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\ranks\RankManager;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class AddPlayerPermissionSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("permission", false));
        $this->registerArgument(1, new RawStringArgument("player", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $perm = $args["permission"];
        $name = $args["player"];

        $target = Loader::getPlayerManager()->getSessionByName($name)?->getPlayer();
        if (!$target instanceof Player) {
            $sender->sendMessage("§cError: Player '$name' not found or offline.");
            return;
        }

        $rm = RankManager::getInstance();
        try {
            $attach = $rm->getAttachment($target);
        } catch (\RuntimeException $e) {
            $attach = $target->addAttachment(Loader::getInstance());
            $rm->setAttachment($target, $attach);
        }

        if ($attach->getPermissions()[$perm] ?? false) {
            $sender->sendMessage("§ePlayer already has '$perm'.");
            return;
        }

        $attach->setPermission($perm, true);
        $sender->sendMessage("§aGave player '{$target->getName()}' permission '$perm'.");
        $target->sendMessage("§aYou have been granted permission '$perm'.");
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
