<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\items;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ListGlaciaItemsSubCommand extends BaseSubCommand {

    private const ITEMS_PER_PAGE = 10;

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::RED . "This command can only be used in-game.");
            return;
        }

        $defs = GlaciaItemFactory::$definitions;
        $lootboxes = LootboxRepository::getLookupMap();
        if (empty($defs) && empty($lootboxes)) {
            $sender->sendMessage(C::YELLOW . "No Glacia custom items are registered.");
            return;
        }

        $itemKeys = array_keys($defs);
        foreach (array_keys($lootboxes) as $lootboxId) {
            $itemKeys[] = "lootbox:" . $lootboxId;
        }
        sort($itemKeys, SORT_NATURAL | SORT_FLAG_CASE);

        $totalItems = count($itemKeys);
        $totalPages = (int) ceil($totalItems / self::ITEMS_PER_PAGE);
        $page = (int) ($args["page"] ?? 1);

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

        $sender->sendMessage(C::GOLD . "=== Glacia Custom Items (Page {$page}/{$totalPages}) ===");

        foreach ($pageItems as $id) {
            if (str_starts_with($id, "lootbox:")) {
                $lootboxId = substr($id, 8);
                $aliases = $lootboxes[$lootboxId] ?? [];
                $sender->sendMessage(C::LIGHT_PURPLE . $lootboxId . C::GRAY . " - " . C::AQUA . "/gi lootbox 1 {$lootboxId}" . (count($aliases) > 1 ? C::GRAY . " [" . implode(", ", array_slice($aliases, 1)) . "]" : ""));
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
}
