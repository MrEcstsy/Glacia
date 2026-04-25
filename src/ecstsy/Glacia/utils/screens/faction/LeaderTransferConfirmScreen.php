<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\faction;

use ecstsy\Glacia\Loader;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class LeaderTransferConfirmScreen {

    public static function display(Player $player, string $targetName): void
    {
        $targetName = trim($targetName);
        $form = new SimpleForm(function(Player $player, ?int $data) use ($targetName): void {
            if ($data !== 0) {
                return;
            }

            $session = Loader::getPlayerManager()->getSession($player);
            $faction = $session?->getFaction();
            if ($session === null || $faction === null || $session->getFactionRole() !== "leader") {
                $player->sendMessage(C::colorize("&r&cYou must be the faction leader to do that."));
                return;
            }

            $target = Loader::getPlayerManager()->getSessionByPrefix($targetName);
            if ($target === null || $target->getFactionId() !== $faction->getFactionId()) {
                $player->sendMessage(C::colorize("&r&cThat player is no longer in your faction."));
                return;
            }

            if (strtolower($target->getUuid()->toString()) === strtolower($session->getUuid()->toString())) {
                $player->sendMessage(C::colorize("&r&cYou are already the leader."));
                return;
            }

            $faction->transferLeadership($faction->getFactionId(), $target->getUuid());
            $player->sendMessage(C::colorize("&r&l&a(✓) &r&aYou transferred leadership to &2" . $target->getUsername() . "&a."));
            $target->getPlayer()?->sendMessage(C::colorize("&r&l&a(✓) &r&aYou are now the leader of &2" . $faction->getName() . "&a."));
        });

        $form->setTitle(C::colorize("&r&8Confirm Transfer"));
        $form->setContent(C::colorize(
            "&r&7Promoting &f{$targetName}&7 to leader will demote you to &fofficer&7.\n\n&r&cThis cannot be undone with one click."
        ));
        $form->addButton(C::colorize("&r&aConfirm Transfer"));
        $form->addButton(C::colorize("&r&7Cancel"));
        $player->sendForm($form);
    }
}
