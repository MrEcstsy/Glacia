<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\items;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItems;
use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
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
        $lootboxes = LootboxRepository::getLookupMap();
        $etherealCrates = EtherealCrateRepository::getLookupMap();
        $rawCategory = trim((string) ($args["category"] ?? ""));
        $legacyPage = $rawCategory !== "" && ctype_digit($rawCategory) ? (int) $rawCategory : null;
        $category = $legacyPage === null ? strtolower($rawCategory) : "";
        $categories = GlaciaItems::getCategoryDisplays();
        $lootboxListMode = self::isLootboxListCategory($category);

        if (empty($defs) && empty($lootboxes) && empty($etherealCrates)) {
            $sender->sendMessage(C::YELLOW . "No Glacia custom items are registered.");
            return;
        }

        if ($category !== "" && !isset($categories[$category]) && !$lootboxListMode) {
            $sender->sendMessage(C::RED . "Unknown category. Available: " . implode(", ", array_keys($categories)) . ", lootboxes");
            return;
        }

        if ($lootboxListMode) {
            $itemKeys = [];
            foreach (array_keys($lootboxes) as $lootboxId) {
                $itemKeys[] = "lootbox:" . $lootboxId;
            }
        } elseif ($category !== "") {
            $itemKeys = array_values(array_filter(
                GlaciaItems::getCategoryItems($category),
                static fn(string $id): bool => isset($defs[$id])
            ));
        } else {
            $itemKeys = array_keys($defs);
            foreach (array_keys($lootboxes) as $lootboxId) {
                $itemKeys[] = "lootbox:" . $lootboxId;
            }
            foreach (array_keys($etherealCrates) as $crateId) {
                $itemKeys[] = "ethereal_crate:" . $crateId;
            }
        }

        sort($itemKeys, SORT_NATURAL | SORT_FLAG_CASE);

        if ($itemKeys === []) {
            $sender->sendMessage(C::YELLOW . "No items were found for that category.");
            return;
        }

        $totalItems = count($itemKeys);
        $totalPages = (int) ceil($totalItems / self::ITEMS_PER_PAGE);
        $page = (int) ($args["page"] ?? $legacyPage ?? 1);

        if ($page < 1) {
            $sender->sendMessage(C::RED . "Page must be at least 1.");
            return;
        }

        if ($page > $totalPages) {
            $sender->sendMessage(C::RED . "That page does not exist. Max page: " . $totalPages);
            return;
        }

        $offset = (int) (($page - 1) * self::ITEMS_PER_PAGE);
        $pageItems = array_slice($itemKeys, $offset, self::ITEMS_PER_PAGE);

        $title = $lootboxListMode
            ? "Lootboxes"
            : ($category !== "" ? ($categories[$category] . " Items") : "Glacia Custom Items");
        $sender->sendMessage(C::GOLD . "=== {$title} (Page {$page}/{$totalPages}) ===");

        foreach ($pageItems as $id) {
            if (str_starts_with($id, "lootbox:")) {
                $lootboxId = substr($id, 8);
                $aliases = $lootboxes[$lootboxId] ?? [];
                $sender->sendMessage(C::LIGHT_PURPLE . $lootboxId . C::GRAY . " - " . C::AQUA . "/gi lootbox 1 {$lootboxId}" . (count($aliases) > 1 ? C::GRAY . " [" . implode(", ", array_slice($aliases, 1)) . "]" : ""));
                continue;
            }
            if (str_starts_with($id, "ethereal_crate:")) {
                $crateId = substr($id, 15);
                $aliases = $etherealCrates[$crateId] ?? [];
                $sender->sendMessage(C::LIGHT_PURPLE . $crateId . C::GRAY . " - " . C::AQUA . "/gi ethereal_crate 1 {$crateId}" . (count($aliases) > 1 ? C::GRAY . " [" . implode(", ", array_slice($aliases, 1)) . "]" : ""));
                continue;
            }

            $def = $defs[$id];
            $displayName = C::colorize((string) ($def["name"] ?? $id));
            $sender->sendMessage(C::AQUA . $id . C::GRAY . " - " . $displayName);
        }
    }

    public function getPermission(): string {
        return "glacia.give-item";
    }

    /** @internal */
    private static function isLootboxListCategory(string $category): bool {
        return in_array(strtolower(trim($category)), ["lootboxes", "lootbox", "lb"], true);
    }
}
