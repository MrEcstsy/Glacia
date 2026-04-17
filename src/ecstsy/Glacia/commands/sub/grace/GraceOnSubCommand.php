<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\grace;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\GraceInstance;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;

final class GraceOnSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("time", true));
    }

public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
    $timeString = $args["time"] ?? "";
    $seconds = GeneralUtils::parseTimeString($timeString); 

    if ($seconds <= 0) {
        $sender->sendMessage("§cInvalid time format.");
        return;
    }

    GraceInstance::getInstance()->enable($seconds);
    $sender->sendMessage("§aGrace period enabled for " . gmdate("H:i:s", $seconds) . ".");
}

    public function getPermission(): string {
        return 'glacia.admin';
    }
}