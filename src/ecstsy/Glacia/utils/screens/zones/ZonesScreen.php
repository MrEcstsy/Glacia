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
        $form = new SimpleForm(function(Player $player, ?int $data) use ($zones) : void {
            if ($data === null) {
                return;
            }

            $zone = $zones[$data] ?? null;
            if ($zone === null) {
                return;
            }

            self::displayDetails($player, $zone);
        });

        $form->setTitle(C::colorize("&r&8Zones"));
        $form->setContent(C::colorize(
            "&r&7Your Level: &f" . number_format($session->getLevel()) . "&7/&f" . number_format(PlayerLevelManager::getMaxLevel()) . "\n" .
            "&r&7Pick a zone to inspect."
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

    public static function displayDetails(Player $player, array $zone) : void
    {
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return;
        }

        $unlocked = ZoneManager::canAccess($session->getLevel(), $zone);
        $unlockLevel = ZoneManager::getUnlockRequirement($zone);
        $form = new SimpleForm(function(Player $player, ?int $data) use ($zone, $unlocked, $unlockLevel) : void {
            if ($data === null) {
                return;
            }

            if ($unlocked && $data === 0) {
                if (!ZoneManager::canPlayerAccess($player, $zone)) {
                    $player->sendMessage(C::colorize("&r&cThat zone unlocks at level &f" . number_format($unlockLevel) . "&c."));
                    return;
                }

                if (!ZoneManager::teleportPlayer($player, $zone)) {
                    $player->sendMessage(C::colorize("&r&cThat zone world is not loaded right now."));
                    return;
                }

                $player->sendMessage(C::colorize("&r&aTeleported to &f" . (string) $zone["display"] . "&r&a."));
                return;
            }

            self::display($player);
        });

        $status = $unlocked ? "&aUnlocked" : "&cLocked";
        $form->setTitle(C::colorize("&r&8" . (string) $zone["display"]));
        $form->setContent(C::colorize(
            (string) ($zone["description"] ?? "&7No details configured.") . "\n\n" .
            "&r&7Recommended Level: &f" . number_format((int) ($zone["recommended_level"] ?? 1)) . "\n" .
            "&r&7Unlock Level: &f" . number_format($unlockLevel) . "\n" .
            "&r&7Status: {$status}"
        ));

        if ($unlocked) {
            $form->addButton(C::colorize("&r&aTeleport\n&r&8Travel to safe zone spawn"));
        }

        $form->addButton(C::colorize("&r&7Back\n&r&8Return to zone list"));
        $player->sendForm($form);
    }
}
