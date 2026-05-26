<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\settings;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\guardian\FactionGuardianManager;
use ecstsy\Glacia\server\staff\StaffModeManager;
use ecstsy\Glacia\utils\GameRuleUtils;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use ecstsy\Glacia\utils\screens\scoreboard\ScoreboardScreen;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class SettingsScreen
{
    public static function display(Player $player): void
    {
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return;
        }

        $guardInspectStaff = $player->hasPermission("glacia.anticheat.command");

        $form = new SimpleForm(function (Player $player, $data) use ($session, $guardInspectStaff): void {
            if (!is_int($data)) {
                return;
            }

            switch ($data) {
                case 0:
                    $session->toggleSetting("chest_inventories");
                    self::sendSettingToast($player, "settings.toast.label.chest_inventories", (bool) $session->getSetting("chest_inventories"));
                    break;
                case 1:
                    $session->toggleSetting("broadcasts");
                    self::sendSettingToast($player, "settings.toast.label.broadcasts", (bool) $session->getSetting("broadcasts"));
                    break;
                case 2:
                    $session->toggleSetting("notifications");
                    $enabled = (bool) $session->getSetting("notifications");
                    $session->setSetting("loot_announcer", $enabled);
                    self::sendSettingToast($player, "settings.toast.label.notifications", $enabled);
                    break;
                case 3:
                    $session->toggleSetting("quick_claim");
                    self::sendSettingToast($player, "settings.toast.label.quick_claim", (bool) $session->getSetting("quick_claim"));
                    break;
                case 4:
                    if (StaffModeManager::isInStaffMode($player)) {
                        $player->sendMessage(C::colorize(MessageTemplates::render("settings.coordinates.staff_mode")));
                        break;
                    }

                    $session->toggleSetting("show_coordinates");
                    $enabled = (bool) $session->getSetting("show_coordinates");
                    GameRuleUtils::syncShowCoordinatesFromSession($session, $player);
                    self::sendSettingToast($player, "settings.toast.label.show_coordinates", $enabled);
                    break;
                case 5:
                    ScoreboardScreen::displayMain($player);
                    break;
                case 6:
                    $session->toggleSetting("hide_faction_guardians");
                    FactionGuardianManager::refreshVisibilityFor($player);
                    self::sendSettingToast($player, "settings.toast.label.hide_faction_guardians", (bool) $session->getSetting("hide_faction_guardians"));
                    break;
                case 7:
                    if (!$guardInspectStaff) {
                        break;
                    }
                    $session->toggleSetting("guard_inspect_use_form");
                    self::sendSettingToast($player, "settings.toast.label.guard_inspect_ui", (bool) $session->getSetting("guard_inspect_use_form"));
                    break;
            }
        });

        $form->setTitle(C::colorize(MessageTemplates::render("settings.menu.title")));
        $form->setContent(C::colorize(MessageTemplates::render("settings.menu.content")));
        $form->addButton(MessageTemplates::render("settings.menu.button.chest_inventories"));
        $form->addButton(MessageTemplates::render("settings.menu.button.broadcasts"));
        $form->addButton(MessageTemplates::render("settings.menu.button.notifications"));
        $form->addButton(MessageTemplates::render("settings.menu.button.quick_claim"));
        $form->addButton(MessageTemplates::render("settings.menu.button.show_coordinates"));
        $form->addButton(MessageTemplates::render("settings.menu.button.scoreboard"));
        $form->addButton(MessageTemplates::render("settings.menu.button.hide_faction_guardians"));
        if ($guardInspectStaff) {
            $form->addButton(MessageTemplates::render("settings.menu.button.guard_inspect_ui"));
        }

        $player->sendForm($form);
    }

    private static function sendSettingToast(Player $player, string $labelKey, bool $enabled): void
    {
        $color = $enabled ? "&a" : "&c";
        $player->sendToastNotification(
            C::colorize(Loader::SERVER_PREFIX),
            C::colorize(MessageTemplates::render("settings.toast.state", [
                "label" => MessageTemplates::render($labelKey),
                "color" => $color,
                "state" => MessageTemplates::render($enabled ? "settings.toast.enabled" : "settings.toast.disabled"),
            ]))
        );
    }
}
