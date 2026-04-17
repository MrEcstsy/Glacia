<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\grace\GraceExtendSubCommand;
use ecstsy\Glacia\commands\sub\grace\GraceInfoSubCommand;
use ecstsy\Glacia\commands\sub\grace\GraceOffSubCommand;
use ecstsy\Glacia\commands\sub\grace\GraceOnSubCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;

final class GraceCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerSubCommand(new GraceExtendSubCommand(Loader::getInstance(), "extend", "Extend grace period"));
        $this->registerSubCommand(new GraceOnSubCommand(Loader::getInstance(), "on", "Enable grace period"));
        $this->registerSubCommand(new GraceOffSubCommand(Loader::getInstance(), "off", "Disable grace period"));
        $this->registerSubCommand(new GraceInfoSubCommand(Loader::getInstance(), "info", "Show grace period info"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $sender->sendMessage("§bUsage: /grace <on|off|extend|info>");
    }

    public function getPermission(): string {
        return 'glacia.admin';
    }
}