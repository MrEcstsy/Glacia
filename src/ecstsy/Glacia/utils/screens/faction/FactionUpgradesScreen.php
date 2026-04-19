<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\faction;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\Faction;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class FactionUpgradesScreen
{
    public static function display(Player $player, Faction $faction): void
    {
        $manager = Loader::getFactionUpgradeManager();
        $session = Loader::getPlayerManager()->getSession($player);

        $form = new SimpleForm(function(Player $player, ?int $data) use ($faction, $manager, $session): void {
            if ($data === null) {
                return;
            }

            $keys = array_keys($manager->getEnabledUpgradeDefinitions());
            $upgradeKey = $keys[$data] ?? null;
            if ($upgradeKey === null) {
                return;
            }

            FactionUpgradeConfirmScreen::display($player, $faction, (string) $upgradeKey);
        });

        $form->setTitle(C::colorize("&r&8Faction Upgrades"));
        $form->setContent(C::colorize(
            "&r&fFaction: &b" . $faction->getName() . "\n" .
            "&r&fBank: &a$" . number_format($manager->getBankBalance($faction->getFactionId()), 2) . "\n\n" .
            "&r&7Purchase permanent upgrades for your faction."
        ));

        foreach ($manager->getEnabledUpgradeDefinitions() as $upgradeKey => $definition) {
            $level = $manager->getUpgradeLevel($faction->getFactionId(), (string) $upgradeKey);
            $maxLevel = max(1, (int) ($definition["max-level"] ?? 1));
            $name = (string) ($definition["display-name"] ?? $upgradeKey);

            if ($level >= $maxLevel) {
                $footer = "&r&aMAX LEVEL";
            } else {
                $cost = $manager->getUpgradeCost((string) $upgradeKey, $level + 1);
                $footer = "&r&eNext: &f$" . number_format($cost);
            }

            $form->addButton(C::colorize(
                "{$name}\n&r&8Level: &f{$level}&8/&f{$maxLevel} &7• {$footer}"
            ));
        }

        $player->sendForm($form);
    }
}
