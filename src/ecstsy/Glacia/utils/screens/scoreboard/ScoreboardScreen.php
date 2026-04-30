<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\scoreboard;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\scoreboard\ScoreboardHelper;
use ecstsy\Glacia\server\scoreboard\ScoreboardProfileManager;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\CustomForm;
use Vecnavium\FormsUI\SimpleForm;

final class ScoreboardScreen
{
    public static function displayMain(Player $player): void
    {
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return;
        }

        $enabled = $session->getSetting("scoreboard");
        $enabled = $enabled === null ? true : (bool) $enabled;
        $selected = ScoreboardProfileManager::getSelectedProfileId($session);
        $label = ScoreboardProfileManager::getProfileDisplayName($selected);

        $form = new SimpleForm(function (Player $player, ?int $data): void {
            match ($data) {
                0 => self::toggleVisibility($player),
                1 => self::displayProfilePicker($player),
                2 => self::displayCustomEditor($player),
                default => null,
            };
        });
        $form->setTitle(C::colorize(MessageTemplates::render("scoreboard.menu.title")));
        $form->setContent(C::colorize(MessageTemplates::render("scoreboard.menu.content")));
        $form->addButton(MessageTemplates::render("scoreboard.menu.button.visibility", [
            "state" => MessageTemplates::render($enabled ? "scoreboard.state.on" : "scoreboard.state.off"),
        ]));
        $form->addButton(MessageTemplates::render("scoreboard.menu.button.profile", ["profile" => $label]));
        $form->addButton(MessageTemplates::render("scoreboard.menu.button.edit"));
        $player->sendForm($form);
    }

    public static function displayProfilePicker(Player $player): void
    {
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return;
        }

        $form = new SimpleForm(function (Player $player, ?int $data): void {
            if ($data === null) {
                return;
            }

            $session = Loader::getPlayerManager()->getSession($player);
            if ($session === null) {
                return;
            }

            $options = array_merge(
                [ScoreboardProfileManager::PROFILE_AUTO],
                array_keys(ScoreboardProfileManager::getProfiles()),
                [ScoreboardProfileManager::PROFILE_CUSTOM]
            );
            $selected = $options[$data] ?? ScoreboardProfileManager::PROFILE_AUTO;
            ScoreboardProfileManager::setSelectedProfileId($session, $selected);
            ScoreboardHelper::refreshScoreboard($player);
            $player->sendMessage(C::colorize(MessageTemplates::render("scoreboard.profile.set", [
                "profile" => ScoreboardProfileManager::getProfileDisplayName($selected),
            ])));
        });
        $form->setTitle(C::colorize(MessageTemplates::render("scoreboard.profile.title")));
        $form->setContent(C::colorize(MessageTemplates::render("scoreboard.profile.content")));
        $form->addButton(MessageTemplates::render("scoreboard.profile.button.auto"));
        foreach (ScoreboardProfileManager::getProfiles() as $id => $profile) {
            $form->addButton((string) ($profile["display"] ?? ucfirst($id)));
        }
        $form->addButton(MessageTemplates::render("scoreboard.profile.button.custom"));
        $player->sendForm($form);
    }

    public static function displayCustomEditor(Player $player): void
    {
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return;
        }

        $custom = ScoreboardProfileManager::getCustomProfile($session);
        $lines = array_values((array) ($custom["lines"] ?? []));

        $form = new CustomForm(function (Player $player, ?array $data): void {
            if ($data === null) {
                return;
            }

            $session = Loader::getPlayerManager()->getSession($player);
            if ($session === null) {
                return;
            }

            $title = (string) ($data[1] ?? "&bCustom Board");
            $lines = [];
            for ($i = 0; $i < 15; $i++) {
                $lines[] = (string) ($data[$i + 2] ?? "");
            }

            ScoreboardProfileManager::saveCustomProfile($session, $title, $lines);
            ScoreboardHelper::refreshScoreboard($player);
            $player->sendMessage(C::colorize(MessageTemplates::render("scoreboard.custom.saved")));
        });
        $form->setTitle(C::colorize(MessageTemplates::render("scoreboard.custom.title")));
        $form->addLabel(C::colorize(MessageTemplates::render("scoreboard.custom.help", [
            "tokens" => implode("\n&r&b", ScoreboardProfileManager::getTokenHints()),
        ])));
        $form->addInput(
            MessageTemplates::render("scoreboard.custom.input.title"),
            MessageTemplates::render("scoreboard.custom.input.title_placeholder"),
            (string) ($custom["title"] ?? "&bCustom Board")
        );
        for ($i = 0; $i < 15; $i++) {
            $form->addInput(
                MessageTemplates::render("scoreboard.custom.input.line", ["number" => $i + 1]),
                MessageTemplates::render("scoreboard.custom.input.line_placeholder"),
                (string) ($lines[$i] ?? "")
            );
        }
        $player->sendForm($form);
    }

    public static function toggleVisibility(Player $player): void
    {
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return;
        }

        $visible = !((bool) ($session->getSetting("scoreboard") ?? true));
        $session->setSetting("scoreboard", $visible);
        ScoreboardHelper::setVisible($player, $visible);
        self::displayMain($player);
    }
}
