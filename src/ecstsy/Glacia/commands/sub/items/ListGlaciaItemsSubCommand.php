<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\items;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItems;
use ecstsy\Glacia\utils\screens\items\GiveItemMenuScreen;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class ListGlaciaItemsSubCommand extends BaseSubCommand {

    private const ITEMS_PER_PAGE = 10;

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("category", true));
        $this->registerArgument(1, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $defs = GlaciaItemFactory::$definitions;
        $rawCategory = trim((string) ($args["category"] ?? ""));
        $legacyPage = $rawCategory !== "" && ctype_digit($rawCategory) ? (int) $rawCategory : null;
        $category = $legacyPage === null ? strtolower($rawCategory) : "";
        $categories = GiveItemMenuScreen::getCategoryDisplays();

        if (empty($defs)) {
            $sender->sendMessage(C::colorize("&eNo Glacia custom items are registered."));
            return;
        }

        if ($category !== "" && !isset($categories[$category])) {
            $sender->sendMessage(C::colorize("&cUnknown category. Available: " . implode(", ", array_keys($categories))));
            return;
        }

        if ($category !== "") {
            $itemKeys = array_values(array_filter(
                GiveItemMenuScreen::getCategoryItems($category),
                static fn(string $id): bool => isset($defs[$id])
                    || str_starts_with($id, "lootbox:")
                    || str_starts_with($id, "ethereal_crate:")
            ));
        } else {
            $itemKeys = GiveItemMenuScreen::getAllCatalogueItems();
        }

        usort($itemKeys, static fn(string $a, string $b): int => strnatcasecmp(self::getSortKey($a), self::getSortKey($b)));

        if ($itemKeys === []) {
            $sender->sendMessage(C::colorize("&eNo items were found for that category."));
            return;
        }

        $totalItems = count($itemKeys);
        $totalPages = (int) ceil($totalItems / self::ITEMS_PER_PAGE);
        $page = (int) ($args["page"] ?? $legacyPage ?? 1);

        if ($page < 1) {
            $sender->sendMessage(C::colorize("&cPage must be at least 1."));
            return;
        }

        if ($page > $totalPages) {
            $sender->sendMessage(C::colorize("&cThat page does not exist. Max page: " . $totalPages));
            return;
        }

        $offset = (int) (($page - 1) * self::ITEMS_PER_PAGE);
        $pageItems = array_slice($itemKeys, $offset, self::ITEMS_PER_PAGE);

        $title = $category !== "" ? ($categories[$category] . " Items") : "Glacia Custom Items";
        $sender->sendMessage(C::colorize("&6=== {$title} (Page {$page}/{$totalPages}) ==="));

        foreach ($pageItems as $id) {
            if (str_starts_with($id, "lootbox:")) {
                $lootboxId = substr($id, 8);
                $sender->sendMessage(C::colorize("&d{$lootboxId}&7 - &b/gi lootbox 1 {$lootboxId}"));
                continue;
            }
            if (str_starts_with($id, "ethereal_crate:")) {
                $crateId = substr($id, 15);
                $sender->sendMessage(C::colorize("&d{$crateId}&7 - &b/gi ethereal_crate 1 {$crateId}"));
                continue;
            }

            $def = $defs[$id];
            $displayName = C::colorize((string) ($def["name"] ?? $id));
            $sender->sendMessage(C::colorize("&b{$id}&7 - " . $displayName));
        }
    }

    public function getPermission(): string {
        return "glacia.give-item";
    }

    private static function getSortKey(string $itemId): string
    {
        if (str_starts_with($itemId, "lootbox:")) {
            return substr($itemId, strlen("lootbox:"));
        }

        if (str_starts_with($itemId, "ethereal_crate:")) {
            return substr($itemId, strlen("ethereal_crate:"));
        }

        return $itemId;
    }
}
