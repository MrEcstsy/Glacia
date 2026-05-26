<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\quests;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

abstract class QuestPointsBaseSubCommand extends BaseSubCommand
{
    protected function registerPlayerArgument(): void
    {
        $this->registerArgument(0, new RawStringArgument("player", false));
    }

    protected function registerAmountArgument(): void
    {
        $this->registerArgument(1, new RawStringArgument("amount", false));
    }

    protected function resolveTarget(CommandSender $sender, array $args): ?Player
    {
        $lookup = trim((string) ($args["player"] ?? ""));
        if ($lookup === "") {
            $sender->sendMessage(C::colorize("&r&cPlayer not specified."));
            return null;
        }

        $target = PlayerUtils::getPlayerByPrefix($lookup);
        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cThat player must be online."));
            return null;
        }

        return $target;
    }

    protected function parseAmount(CommandSender $sender, array $args): ?int
    {
        $raw = $args["amount"] ?? null;
        if ($raw === null || !is_numeric((string) $raw)) {
            $sender->sendMessage(C::colorize("&r&cAmount must be a number."));
            return null;
        }

        $amount = (int) round((float) $raw);
        if ($amount < 0) {
            $sender->sendMessage(C::colorize("&r&cAmount must be zero or greater."));
            return null;
        }

        return $amount;
    }

    protected function sendUpdateMessages(CommandSender $sender, Player $target, string $verb, int $newTotal): void
    {
        $sender->sendMessage(C::colorize("&r&a{$verb} quest points for &f" . $target->getName() . "&a. New total: &f" . number_format($newTotal) . "&a."));
        if ($sender !== $target) {
            $target->sendMessage(C::colorize("&r&eYour quest points were updated. New total: &6" . number_format($newTotal)));
        }
    }

    protected function manager()
    {
        return Loader::getQuestBoardManager();
    }

    public function getPermission(): ?string
    {
        return "glacia.admin";
    }
}
