<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\zones;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\levels\PlayerLevelManager;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
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
            "&r&7Pick a zone to inspect. Grind where your kills feel fast, safe, and worth selling."
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

            if ($unlocked) {
                if ($data === 0) {
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

                if ($data === 1) {
                    $spawn = ZoneManager::getSpawnPosition($zone);
                    if ($spawn === null) {
                        $player->sendMessage(C::colorize("&r&cThat zone does not have a saved spawn point yet."));
                        return;
                    }

                    if ($spawn->getWorld() !== $player->getWorld()) {
                        $player->sendMessage(C::colorize(
                            "&r&eTrail guidance works once you're inside &f" . (string) ($zone["display"] ?? "that zone") .
                            "&e. Teleport there first, then use the guide."
                        ));
                        return;
                    }

                    if (!CustomParticleEffectManager::startTrailTo($player, $spawn, 14)) {
                        $player->sendMessage(C::colorize("&r&cCouldn't start the trail right now."));
                        return;
                    }

                    $player->sendMessage(C::colorize("&r&bA guiding trail now points toward &f" . (string) ($zone["display"] ?? "that zone") . "&b."));
                    return;
                }
            }

            self::display($player);
        });

        $status = $unlocked ? "&aUnlocked" : "&cLocked";
        $pvp = (bool) ($zone["pvp_enabled"] ?? false) ? "&cEnabled" : "&aDisabled";
        $loop = self::describeZoneLoop((string) ($zone["display"] ?? ""), (string) ($zone["world"] ?? ""));
        $travelTip = self::describeZoneTravel((string) ($zone["display"] ?? ""), (bool) ($zone["full_world"] ?? false));
        $form->setTitle(C::colorize("&r&8" . (string) $zone["display"]));
        $form->setContent(C::colorize(
            (string) ($zone["description"] ?? "&7No details configured.") . "\n\n" .
            "&r&bWhat it's for: &f{$loop}\n" .
            "&r&7Recommended Level: &f" . number_format((int) ($zone["recommended_level"] ?? 1)) . "\n" .
            "&r&7Unlock Level: &f" . number_format($unlockLevel) . "\n" .
            "&r&7PvP: {$pvp}\n" .
            "&r&7Status: {$status}\n" .
            "&r&7Travel Tip: &f{$travelTip}"
        ));

        if ($unlocked) {
            $form->addButton(C::colorize("&r&aTeleport\n&r&8Travel to safe zone spawn"));
            $form->addButton(C::colorize("&r&bGuide Trail\n&r&8Show a particle path"));
        }

        $form->addButton(C::colorize("&r&7Back\n&r&8Return to zone list"));
        $player->sendForm($form);
    }

    private static function describeZoneLoop(string $display, string $worldName): string
    {
        $normalizedDisplay = strtolower(trim(C::clean($display)));
        return match ($normalizedDisplay) {
            "azure plains" => "Early leveling, low-pressure grinding, and learning the sell loop.",
            "irondepths" => "Mid-game grinding with tighter terrain and better value than starter zones.",
            "frostreach" => "Stronger farming with harsher mob pressure before the late-game jump.",
            "infernal rift" => "Late-game PvE where better gear and healing discipline start to matter.",
            "astral veil" => "Endgame grind with PvP risk outside the safe structure. Come prepared, not curious.",
            default => $display !== "" ? "Progression grinding and drop farming in {$display}." : "Progression grinding and drop farming.",
        };
    }

    private static function describeZoneTravel(string $display, bool $fullWorld): string
    {
        if ($fullWorld) {
            return $display !== ""
                ? "This is a full-world zone. Learn the spawn and exit routes before pushing far out."
                : "This is a full-world zone. Learn the spawn and exit routes before pushing far out.";
        }

        return "Use teleport for the safe entry point, then use the guide trail once you're inside that world.";
    }
}
