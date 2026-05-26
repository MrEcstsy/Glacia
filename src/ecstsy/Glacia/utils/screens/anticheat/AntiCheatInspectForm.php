<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\anticheat;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class AntiCheatInspectForm {

    public static function open(Player $viewer, Player $target): void {
        $form = new SimpleForm(function (Player $player, ?int $data) use ($viewer, $target): void {
            if ($data === null || $player->getName() !== $viewer->getName()) {
                return;
            }

            match ($data) {
                0 => self::openSection($player, $target, AntiCheatInspectPresenter::SECTION_SUMMARY),
                1 => self::openSection($player, $target, AntiCheatInspectPresenter::SECTION_COMBAT),
                2 => self::openSection($player, $target, AntiCheatInspectPresenter::SECTION_MOVEMENT),
                3 => self::openSection($player, $target, AntiCheatInspectPresenter::SECTION_NETWORK),
                4 => self::openSection($player, $target, AntiCheatInspectPresenter::SECTION_VIOLATIONS),
                5 => self::openSection($player, $target, AntiCheatInspectPresenter::SECTION_METHOD),
                6 => self::openActions($player, $target),
                default => null,
            };
        });

        $form->setTitle(C::colorize("&r&b&lGuard Panel &r&8- &f" . $target->getName()));
        $form->setContent(AntiCheatInspectPresenter::buildHomeBody($target));
        $form->addButton(C::colorize("&r&bSummary"));
        $form->addButton(C::colorize("&r&cCombat"));
        $form->addButton(C::colorize("&r&3Movement"));
        $form->addButton(C::colorize("&r&9Network"));
        $form->addButton(C::colorize("&r&eViolations"));
        $form->addButton(C::colorize("&r&dHow Risk Works"));
        $form->addButton(C::colorize("&r&aStaff Actions"));

        $viewer->sendForm($form);
    }

    public static function openSection(Player $viewer, Player $target, string $section): void {
        $titles = [
            AntiCheatInspectPresenter::SECTION_SUMMARY => "&b&lGuard Summary",
            AntiCheatInspectPresenter::SECTION_COMBAT => "&c&lCombat Read",
            AntiCheatInspectPresenter::SECTION_MOVEMENT => "&3&lMovement Read",
            AntiCheatInspectPresenter::SECTION_NETWORK => "&9&lNetwork Read",
            AntiCheatInspectPresenter::SECTION_VIOLATIONS => "&e&lViolations",
            AntiCheatInspectPresenter::SECTION_METHOD => "&d&lRisk Method",
        ];

        $form = new SimpleForm(function (Player $player, ?int $data) use ($target): void {
            if ($data === null) {
                return;
            }

            match ($data) {
                0 => self::open($player, $target),
                1 => self::openActions($player, $target),
                default => null,
            };
        });

        $form->setTitle(C::colorize(($titles[$section] ?? "&b&lGuard View") . " &r&8- &f" . $target->getName()));
        $form->setContent(AntiCheatInspectPresenter::buildSectionBody($target, $section));
        $form->addButton(C::colorize("&r&cBack"));
        $form->addButton(C::colorize("&r&aStaff Actions"));
        $viewer->sendForm($form);
    }

    public static function openActions(Player $viewer, Player $target): void {
        $session = Loader::getPlayerManager()->getSession($viewer);
        $formDefault = $session !== null && (bool) $session->getSetting("guard_inspect_use_form");

        $form = new SimpleForm(function (Player $player, ?int $data) use ($target): void {
            if ($data === null) {
                return;
            }

            match ($data) {
                0 => self::open($player, $target),
                1 => self::afterRefresh($player, $target),
                2 => self::afterReset($player, $target),
                3 => self::afterAlertsToggle($player, $target),
                4 => AntiCheatInspectMenu::openChest($player, $target),
                5 => self::afterUiToggle($player, $target),
                default => null,
            };
        });

        $form->setTitle(C::colorize("&r&a&lGuard Actions &r&8- &f" . $target->getName()));
        $form->setContent(C::colorize(implode("\n", [
            "&r&fChoose a staff action for this snapshot.",
            "",
            "&r&7Default inspect UI: &f" . ($formDefault ? "Form" : "Chest"),
        ])));
        $form->addButton(C::colorize("&r&cBack"));
        $form->addButton(C::colorize("&r&aRefresh Snapshot"));
        $form->addButton(C::colorize("&r&cWipe Guard Data"));
        $form->addButton(C::colorize("&r&eToggle My Alerts"));
        $form->addButton(C::colorize("&r&bOpen Chest Snapshot"));
        $form->addButton(C::colorize("&r&dToggle Default UI"));

        $viewer->sendForm($form);
    }

    private static function afterRefresh(Player $staff, Player $target): void {
        self::open($staff, $target);
    }

    private static function afterReset(Player $staff, Player $target): void {
        AntiCheatManager::getInstance()->reset($target);
        $staff->sendMessage(C::colorize("&r&aGuard &8> &7Wiped seasonal Guard data for &f" . $target->getName() . "&7."));
        self::openActions($staff, $target);
    }

    private static function afterAlertsToggle(Player $staff, Player $target): void {
        $on = AntiCheatManager::getInstance()->toggleAlerts($staff);
        $staff->sendMessage(C::colorize($on ? "&r&aGuard alerts enabled." : "&r&cGuard alerts disabled."));
        self::openActions($staff, $target);
    }

    private static function afterUiToggle(Player $staff, Player $target): void {
        $session = Loader::getPlayerManager()->getSession($staff);
        if ($session !== null) {
            $session->toggleSetting("guard_inspect_use_form");
            $formMode = (bool) $session->getSetting("guard_inspect_use_form");
            $staff->sendMessage(C::colorize($formMode
                ? "&r&7Guard inspect default set to &fform&7."
                : "&r&7Guard inspect default set to &fchest&7."));
        }
        self::openActions($staff, $target);
    }
}
