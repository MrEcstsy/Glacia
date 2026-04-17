<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\grace;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\GraceInstance;
use pocketmine\command\CommandSender;

final class GraceOffSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        GraceInstance::getInstance()->disable();
        $sender->sendMessage("§cGrace period disabled.");
    }

    public function getPermission(): string {
        return 'glacia.admin';
    }
}