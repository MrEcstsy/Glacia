<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\loot;

use ecstsy\Glacia\utils\RewardResolver;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class OpenableLootPreviewScreen {

    private const ENTRIES_PER_PAGE = 9;

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

        $form->setTitle(C::colorize("&r&8Loot Table"));

        $lines = [
            C::colorize("&r&f{$title}"),
            C::colorize("&r&7Page &f" . ($page + 1) . "&7/&f" . ($maxPage + 1)),
            ""
        ];

        foreach ($slice as $entryData) {
            $entry = $entryData["entry"];
            $item = RewardResolver::resolveItem($entry, $player);
            $lines[] = C::colorize("&r&6* &r&f" . ($item?->getCount() ?? 1) . "x &r" . self::getItemDisplayName($item));
        }

        $form->setContent(implode("\n", $lines));

        if ($page > 0) {
            $form->addButton(C::colorize("&r&bPrevious Page"), label: "prev");
        }

        if ($page < $maxPage) {
            $form->addButton(C::colorize("&r&bNext Page"), label: "next");
        }

        $form->addButton(C::colorize("&r&7Close"), label: "close");
        $player->sendForm($form);
    }

    /**
     * @param array<int, array{name: string, entries: array<int, array<string, mixed>>}> $groups
     * @return array<int, array{group: string, entry: array<string, mixed>}>
     */
    private static function flattenEntries(array $groups): array
    {
        $flattened = [];

        foreach ($groups as $group) {
            foreach ($group["entries"] as $entry) {
                $flattened[] = [
                    "group" => (string) $group["name"],
                    "entry" => $entry
                ];
            }
        }

        return $flattened;
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
