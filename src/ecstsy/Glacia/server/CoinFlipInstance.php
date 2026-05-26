<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\tasks\CoinFlipTask;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\PermissionTierUtils;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\player\Player;
use Vecnavium\FormsUI\SimpleForm;

final class CoinFlipInstance {

    public static array $coinFlips = [];
    public const COLOR_BOX = "■";

    public const COLORS = [
        "Red" => "&c",
        "Yellow" => "&e",
        "Green" => "&a",
        "Blue" => "&9",
        "Purple" => "&5",
        "Orange" => "&6",
        "Pink" => "&d",
        "Cyan" => "&b"
    ];

    public function __construct(Loader $plugin)
    {
        $this->loadCoinFlips();
    }

    private function loadCoinFlips(): void
    {
        Loader::getDatabase()->executeSelect(QueryStmts::COINFLIP_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                self::$coinFlips[$row["uuid"]] = [
                    "uuid" => $row["uuid"],
                    "username" => $row["username"],
                    "type" => $row["type"],
                    "wager" => $row["money"]
                ];
            }
        });
    }

    public static function getCoinFlipColorOption(Player $player, int $amount): SimpleForm
    {
        $form = new SimpleForm(function (Player $player, $data) use ($amount): void {
            if ($data === null) {
                return;
            }

            $colors = array_keys(self::COLORS);
            $color = $colors[$data];
            $session = Loader::getPlayerManager()->getSession($player);

            if ($session === null || $session->getBalance() < $amount) {
                $player->sendMessage(GlaciaUtils::format("coinflip.error.not_enough_balance"));
                return;
            }

            $session->removeBalance($amount);
            self::addCoinFlip($player, $color, $amount);
            $player->sendMessage(GlaciaUtils::format("coinflip.created"));
        });

        $form->setTitle(GlaciaUtils::format("coinflip.menu.color.title"));
        foreach (self::COLORS as $colorName => $colorCode) {
            $form->addButton(GlaciaUtils::format("coinflip.menu.color.button", [
                "color" => $colorCode,
                "name" => $colorName,
            ]));
        }

        return $form;
    }

    public static function getCoinFlipList(Player $player): SimpleForm
    {
        $entries = array_values(array_filter(
            self::$coinFlips,
            static fn(array $cf): bool => ($cf["uuid"] ?? "") !== $player->getUniqueId()->toString()
        ));

        $form = new SimpleForm(function (Player $player, $data) use ($entries): void {
            if ($data === null || !isset($entries[$data])) {
                return;
            }

            self::getCoinFlipOpponentColorForm($player, (string) $entries[$data]["uuid"], $entries[$data]);
        });

        $form->setTitle(GlaciaUtils::format("coinflip.menu.list.title"));
        if ($entries === []) {
            $form->setContent(GlaciaUtils::format("coinflip.menu.list.empty"));
            return $form;
        }

        foreach ($entries as $cf) {
            $form->addButton(GlaciaUtils::format("coinflip.menu.list.entry", [
                "player" => (string) $cf["username"],
                "amount" => number_format((int) $cf["wager"]),
            ]));
        }

        return $form;
    }

    public static function getCoinFlipOpponentColorForm(Player $player, string $uuid, array $cf): void
    {
        $colors = array_values(array_filter(
            array_keys(self::COLORS),
            static fn(string $color): bool => $color !== ($cf["type"] ?? "")
        ));

        $form = new SimpleForm(function (Player $player, $data) use ($uuid, $cf, $colors): void {
            if ($data === null || !isset($colors[$data])) {
                return;
            }

            $session = Loader::getPlayerManager()->getSession($player);
            if ($session === null || $session->getBalance() < (int) $cf["wager"]) {
                $player->sendMessage(GlaciaUtils::format("coinflip.error.not_enough_balance"));
                return;
            }

            $session->removeBalance((int) $cf["wager"]);
            if (!self::startCoinFlip((string) $cf["username"], $player->getName(), (string) $cf["type"], $colors[$data], (int) $cf["wager"])) {
                $session->addBalance((int) $cf["wager"]);
                $player->sendMessage(GlaciaUtils::format("coinflip.error.player_unavailable"));
            }
        });

        $form->setTitle(GlaciaUtils::format("coinflip.menu.color.title"));
        foreach ($colors as $color) {
            $form->addButton(GlaciaUtils::format("coinflip.menu.color.button", [
                "color" => self::COLORS[$color] ?? "&f",
                "name" => $color,
            ]));
        }

        $player->sendForm($form);
    }

    public static function startCoinFlip(string $p1Name, string $p2Name, string $p1Color, string $p2Color, int $amount): bool
    {
        $p1 = PlayerUtils::getPlayerByPrefix($p1Name);
        $p2 = PlayerUtils::getPlayerByPrefix($p2Name);
        if ($p1 === null || $p2 === null) {
            return false;
        }

        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new CoinFlipTask($p1, $p2, $p1Color, $p2Color, $amount), 1);
        $p1->sendMessage(GlaciaUtils::format("coinflip.started.self", ["player" => $p2->getName()]));
        $p2->sendMessage(GlaciaUtils::format("coinflip.started.self", ["player" => $p1->getName()]));
        return true;
    }

    public static function addCoinFlip(Player $player, string $type, int $wager): void
    {
        $uuid = $player->getUniqueId()->toString();
        $username = $player->getName();

        $args = [
            "uuid" => $uuid,
            "username" => $username,
            "type" => $type,
            "money" => $wager,
        ];

        Loader::getDatabase()->executeInsert(QueryStmts::COINFLIP_CREATE, $args);
        self::$coinFlips[$uuid] = [
            "uuid" => $uuid,
            "username" => $username,
            "type" => $type,
            "wager" => $wager
        ];
    }

    public static function removeCoinFlip(string $uuid): void
    {
        Loader::getDatabase()->executeChange(QueryStmts::COINFLIP_DELETE, ["uuid" => $uuid]);
        unset(self::$coinFlips[$uuid]);
    }

    public function resetSeasonState(): void
    {
        self::$coinFlips = [];
    }

    public static function getRollTaskTickInterval(): int
    {
        return 10;
    }

    public function getCoinFlipData(string $uuid): ?array
    {
        return self::$coinFlips[$uuid] ?? null;
    }

    public static function hasSubmittedCoinFlip(Player $player): bool
    {
        return isset(self::$coinFlips[$player->getUniqueId()->toString()]);
    }

    public static function getBaseTaxPercent(): float
    {
        $config = Loader::getInstance()->getConfig();
        return max(0.0, (float) ($config->getNested("tax.coinflip", 5.0) ?? 5.0));
    }

    public static function getTaxPercentFor(Player $player): float
    {
        $basePercent = self::getBaseTaxPercent();
        $reduction = PermissionTierUtils::getHighestIntPermission($player, "glacia.cf.tax_reduction.", 0, 100);
        return max(0.0, $basePercent * (1 - ($reduction / 100)));
    }

    public static function getColorCode(string $colorName): string
    {
        return self::COLORS[$colorName] ?? "&f";
    }

    public static function getColorNameFromCode(string $colorCode): string
    {
        $name = array_search($colorCode, self::COLORS, true);
        return is_string($name) ? $name : "Unknown";
    }

    public static function getColoredBox(string $colorCode): string
    {
        return $colorCode . self::COLOR_BOX;
    }

    public static function getStats(Player $player): array
    {
        $session = Loader::getPlayerManager()->getSession($player);
        return self::normalizeStats($session?->getSetting("coinflip_stats"));
    }

    public static function showStats(Player $viewer, ?Player $target = null): void
    {
        $target ??= $viewer;

        $viewerSession = Loader::getPlayerManager()->getSession($viewer);
        $targetSession = Loader::getPlayerManager()->getSession($target);
        if (!$targetSession instanceof GlaciaPlayer) {
            $viewer->sendMessage(GlaciaUtils::format("player.error.no_session"));
            return;
        }

        $stats = self::normalizeStats($targetSession->getSetting("coinflip_stats"));
        $wins = (int) ($stats["wins"] ?? 0);
        $losses = (int) ($stats["losses"] ?? 0);
        $amountWon = (int) ($stats["amount_won"] ?? 0);
        $amountLost = (int) ($stats["amount_lost"] ?? 0);
        $total = $wins + $losses;
        $winRate = $total > 0 ? round(($wins / $total) * 100, 1) : 0.0;
        $net = $amountWon - $amountLost;

        $viewer->sendMessage(GlaciaUtils::format(
            $target === $viewer ? "coinflip.stats.header.self" : "coinflip.stats.header.other",
            ["player" => $target->getName()]
        ));
        $viewer->sendMessage(GlaciaUtils::format("coinflip.stats.summary", [
            "wins" => number_format($wins),
            "losses" => number_format($losses),
            "win_rate" => number_format($winRate, 1),
            "amount_won" => number_format($amountWon),
            "amount_lost" => number_format($amountLost),
            "net_amount" => number_format(abs($net)),
            "net_color" => $net >= 0 ? "&a" : "&c",
        ]));

        if ($target !== $viewer && $viewerSession instanceof GlaciaPlayer) {
            $headToHead = self::getHeadToHead(self::normalizeStats($viewerSession->getSetting("coinflip_stats")), $target->getUniqueId()->toString());
            if (($headToHead["wins"] + $headToHead["losses"]) > 0) {
                $viewer->sendMessage(GlaciaUtils::format("coinflip.stats.vs", [
                    "player" => $viewer->getName(),
                    "opponent" => $target->getName(),
                    "player_wins" => $headToHead["wins"],
                    "opponent_wins" => $headToHead["losses"],
                ]));
            } else {
                $viewer->sendMessage(GlaciaUtils::format("coinflip.stats.vs.none", [
                    "opponent" => $target->getName(),
                ]));
            }
        }
    }

    public static function recordResult(Player $winner, Player $loser, int $winnerNetPrize, int $loserAmountLost): array
    {
        $winnerSession = Loader::getPlayerManager()->getSession($winner);
        $loserSession = Loader::getPlayerManager()->getSession($loser);
        if (!$winnerSession instanceof GlaciaPlayer || !$loserSession instanceof GlaciaPlayer) {
            return [
                "winner_vs_loser" => ["wins" => 0, "losses" => 0],
                "loser_vs_winner" => ["wins" => 0, "losses" => 0],
            ];
        }

        $winnerUuid = $winner->getUniqueId()->toString();
        $loserUuid = $loser->getUniqueId()->toString();

        $winnerStats = self::normalizeStats($winnerSession->getSetting("coinflip_stats"));
        $loserStats = self::normalizeStats($loserSession->getSetting("coinflip_stats"));

        $winnerStats["wins"]++;
        $winnerStats["amount_won"] += max(0, $winnerNetPrize);
        $winnerStats["head_to_head"][$loserUuid] = self::updateHeadToHeadEntry(
            $winnerStats["head_to_head"][$loserUuid] ?? null,
            $loser->getName(),
            true
        );

        $loserStats["losses"]++;
        $loserStats["amount_lost"] += max(0, $loserAmountLost);
        $loserStats["head_to_head"][$winnerUuid] = self::updateHeadToHeadEntry(
            $loserStats["head_to_head"][$winnerUuid] ?? null,
            $winner->getName(),
            false
        );

        $winnerSession->setSetting("coinflip_stats", $winnerStats);
        $loserSession->setSetting("coinflip_stats", $loserStats);
        Loader::getQuestBoardManager()->recordProgress($winner, "coinflip_wins", 1, [
            "wager" => max(0, $loserAmountLost),
            "prize" => max(0, $winnerNetPrize),
        ]);

        return [
            "winner_vs_loser" => self::getHeadToHead($winnerStats, $loserUuid),
            "loser_vs_winner" => self::getHeadToHead($loserStats, $winnerUuid),
        ];
    }

    private static function normalizeStats(mixed $raw): array
    {
        $stats = is_array($raw) ? $raw : [];
        $headToHead = [];
        foreach (($stats["head_to_head"] ?? []) as $uuid => $entry) {
            if (!is_string($uuid) || !is_array($entry)) {
                continue;
            }
            $headToHead[$uuid] = [
                "name" => (string) ($entry["name"] ?? ""),
                "wins" => max(0, (int) ($entry["wins"] ?? 0)),
                "losses" => max(0, (int) ($entry["losses"] ?? 0)),
            ];
        }

        return [
            "wins" => max(0, (int) ($stats["wins"] ?? 0)),
            "losses" => max(0, (int) ($stats["losses"] ?? 0)),
            "amount_won" => max(0, (int) ($stats["amount_won"] ?? 0)),
            "amount_lost" => max(0, (int) ($stats["amount_lost"] ?? 0)),
            "head_to_head" => $headToHead,
        ];
    }

    private static function updateHeadToHeadEntry(mixed $entry, string $name, bool $won): array
    {
        $normalized = is_array($entry) ? $entry : [];
        $normalized["name"] = $name;
        $normalized["wins"] = max(0, (int) ($normalized["wins"] ?? 0));
        $normalized["losses"] = max(0, (int) ($normalized["losses"] ?? 0));
        if ($won) {
            $normalized["wins"]++;
        } else {
            $normalized["losses"]++;
        }
        return $normalized;
    }

    private static function getHeadToHead(array $stats, string $opponentUuid): array
    {
        $entry = $stats["head_to_head"][$opponentUuid] ?? [];
        return [
            "wins" => max(0, (int) ($entry["wins"] ?? 0)),
            "losses" => max(0, (int) ($entry["losses"] ?? 0)),
        ];
    }
}
