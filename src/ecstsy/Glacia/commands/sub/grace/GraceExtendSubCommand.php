<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\grace;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\GraceInstance;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\world\sound\NoteInstrument;
use pocketmine\world\sound\NoteSound;

final class GraceExtendSubCommand extends BaseSubCommand {

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

        $grace = GraceInstance::getInstance();
        $grace->extend($seconds);

        $active = $grace->isGraceActive();
        $endTime = $grace->getEndTime();
        $remaining = $grace->getRemaining();

        $msg = "§b§lGrace Period Extended§r\n";
        $msg .= "§7Grace period is a temporary protection for factions. Explosions and certain actions are disabled while grace is active.\n";
        $msg .= "§fStatus: " . ($active ? "§aActive" : "§cInactive") . "\n";
        if ($active) {
            $msg .= "§fEnds: §b" . date("Y-m-d H:i:s", $endTime) . "§f (in §b" . GeneralUtils::translateTime($remaining) . "§f)\n";
        } else {
            $msg .= "§fGrace is currently not active.\n";
        }

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $player->sendMessage($msg);
            $player->getWorld()->addSound($player->getPosition(), new NoteSound(NoteInstrument::PIANO(), 12)); 
        }
    }

    public function getPermission(): string {
        return 'glacia.admin';
    }
}