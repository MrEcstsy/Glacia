<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\scoreboard\ScoreboardHelper;
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
        if ($state === "") {
            $enabled = $session->getSetting("scoreboard");
            $enabled = $enabled === null ? true : (bool) $enabled;
            $sender->sendMessage(C::colorize("&r&bScoreboard&7: " . ($enabled ? "&aON" : "&cOFF")));
            $sender->sendMessage(C::colorize("&r&7Use &f/scoreboard on &7or &f/scoreboard off&7."));
            return;
        }

        if ($state !== "on" && $state !== "off") {
            $sender->sendMessage(C::colorize("&r&cUsage: /scoreboard <on|off>"));
            return;
        }

        $visible = $state === "on";
        $session->setSetting("scoreboard", $visible);
        ScoreboardHelper::setVisible($sender, $visible);

        $sender->sendMessage(C::colorize($visible
            ? "&r&aScoreboard enabled."
            : "&r&cScoreboard hidden."
        ));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
