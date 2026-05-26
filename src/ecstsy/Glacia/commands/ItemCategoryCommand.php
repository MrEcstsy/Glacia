<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItems;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ItemCategoryCommand extends BaseCommand {

    private const ITEMS_PER_PAGE = 8;

    public function __construct(
        Loader $plugin,
        string $name,
        string $description,
        private readonly string $category,
        array $aliases = []
    ) {
        parent::__construct($plugin, $name, $description, $aliases);
    }

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&cThis command can only be used in-game."));
            return;
        }

        $display = GlaciaItems::getCategoryDisplay($this->category) ?? ucfirst($this->category);
        $itemIds = GlaciaItems::getCategoryItems($this->category);

        if ($itemIds === []) {
            $sender->sendMessage(C::colorize("&cNo items are registered in that category."));
            return;
        }

        $page = max(1, (int) ($args["page"] ?? 1));
        $totalPages = (int) max(1, (int) ceil(count($itemIds) / self::ITEMS_PER_PAGE));

        if ($page > $totalPages) {
            $sender->sendMessage(C::colorize("&cThat page does not exist. Max page: " . $totalPages));
            return;
        }

        $pageItems = array_slice($itemIds, ($page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE);

        $sender->sendMessage(C::colorize("&r&6=== {$display} &7(Page {$page}/{$totalPages}) &6==="));
        foreach ($pageItems as $id) {
            $definition = GlaciaItemFactory::$definitions[$id] ?? null;
            $displayName = $definition !== null ? C::colorize((string) ($definition["name"] ?? $id)) : $id;
            $sender->sendMessage(C::colorize("&b" . $id . "&7 - " . $displayName));
        }

        if ($sender->hasPermission("glacia.give-item")) {
            $sender->sendMessage(C::colorize("&r&7Tip: &f/gi list {$this->category} [page] &7or &f/gi <item>"));
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
