<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class BalTopCommand extends BaseCommand {

    private const PER_PAGE = 10;

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $page = max(1, (int) ($args["page"] ?? 1));

        Loader::getDatabase()->executeSelect(QueryStmts::PLAYERS_BALTOP_COUNT, [], function(array $countRows) use ($sender, $page): void {
            $totalEntries = (int) ($countRows[0]["count"] ?? 0);
            $totalPages = max(1, (int) ceil($totalEntries / self::PER_PAGE));
            $currentPage = min($page, $totalPages);
            $offset = ($currentPage - 1) * self::PER_PAGE;

            Loader::getDatabase()->executeSelect(QueryStmts::PLAYERS_BALTOP, [
                "limit" => self::PER_PAGE,
                "offset" => $offset
            ], function(array $rows) use ($sender, $currentPage, $totalPages, $offset): void {
                $sender->sendMessage(C::colorize("&r&6Top Balances &8(&f{$currentPage}&7/&f{$totalPages}&8)"));

                if ($rows === []) {
                    $sender->sendMessage(C::colorize("&r&7No balance data found."));
                    return;
                }

                foreach ($rows as $index => $row) {
                    $rank = $offset + $index + 1;
                    $name = (string) ($row["username"] ?? "Unknown");
                    $balance = (float) ($row["balance"] ?? 0.0);

                    $sender->sendMessage(C::colorize(
                        "&r&e{$rank}. &f{$name}&7: &a$" . self::formatCompactMoney($balance)
                    ));
                }

                if ($totalPages > 1) {
                    $sender->sendMessage(C::colorize("&r&7Use &f/baltop " . ($currentPage + 1) . " &7for the next page."));
                }
            });
        });
    }

    private static function formatCompactMoney(float $amount): string {
        $abs = abs($amount);

        return match (true) {
            $abs >= 1_000_000_000_000 => number_format($amount / 1_000_000_000_000, 1) . "T",
            $abs >= 1_000_000_000 => number_format($amount / 1_000_000_000, 1) . "B",
            $abs >= 1_000_000 => number_format($amount / 1_000_000, 1) . "M",
            $abs >= 1_000 => number_format($amount / 1_000, 1) . "K",
            default => fmod($amount, 1.0) === 0.0 ? number_format($amount, 0) : number_format($amount, 2),
        };
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
