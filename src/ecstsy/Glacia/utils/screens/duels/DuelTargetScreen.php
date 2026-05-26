<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\duels;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class DuelTargetScreen
{
    public static function display(Player $player): void
    {
        $targets = [];
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            if ($online->getUniqueId()->equals($player->getUniqueId())) {
                continue;
            }
            $targets[] = $online;
        }

        if ($targets === []) {
            $player->sendMessage(C::colorize("&r&cThere is nobody online to duel right now."));
            return;
        }

        $form = new SimpleForm(function(Player $player, mixed $data) use ($targets): void {
            if (!is_string($data) || !ctype_digit($data) || !isset($targets[(int) $data])) {
                return;
            }

            DuelSetupScreen::display($player, $targets[(int) $data]);
        });

        $form->setTitle(C::colorize("&r&8Choose Duel Target"));
        $form->setContent(C::colorize("&r&7Pick a player to challenge."));

        foreach ($targets as $index => $target) {
            $form->addButton(C::colorize("&r&f" . $target->getName()), -1, "", (string) $index);
        }

        $player->sendForm($form);
    }
}
