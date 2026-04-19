<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\faction;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\Faction;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class FactionUpgradeConfirmScreen
{
    public static function display(Player $player, Faction $faction, string $upgradeKey): void
    {
        $manager = Loader::getFactionUpgradeManager();
        $definition = $manager->getUpgradeDefinition($upgradeKey);
        if ($definition === null || !((bool) ($definition["enabled"] ?? false))) {
            $player->sendMessage(C::colorize("&r&cThat upgrade is not available."));
            FactionUpgradesScreen::display($player, $faction);
            return;
        }

        $level = $manager->getUpgradeLevel($faction->getFactionId(), $upgradeKey);
        $maxLevel = max(1, (int) ($definition["max-level"] ?? 1));
        $cost = $level >= $maxLevel ? 0 : $manager->getUpgradeCost($upgradeKey, $level + 1);
        [$before, $after] = self::buildPreview($faction, $upgradeKey, $definition, $level);

        $form = new SimpleForm(function(Player $player, ?int $data) use ($faction, $upgradeKey, $definition): void {
            if ($data === 0) {
                $session = Loader::getPlayerManager()->getSession($player);
                if (!in_array($session?->getFactionRole(), ["leader", "officer"], true)) {
                    $player->sendMessage(C::colorize("&r&cOnly faction leaders and officers can purchase upgrades."));
                    FactionUpgradesScreen::display($player, $faction);
                    return;
                }

                $result = Loader::getFactionUpgradeManager()->purchaseUpgrade($faction, $upgradeKey);
                if ($result !== "") {
                    $player->sendMessage(C::colorize("&r&c" . $result));
                } else {
                    $player->sendMessage(C::colorize("&r&aUpgraded &f" . (string) ($definition["display-name"] ?? $upgradeKey) . "&a successfully."));
                }
            }

            FactionUpgradesScreen::display($player, $faction);
        });

        $description = is_array($definition["description"] ?? null) ? implode("\n", $definition["description"]) : "";
        $form->setTitle(C::colorize("&r&8Upgrade Confirm"));
        $form->setContent(C::colorize(
            (string) ($definition["display-name"] ?? $upgradeKey) . "\n" .
            "&r&7Level: &f{$level}&7/&f{$maxLevel}\n\n" .
            ($description !== "" ? $description . "\n\n" : "") .
            "&r&7Current: &f{$before}\n" .
            "&r&7After: &a{$after}\n" .
            ($level >= $maxLevel
                ? "\n&r&aThis upgrade is already maxed."
                : "\n&r&7Cost: &f$" . number_format($cost) . "\n&r&7Bank: &a$" . number_format($manager->getBankBalance($faction->getFactionId()), 2))
        ));
        $form->addButton(C::colorize($level >= $maxLevel ? "&r&8Maxed" : "&r&aPurchase Upgrade"));
        $form->addButton(C::colorize("&r&cBack"));
        $player->sendForm($form);
    }

    private static function buildPreview(Faction $faction, string $upgradeKey, array $definition, int $level): array
    {
        $manager = Loader::getFactionUpgradeManager();
        $nextLevel = min($level + 1, (int) ($definition["max-level"] ?? 1));

        return match ($upgradeKey) {
            "aerodynamic" => [
                $level > 0 ? "Enabled" : "Disabled",
                $nextLevel > 0 ? "Enabled" : "Disabled",
            ],
            "megaknight" => [
                number_format($manager->getArmorSetMitigationPercent($faction), 0) . "%",
                number_format(((float) ($definition["reduction-percent-per-level"] ?? 0.0)) * $nextLevel, 0) . "%",
            ],
            "veteran" => [
                number_format(max(1.0, Loader::getCombatManager()->getBaseCombatTime() - $manager->getCombatTagReductionSeconds($faction)), 0) . "s",
                number_format(max(1.0, Loader::getCombatManager()->getBaseCombatTime() - (((int) ($definition["seconds-reduction-per-level"] ?? 0)) * $nextLevel)), 0) . "s",
            ],
            "entrepreneur" => [
                "x" . number_format($manager->getCollectionSellMultiplier($faction), 2),
                "x" . number_format(max(1.0, ((float) ($definition["sell-multiplier-per-level"] ?? 1.0)) ** $nextLevel), 2),
            ],
            "heroic_rush" => [
                number_format($manager->getQuestBonusChance($faction), 0) . "%",
                number_format(((float) ($definition["extra-reward-chance-per-level"] ?? 0.0)) * $nextLevel, 0) . "%",
            ],
            default => [(string) $level, (string) ($level + 1)],
        };
    }
}
