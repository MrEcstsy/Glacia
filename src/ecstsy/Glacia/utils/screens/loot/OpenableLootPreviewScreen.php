<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\loot;

use ecstsy\Glacia\utils\RewardResolver;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class OpenableLootPreviewScreen {

    private const ENTRIES_PER_PAGE = 10;

    /**
     * @param array<int, array{name: string, entries: array<int, array<string, mixed>>}> $groups
     */
    public static function display(Player $player, string $title, array $groups, int $page = 0): void
    {
        $entries = self::flattenEntries($groups);
        if ($entries === []) {
            $player->sendMessage(C::colorize("&r&cNo loot table entries are configured for this item."));
            return;
        }

        $maxPage = max(0, (int) ceil(count($entries) / self::ENTRIES_PER_PAGE) - 1);
        $page = max(0, min($maxPage, $page));
        $slice = array_slice($entries, $page * self::ENTRIES_PER_PAGE, self::ENTRIES_PER_PAGE);

        $form = new SimpleForm(function(Player $player, mixed $data) use ($title, $groups, $page, $maxPage): void {
            if ($data === "prev") {
                self::display($player, $title, $groups, $page - 1);
                return;
            }

            if ($data === "next") {
                self::display($player, $title, $groups, $page + 1);
            }
        });

        $form->setTitle(C::colorize("&r&8Loot Preview"));

        $lines = [
            C::colorize("&r&f&l{$title}"),
            C::colorize("&r&7Page &f" . ($page + 1) . "&7/&f" . ($maxPage + 1)),
        ];

        $lastGroup = null;
        foreach ($slice as $entryData) {
            $entry = $entryData["entry"];
            $group = (string) $entryData["group"];
            $groupWeight = (int) $entryData["group_weight"];

            if ($group !== $lastGroup) {
                $lines[] = "";
                $lines[] = C::colorize("&r&l&n" . self::formatGroupName($group));
                $lastGroup = $group;
            }

            $item = RewardResolver::resolveItem($entry, $player);
            $count = $item?->getCount() ?? 1;
            $chanceText = self::formatChancePercent((int) ($entry["chance"] ?? 0), $groupWeight);

            $lines[] = C::colorize(
                "&r&6* " . $chanceText . " &r&f" . $count . "x &r" . self::getItemDisplayName($item)
            );
        }

        $form->setContent(implode("\n", $lines));

        if ($page > 0) {
            $form->addButton(C::colorize("&r&8Previous Page"), label: "prev");
        }

        if ($page < $maxPage) {
            $form->addButton(C::colorize("&r&8Next Page"), label: "next");
        }

        $form->addButton(C::colorize("&r&8Close"), label: "close");
        $player->sendForm($form);
    }

    /**
     * @param array<int, array{name: string, entries: array<int, array<string, mixed>>}> $groups
     * @return array<int, array{group: string, entry: array<string, mixed>, group_weight: int}>
     */
    private static function flattenEntries(array $groups): array
    {
        $flattened = [];

        foreach ($groups as $group) {
            $entries = (array) ($group["entries"] ?? []);
            $groupWeight = 0;
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $groupWeight += max(0, (int) ($entry["chance"] ?? 0));
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $flattened[] = [
                    "group" => (string) ($group["name"] ?? ""),
                    "entry" => $entry,
                    "group_weight" => $groupWeight,
                ];
            }
        }

        return $flattened;
    }

    private static function formatGroupName(string $group): string
    {
        $clean = trim($group);
        if ($clean === "") {
            return "Loot";
        }

        return ucwords(strtolower($clean));
    }

    private static function formatChancePercent(int $weight, int $groupWeight): string
    {
        if ($weight <= 0 || $groupWeight <= 0) {
            return C::colorize("&r&8(?%)");
        }

        $percent = ($weight / $groupWeight) * 100.0;
        if ($percent >= 10.0) {
            $formatted = number_format($percent, 0);
        } elseif ($percent >= 1.0) {
            $formatted = number_format($percent, 1);
        } else {
            $formatted = number_format($percent, 2);
        }

        return C::colorize("&r&7(&e" . $formatted . "%&7)");
    }

    private static function getItemDisplayName(?Item $item): string
    {
        if ($item === null) {
            return C::colorize("&cInvalid Reward");
        }

        $customName = $item->getCustomName();
        return $customName !== "" ? $customName : $item->getName();
    }
}
