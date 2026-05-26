<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\quests;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class QuestsScreen
{
    public static function display(Player $player): void
    {
        $manager = Loader::getQuestBoardManager();
        $points = $manager->getQuestPoints($player);
        $repLevel = $manager->getReputationLevel($player);
        $repPoints = $manager->getReputationPoints($player);
        $repProgress = $manager->getReputationProgress($player);
        $tierOdds = $manager->getTierOdds($player);
        $rows = $manager->getActiveQuestRows($player);

        $tierOne = self::getSectionRows($rows, "tier_1", false);
        $tierTwo = self::getSectionRows($rows, "tier_2", false);
        $tierThree = self::getSectionRows($rows, "tier_3", false);
        $bonus = self::getSectionRows($rows, "", true);

        $form = new SimpleForm(function(Player $player, ?string $data): void {
            if ($data === null) {
                return;
            }

            if ($data === "shop") {
                QuestShopScreen::display($player);
                return;
            }

            if ($data === "bonus") {
                self::displayQuestList($player, "bonus", true);
                return;
            }

            self::displayQuestList($player, $data, false);
        });

        $form->setTitle(C::colorize("&r&l&bQuests"));
        $content = [
            "&r&7Quest Points: &b" . number_format($points),
            "&r&7Reputation: &fLevel &d{$repLevel} &8(" . number_format($repPoints) . " total&8)",
            "&r&7Progress: " . self::buildReputationBar((float) $repProgress["percent"]),
            $repProgress["max"]
                ? "&r&aYou have reached max reputation."
                : "&r&7Next Level: &f" . number_format((int) $repProgress["remaining"]) . " &7more to &dLevel " . ($repLevel + 1),
            "",
            "&r&7Daily Odds: &aT1 " . (int) ($tierOdds["tier_1"] ?? 0) . "% &8| &eT2 " . (int) ($tierOdds["tier_2"] ?? 0) . "% &8| &cT3 " . (int) ($tierOdds["tier_3"] ?? 0) . "%",
            "&r&7Daily Reset: &b" . GeneralUtils::translateTime($manager->getDailyRefreshRemaining()),
            "&r&7Shop Reset: &6" . GeneralUtils::translateTime($manager->getShopRefreshRemaining()),
        ];
        $form->setContent(C::colorize(implode("\n", $content)));

        $form->addButton(C::colorize("&r&l&aTier 1 Quests\n&r&f" . count($tierOne) . " active"), -1, "", "tier_1");
        $form->addButton(C::colorize("&r&l&eTier 2 Quests\n&r&f" . count($tierTwo) . " active"), -1, "", "tier_2");
        $form->addButton(C::colorize("&r&l&cTier 3 Quests\n&r&f" . count($tierThree) . " active"), -1, "", "tier_3");
        $form->addButton(C::colorize("&r&l&dBonus Quests\n&r&f" . count($bonus) . " active"), -1, "", "bonus");
        $form->addButton(C::colorize("&r&l&6Quest Shop\n&r&fRotating rewards"), -1, "", "shop");
        $player->sendForm($form);
    }

    public static function displayRerollMenu(Player $player): void
    {
        $manager = Loader::getQuestBoardManager();
        $rows = $manager->getActiveQuestRows($player);

        $tierOne = self::getSectionRows($rows, "tier_1", false);
        $tierTwo = self::getSectionRows($rows, "tier_2", false);
        $tierThree = self::getSectionRows($rows, "tier_3", false);
        $bonus = self::getSectionRows($rows, "", true);

        $form = new SimpleForm(function(Player $player, ?string $data): void {
            if ($data === null || $data === "back") {
                return;
            }

            if ($data === "bonus") {
                self::displayQuestList($player, "bonus", true, true);
                return;
            }

            self::displayQuestList($player, $data, false, true);
        });

        $form->setTitle(C::colorize("&r&l&dQuest Reroll"));
        $form->setContent(C::colorize("&r&7Choose a quest section, then click a quest to replace it.\n&r&8Completed quests cannot be rerolled."));
        $form->addButton(C::colorize("&r&l&aTier 1 Quests\n&r&f" . count($tierOne) . " active"), -1, "", "tier_1");
        $form->addButton(C::colorize("&r&l&eTier 2 Quests\n&r&f" . count($tierTwo) . " active"), -1, "", "tier_2");
        $form->addButton(C::colorize("&r&l&cTier 3 Quests\n&r&f" . count($tierThree) . " active"), -1, "", "tier_3");
        $form->addButton(C::colorize("&r&l&dBonus Quests\n&r&f" . count($bonus) . " active"), -1, "", "bonus");
        $form->addButton(C::colorize("&r&l&8Close"), -1, "", "back");
        $player->sendForm($form);
    }

    private static function displayQuestList(Player $player, string $section, bool $bonusOnly, bool $rerollMode = false): void
    {
        $rows = self::getSectionRows(Loader::getQuestBoardManager()->getActiveQuestRows($player), $section, $bonusOnly);

        $form = new SimpleForm(function(Player $player, ?int $data) use ($rows, $section, $bonusOnly, $rerollMode): void {
            if ($data === null) {
                return;
            }

            if ($data === 0) {
                if ($rerollMode) {
                    self::displayRerollMenu($player);
                } else {
                    self::display($player);
                }
                return;
            }

            $row = $rows[$data - 1] ?? null;
            if ($row === null) {
                self::displayQuestList($player, $section, $bonusOnly, $rerollMode);
                return;
            }

            if ($rerollMode) {
                if (!self::consumeQuestRerollItem($player)) {
                    $player->sendMessage(C::colorize("&r&cYou need a Quest Reroll item to do that."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $recordIndex = (int) ($row["record_index"] ?? -1);
                if (!Loader::getQuestBoardManager()->rerollQuest($player, (bool) ($row["bonus"] ?? false), $recordIndex)) {
                    foreach ($player->getInventory()->addItem(GlaciaItemFactory::create("quest_reroll")) as $leftover) {
                        $player->getWorld()->dropItem($player->getPosition(), $leftover);
                    }
                    PlayerUtils::playSound($player, "note.bass");
                    self::displayQuestList($player, $section, $bonusOnly, true);
                    return;
                }

                self::displayRerollMenu($player);
                return;
            }

            self::displayQuestDetail($player, $row, $section);
        });

        $form->setTitle(C::colorize(self::getSectionTitle($section, $bonusOnly)));
        if ($rows === []) {
            $form->setContent(C::colorize("&r&fNo quests are active here right now."));
        } elseif ($rerollMode) {
            $form->setContent(C::colorize("&r&fClick a quest to replace it with a new quest from the same tier."));
        } else {
            $form->setContent(C::colorize("&r&fPick a quest to see its progress and reward info."));
        }

        $form->addButton(C::colorize("&r&l&8Back"));
        foreach ($rows as $row) {
            $status = (bool) ($row["completed"] ?? false) ? "&aComplete" : "&7" . $row["progress"] . "&8/&7" . $row["target"];
            $tierColor = self::getTierColor((string) ($row["tier_key"] ?? ""));
            $form->addButton(C::colorize("{$tierColor}&l" . $row["name"] . "\n&r&f{$status}"));
        }

        $player->sendForm($form);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function displayQuestDetail(Player $player, array $row, string $returnSection): void
    {
        $bonusOnly = $returnSection === "bonus";
        $title = $bonusOnly ? "&r&l&dBonus Quest" : self::getTierColor((string) ($row["tier_key"] ?? "")) . "&l" . $row["tier"];
        $progressBar = self::buildQuestProgressBar((int) $row["progress"], (int) $row["target"]);
        $status = (bool) ($row["completed"] ?? false) ? "&aComplete" : "&eIn Progress";

        $lines = [
            "&r&7Quest: " . self::getTierColor((string) ($row["tier_key"] ?? "")) . $row["name"],
            "&r&7Goal: &f" . $row["description"],
            "",
            "&r&7Progress: {$progressBar}",
            "&r&7Count: &f" . $row["progress"] . "&8/&f" . $row["target"],
            "&r&7Reward: &d" . $row["points"] . " Quest Point" . ((int) $row["points"] === 1 ? "" : "s"),
            "&r&7Status: {$status}",
        ];

        if (($row["source"] ?? "") !== "") {
            $lines[] = "&r&7Source: &d" . ucfirst((string) $row["source"]) . " Sigil";
        }

        $form = new SimpleForm(function(Player $player, ?int $data) use ($returnSection, $bonusOnly): void {
            if ($data === null || $data === 0) {
                self::displayQuestList($player, $returnSection, $bonusOnly);
            }
        });

        $form->setTitle(C::colorize($title));
        $form->setContent(C::colorize(implode("\n", $lines)));
        $form->addButton(C::colorize("&r&l&8Back"));
        $player->sendForm($form);
    }

    private static function buildReputationBar(float $percent): string
    {
        $segments = 10;
        $filled = max(0, min($segments, (int) round($percent * $segments)));
        $parts = ["&8["];
        for ($i = 0; $i < $segments; $i++) {
            $parts[] = $i < $filled ? "&a|" : "&7|";
        }
        $parts[] = "&8]";
        return implode("", $parts);
    }

    private static function buildQuestProgressBar(int $progress, int $target): string
    {
        $segments = 10;
        $target = max(1, $target);
        $filled = max(0, min($segments, (int) round(($progress / $target) * $segments)));
        $parts = ["&8["];
        for ($i = 0; $i < $segments; $i++) {
            $parts[] = $i < $filled ? "&a|" : "&7|";
        }
        $parts[] = "&8]";
        return implode("", $parts);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function getSectionRows(array $rows, string $section, bool $bonusOnly): array
    {
        return array_values(array_filter($rows, static function(array $row) use ($section, $bonusOnly): bool {
            if ($bonusOnly) {
                return (bool) ($row["bonus"] ?? false);
            }

            return !(bool) ($row["bonus"] ?? false) && (string) ($row["tier_key"] ?? "") === $section;
        }));
    }

    private static function getSectionTitle(string $section, bool $bonusOnly): string
    {
        if ($bonusOnly) {
            return "&r&l&dBonus Quests";
        }

        return match ($section) {
            "tier_1" => "&r&l&aTier 1 Quests",
            "tier_2" => "&r&l&eTier 2 Quests",
            "tier_3" => "&r&l&cTier 3 Quests",
            default => "&r&l&bQuests",
        };
    }

    private static function getTierColor(string $tierKey): string
    {
        return match ($tierKey) {
            "tier_1" => "&a",
            "tier_2" => "&e",
            "tier_3" => "&c",
            default => "&b",
        };
    }

    private static function consumeQuestRerollItem(Player $player): bool
    {
        $inventory = $player->getInventory();
        foreach ($inventory->getContents() as $slot => $item) {
            if (GlaciaItemFactory::getGlaciaIdentifier($item) !== "quest_reroll") {
                continue;
            }

            $item->pop();
            $inventory->setItem($slot, $item);
            return true;
        }

        return false;
    }
}
