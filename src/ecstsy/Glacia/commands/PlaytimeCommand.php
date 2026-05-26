<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PlaytimeCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("name", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        $name = isset($args["name"]) ? (string) $args["name"] : null;
        if ($name === null || trim($name) === "") {
            $session = Loader::getPlayerManager()->getSession($sender);
            if ($session === null) {
                $sender->sendMessage(C::colorize("&r&cNo profile loaded."));
                return;
            }

            $this->sendPlaytime($sender, $session, $sender->getName());
            return;
        }

        if (
            !$sender->hasPermission("glacia.playtime.others")
            && !$sender->hasPermission("glacia.staff")
            && !$sender->hasPermission("glacia.admin")
            && !$sender->isOp()
        ) {
            $sender->sendMessage(C::colorize("&r&cYou cannot view other players' playtime."));
            return;
        }

        $target = PlayerUtils::getPlayerByPrefix($name);
        if ($target !== null) {
            $session = Loader::getPlayerManager()->getSession($target);
            if ($session !== null) {
                $this->sendPlaytime($sender, $session, $target->getName());
                return;
            }
        }

        $session = Loader::getPlayerManager()->getSessionByPrefix($name);
        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cNo player profile matches &f{$name}&c."));
            return;
        }

        $this->sendPlaytime($sender, $session, $session->getUsername());
    }

    private function sendPlaytime(Player $viewer, GlaciaPlayer $session, string $label): void
    {
        $sec = $session->getDisplayPlaytimeSeconds();
        $formatted = GlaciaPlayer::formatPlaytime($sec);
        $viewer->sendMessage(C::colorize("&r&7Total playtime for &f{$label}&7: &a{$formatted} &8(&f{$sec}s&8)"));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
