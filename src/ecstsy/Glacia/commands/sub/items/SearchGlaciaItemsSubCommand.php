<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\items;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\utils\screens\items\GiveItemMenuScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SearchGlaciaItemsSubCommand extends BaseSubCommand
{
    private const RESULTS_PER_PAGE = 10;

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("query"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $input = trim((string) ($args["query"] ?? ""));
        [$query, $page] = $this->extractQueryAndPage($input);
        if ($query === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /gi search <query> [page]"));
            return;
        }

        $viewer = $sender instanceof Player ? $sender : null;
        $matches = GiveItemMenuScreen::searchItems($query, $viewer);
        if ($matches === []) {
            $sender->sendMessage(C::colorize("&r&cNo Glacia items matched &f{$query}&c."));
            return;
        }

        $totalPages = max(1, (int) ceil(count($matches) / self::RESULTS_PER_PAGE));
        if ($page > $totalPages) {
            $sender->sendMessage(C::colorize("&r&cThat page does not exist. Max page: &f{$totalPages}&c."));
            return;
        }

        $pageItems = array_slice($matches, ($page - 1) * self::RESULTS_PER_PAGE, self::RESULTS_PER_PAGE);
        $sender->sendMessage(C::colorize("&r&6=== GI Search: &f{$query} &7({$page}/{$totalPages}) &6==="));

        foreach ($pageItems as $itemId) {
            $label = GiveItemMenuScreen::getMenuItemLabel($itemId);
            if (str_starts_with($itemId, "lootbox:")) {
                $lookup = substr($itemId, strlen("lootbox:"));
                $sender->sendMessage(C::colorize("&d{$lookup}&7 - &b/gi lootbox 1 {$lookup}&7 - &f{$label}"));
                continue;
            }

            if (str_starts_with($itemId, "ethereal_crate:")) {
                $lookup = substr($itemId, strlen("ethereal_crate:"));
                $sender->sendMessage(C::colorize("&d{$lookup}&7 - &b/gi ethereal_crate 1 {$lookup}&7 - &f{$label}"));
                continue;
            }

            $sender->sendMessage(C::colorize("&b{$itemId}&7 - &f{$label}"));
        }
    }

    public function getPermission(): string
    {
        return "glacia.give-item";
    }

    /**
     * @return array{0:string,1:int}
     */
    private function extractQueryAndPage(string $input): array
    {
        if ($input === "") {
            return ["", 1];
        }

        if (preg_match('/^(.*\S)\s+(\d+)$/', $input, $matches) === 1) {
            $query = trim((string) ($matches[1] ?? ""));
            $page = max(1, (int) ($matches[2] ?? 1));
            return [$query, $page];
        }

        return [$input, 1];
    }
}
