<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\duels;

use ecstsy\Glacia\server\duels\DuelManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class DuelRequestResponseScreen
{
    public static function display(Player $target, ?string $challengerPrefix = null): void
    {
        $request = DuelManager::getInstance()->findIncomingRequest($target, $challengerPrefix);
        if ($request === null) {
            $target->sendMessage(C::colorize("&r&cThat duel request is no longer available."));
            return;
        }

        $duration = GeneralScreenUtils::formatDurationOption((int) $request["duration"]);
        $loadout = (string) (($request["loadout"]["label"] ?? "Unknown"));
        $wagerText = DuelManager::getInstance()->formatWagerSummary((array) ($request["wager"] ?? []));
        $content = "&r&f" . $request["challenger_name"] . " &7challenged you.\n"
            . "&r&7Mode: &f" . $loadout . "\n"
            . "&r&7Duration: &f" . $duration;
        if (((string) (($request["wager"]["type"] ?? "none"))) !== "none") {
            $content .= "\n&r&7Wager: " . $wagerText;
        }

        $form = new SimpleForm(function(Player $player, ?int $data) use ($request): void {
            if ($data === null) {
                return;
            }

            if ($data === 0) {
                DuelManager::getInstance()->acceptRequest($player, (string) $request["challenger_name"]);
                return;
            }

            DuelManager::getInstance()->denyRequest($player, (string) $request["challenger_name"]);
        });

        $form->setTitle(C::colorize("&r&8Duel Request"));
        $form->setContent(C::colorize($content));
        $form->addButton(C::colorize("&r&aAccept"));
        $form->addButton(C::colorize("&r&cDeny"));
        $target->sendForm($form);
    }
}
