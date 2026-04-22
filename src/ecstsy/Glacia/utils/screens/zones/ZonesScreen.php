<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\zones;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\levels\PlayerLevelManager;
use ecstsy\Glacia\server\zones\ZoneManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class ZonesScreen {

    public static function display(Player $player) : void
    {
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return;
        }

        $zones = array_values(ZoneManager::getZones());
        $form = new SimpleForm(function(Player $player, ?int $data) use ($zones, $session) : void {
            if ($data === null) {
                return;
            }

            $zone = $zones[$data] ?? null;
            if ($zone === null) {
                return;
            }

            if (!ZoneManager::canAccess($session->getLevel(), $zone)) {
                $player->sendMessage(C::colorize("&r&cThat zone unlocks at level &f" . number_format(ZoneManager::getUnlockRequirement($zone)) . "&c."));
                return;
            }

            if (!ZoneManager::teleportPlayer($player, $zone)) {
                $player->sendMessage(C::colorize("&r&cThat zone world is not loaded right now."));
                return;
            }

            $player->sendMessage(C::colorize("&r&a✔ &r&7Teleported to &f" . (string) $zone["display"] . "&7."));
        });

        $form->setTitle(C::colorize("&r&8Zones"));
        $form->setContent(C::colorize(
            "&r&7Your Level: &f" . number_format($session->getLevel()) . "&7/&f" . number_format(PlayerLevelManager::getMaxLevel()) . "\n" .
            "&r&7Pick a zone to travel to."
        ));

        foreach ($zones as $zone) {
            $unlocked = ZoneManager::canAccess($session->getLevel(), $zone);
            $status = $unlocked ? "&aUnlocked" : "&cLocked";
            $form->addButton(C::colorize(
                "&r&b" . (string) $zone["display"] . "\n" .
                "&r&8Rec Level: &f" . number_format((int) $zone["recommended_level"]) . "&8 | {$status}"
            ));
        }

        $player->sendForm($form);
    }
}
