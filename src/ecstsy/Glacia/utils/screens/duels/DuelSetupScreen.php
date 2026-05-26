<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\duels;

use ecstsy\Glacia\server\duels\DuelManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\CustomForm;

final class DuelSetupScreen
{
    public static function display(Player $player, Player $target): void
    {
        $manager = DuelManager::getInstance();
        $durations = $manager->getDurationOptions();
        $durationLabels = array_map(static fn(int $seconds): string => GeneralScreenUtils::formatDurationOption($seconds), $durations);
        $gkits = $manager->getSelectableGKits();
        $presets = $manager->getPresetSets();

        $gkitLabels = $gkits === []
            ? ["No GKits configured"]
            : array_map(static fn(array $entry): string => C::colorize((string) $entry["display"]) . " (Lv " . $entry["level"] . ")", $gkits);
        $presetLabels = $presets === []
            ? ["No preset sets configured"]
            : array_map(static fn(array $entry): string => C::colorize((string) $entry["display"]), $presets);

        $form = new CustomForm(function(Player $player, ?array $data) use ($target, $durations, $gkits, $presets): void {
            if ($data === null) {
                return;
            }

            $liveTarget = PlayerUtils::getPlayerByPrefix($target->getName());
            if (!$liveTarget instanceof Player) {
                $player->sendMessage(C::colorize("&r&cThat player is no longer online."));
                return;
            }

            $duration = $durations[(int) ($data[1] ?? 0)] ?? 180;
            $wagerTypeIndex = (int) ($data[2] ?? 0);
            $wagerInput = trim((string) ($data[3] ?? ""));
            $wagerType = match ($wagerTypeIndex) {
                1 => "money",
                2 => "xp",
                3 => "inventory",
                default => "none",
            };
            $wagerAmount = $wagerType === "money"
                ? max(0, (int) PlayerUtils::parseShorthandAmount($wagerInput === "" ? "0" : $wagerInput))
                : ($wagerType === "xp" ? max(0, GlaciaUtils::parseShorthandNumber($wagerInput === "" ? "0" : $wagerInput)) : 0);
            $modeIndex = (int) ($data[4] ?? 0);
            $mode = match ($modeIndex) {
                1 => "gkit",
                2 => "preset",
                default => "current",
            };

            $loadout = ["mode" => $mode];
            if ($mode === "gkit" && $gkits !== []) {
                $loadout["gkit"] = $gkits[(int) ($data[5] ?? 0)]["id"] ?? $gkits[0]["id"];
            }
            if ($mode === "preset" && $presets !== []) {
                $loadout["preset"] = $presets[(int) ($data[6] ?? 0)]["id"] ?? $presets[0]["id"];
            }

            $wager = ["type" => $wagerType, "amount" => $wagerAmount];
            if (!DuelManager::getInstance()->sendRequest($player, $liveTarget, $duration, $wager, $loadout)) {
                return;
            }
        });

        $form->setTitle(C::colorize("&r&8Duel Setup"));
        $form->addLabel(C::colorize("&r&7Challenging: &f" . $target->getName()));
        $form->addDropdown("Duration", $durationLabels, 2);
        $form->addDropdown("Wager Type", ["None", "Money", "XP", "Full Inventory"], 0);
        $form->addInput("Wager Amount", "Money: 100k | XP: 5000", "0");
        $form->addDropdown("Loadout", ["Current Inventory", "Allowed GKit", "Preset Set"], 0);
        $form->addDropdown("GKit", $gkitLabels, 0);
        $form->addDropdown("Preset Set", $presetLabels, 0);
        $player->sendForm($form);
    }
}

final class GeneralScreenUtils
{
    public static function formatDurationOption(int $seconds): string
    {
        if ($seconds % 60 === 0) {
            $minutes = (int) floor($seconds / 60);
            return $minutes . " minute" . ($minutes === 1 ? "" : "s");
        }

        return $seconds . " seconds";
    }
}
