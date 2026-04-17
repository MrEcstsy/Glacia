<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use ecstsy\Glacia\managers\TPAManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\utils\TextFormat as C;

final class TPAAcceptCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new PlayerArgument("sender", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) return;

        $requester = $args["sender"] ?? null;
        if ($requester === null || !$requester->isOnline()) {
            $sender->sendMessage(GlaciaUtils::format('player.error.invalid', ['{name}' => $requester->getName()], 'error'));
            return;
        }
        TPAManager::acceptTPA($sender, $requester);
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}