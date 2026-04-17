<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\grace;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\GraceInstance;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;

final class GraceInfoSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $grace = GraceInstance::getInstance();
        $active = $grace->isGraceActive();
        $endTime = $grace->getEndTime();
        $remaining = $grace->getRemaining();

        $msg = "§b§lGrace Period Info§r\n";
        $msg .= "§7Grace period is a temporary protection for factions. Explosions and certain actions are disabled while grace is active.\n";
        $msg .= "§fStatus: " . ($active ? "§aActive" : "§cInactive") . "\n";
        
        if ($active) {
            $msg .= "§fEnds: §b" . date("Y-m-d H:i:s", $endTime) . "§f (in §b" . GeneralUtils::translateTime($remaining) . "§f)\n";
        } else {
            $msg .= "§fGrace is currently not active.\n";
        }

        $sender->sendMessage($msg);
    }

    public function getPermission(): string {
        return 'glacia.default';
    }
}