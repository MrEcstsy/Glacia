<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\screens\zones\ZonesScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class NetherCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
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

        $zone = ZoneManager::getNetherShortcutZone();
        if ($zone === null) {
            $sender->sendMessage(C::colorize("&r&cThe nether zone shortcut is not configured yet."));
            return;
        }

        if (!ZoneManager::canAccess($session->getLevel(), $zone)) {
            $sender->sendMessage(C::colorize("&r&cYou unlock &f" . (string) $zone["display"] . " &cat level &f" . number_format(ZoneManager::getUnlockRequirement($zone)) . "&c."));
            ZonesScreen::display($sender);
            return;
        }

        if (!ZoneManager::teleportPlayer($sender, $zone)) {
            $sender->sendMessage(C::colorize("&r&cThat zone world is not loaded right now."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&a✔ &r&7Teleported to &f" . (string) $zone["display"] . "&7."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
