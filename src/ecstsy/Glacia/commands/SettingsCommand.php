<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class SettingsCommand extends BaseCommand {

    public function prepare(): void {
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

        $form = new SimpleForm(function (Player $player, $data) use ($session): void {
            if ($data === null) {
                return;
            }

            switch ($data) {
                case 0:
                    $session->toggleSetting("chest_inventories");
                    $this->sendSettingToast($player, "Chest GUI", (bool) $session->getSetting("chest_inventories"));
                    break;
                case 1:
                    $session->toggleSetting("broadcasts");
                    $this->sendSettingToast($player, "Announcer", (bool) $session->getSetting("broadcasts"));
                    break;
                case 2:
                    $session->toggleSetting("notifications");
                    $enabled = (bool) $session->getSetting("notifications");
                    $session->setSetting("loot_announcer", $enabled);
                    $this->sendSettingToast($player, "Notifications", $enabled);
                    break;
                case 3:
                    $session->toggleSetting("quick_claim");
                    $this->sendSettingToast($player, "Quick redeem", (bool) $session->getSetting("quick_claim"));
                    break;
            }
        });

        $form->setTitle(C::colorize("&r&8Server Settings"));
        $form->setContent(C::colorize("&r&fToggle server features to better fit how you play."));
        $form->addButton("Chest Inventories");
        $form->addButton("Announcer");
        $form->addButton("Notifications");
        $form->addButton("Quick Redeem");

        $sender->sendForm($form);
    }

    private function sendSettingToast(Player $player, string $label, bool $enabled): void
    {
        $color = $enabled ? C::GREEN : C::RED;
        $player->sendToastNotification(
            C::colorize(Loader::SERVER_PREFIX),
            C::colorize("&r&7{$label} setting has been set to: " . $color . ($enabled ? "Enabled" : "Disabled"))
        );
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
