<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\quests;

use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class QuestPointsSubCommand extends QuestPointsBaseSubCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerPlayerArgument();
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $target = $this->resolveTarget($sender, $args);
        if ($target === null) {
            return;
        }

        $sender->sendMessage(C::colorize("&r&f" . $target->getName() . " &7has &6" . number_format($this->manager()->getQuestPoints($target)) . " &7quest points."));
    }
}
