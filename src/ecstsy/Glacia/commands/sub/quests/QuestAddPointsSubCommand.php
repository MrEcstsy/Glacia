<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\quests;

use pocketmine\command\CommandSender;

final class QuestAddPointsSubCommand extends QuestPointsBaseSubCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerPlayerArgument();
        $this->registerAmountArgument();
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $target = $this->resolveTarget($sender, $args);
        $amount = $this->parseAmount($sender, $args);
        if ($target === null || $amount === null) {
            return;
        }

        $this->sendUpdateMessages($sender, $target, "Added", $this->manager()->addQuestPoints($target, $amount));
    }
}
