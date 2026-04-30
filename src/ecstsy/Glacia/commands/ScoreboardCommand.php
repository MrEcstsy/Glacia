<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\scoreboard\ScoreboardHelper;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use ecstsy\Glacia\utils\screens\scoreboard\ScoreboardScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ScoreboardCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("state", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null) {
            return;
        }

        $state = strtolower((string) ($args["state"] ?? ""));
        switch ($state) {
            case "":
                ScoreboardScreen::displayMain($sender);
                return;
            case "on":
            case "off":
                $visible = $state === "on";
                $session->setSetting("scoreboard", $visible);
                ScoreboardHelper::setVisible($sender, $visible);
                $sender->sendMessage(C::colorize(MessageTemplates::render($visible ? "scoreboard.message.enabled" : "scoreboard.message.disabled")));
                return;
            case "edit":
                ScoreboardScreen::displayCustomEditor($sender);
                return;
            case "profiles":
            case "profile":
                ScoreboardScreen::displayProfilePicker($sender);
                return;
            default:
                $sender->sendMessage(C::colorize(MessageTemplates::render("scoreboard.usage")));
                return;
        }
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
