<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\args\RawStringArgument;
use ecstsy\Glacia\commands\sub\quests\QuestAddPointsSubCommand;
use ecstsy\Glacia\commands\sub\quests\QuestPointsSubCommand;
use ecstsy\Glacia\commands\sub\quests\QuestRemovePointsSubCommand;
use ecstsy\Glacia\commands\sub\quests\QuestSetPointsSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\quests\QuestShopScreen;
use ecstsy\Glacia\utils\screens\quests\QuestsScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class QuestsCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("section", true));
        $this->registerSubCommand(new QuestPointsSubCommand(Loader::getInstance(), "points", "View a player's quest points"));
        $this->registerSubCommand(new QuestAddPointsSubCommand(Loader::getInstance(), "addpoints", "Add quest points to a player"));
        $this->registerSubCommand(new QuestRemovePointsSubCommand(Loader::getInstance(), "removepoints", "Remove quest points from a player"));
        $this->registerSubCommand(new QuestSetPointsSubCommand(Loader::getInstance(), "setpoints", "Set a player's quest points"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cUsage: /quest [shop|points|addpoints|removepoints|setpoints]"));
            return;
        }

        if (!Loader::getQuestBoardManager()->isEnabled()) {
            $sender->sendMessage(C::colorize("&r&cQuests are currently disabled."));
            return;
        }

        $section = strtolower(trim((string) ($args["section"] ?? "")));
        if ($section === "shop" || $section === "store") {
            QuestShopScreen::display($sender);
            return;
        }

        QuestsScreen::display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
