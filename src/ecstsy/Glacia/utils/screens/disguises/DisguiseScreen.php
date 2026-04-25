<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\disguises;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\disguises\DisguiseManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class DisguiseScreen
{
    public static function display(Player $player): void
    {
        $manager = Loader::getDisguiseManager();
        $ids = DisguiseManager::getIds();

        $form = new SimpleForm(function (Player $player, ?int $data) use ($ids, $manager): void {
            if ($data === null) {
                return;
            }

            if ($manager->isDisguised($player)) {
                if ($data === 0) {
                    $manager->clear($player);
                    $player->sendMessage(C::colorize("&r&aYour disguise has faded."));
                    return;
                }

                $data--;
            }

            $id = $ids[$data] ?? null;
            if ($id === null) {
                return;
            }

            if (!$player->hasPermission(DisguiseManager::getPermission($id))) {
                $player->sendMessage(C::colorize("&r&cYou have not unlocked that disguise yet."));
                return;
            }

            $manager->apply($player, $id);
            $player->sendMessage(C::colorize("&r&dDisguise active: &f" . DisguiseManager::getDisplayName($id)));
        });

        $active = $manager->getActiveDisguiseId($player);
        $form->setTitle(C::colorize("&r&8Disguises"));
        $form->setContent(C::colorize(
            $active !== null
                ? "&r&7Current disguise: &f" . DisguiseManager::getDisplayName($active) . "\n&r&8Tap a form below to switch or remove it."
                : "&r&7Choose one of your unlocked disguises."
        ));

        if ($active !== null) {
            $form->addButton(C::colorize("&r&cRemove Disguise"));
        }

        foreach ($ids as $id) {
            $locked = !$player->hasPermission(DisguiseManager::getPermission($id));
            $status = $locked ? "&cLOCKED" : "&aUNLOCKED";
            $suffix = $active === $id ? " &7(active)" : "";
            $form->addButton(C::colorize(
                "&r&f" . DisguiseManager::getDisplayName($id) . "\n&7{$status}{$suffix}"
            ));
        }

        $player->sendForm($form);
    }
}
