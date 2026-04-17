<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FixAllCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $fix = GlaciaUtils::repairAll($sender);

        if ($fix) {
            $sender->sendMessage(GlaciaUtils::format("fixall.success", [], "success"));
        } else {
            $sender->sendMessage(GlaciaUtils::format("fixall.no_damaged", [], "error"));
        }
    }

    public function getPermission(): string {
        return "glacia.fixall";
    }
}