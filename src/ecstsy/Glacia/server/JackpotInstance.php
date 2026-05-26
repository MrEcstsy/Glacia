<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use SQLite3;
use Throwable;

final class JackpotInstance {

    private int $pot = 0;
    private int $ticketPrice;
    private float $taxPercent;
    private int $interval;
    private int $lastDrawTime = 0;
    private int $timeLeft;
    private const MAX_TICKETS_PER_PLAYER = 1000;

    /** @var array<string, int> */
    private array $tickets = [];

    public function __construct()
    {
        $this->reloadSettingsFromConfig();
        $this->loadState();
        $this->scheduleDrawTask();
    }

    public function reloadSettingsFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml")?->getAll() ?? [];
        $this->ticketPrice = max(1, (int) ($config["jackpot"]["ticket-price"] ?? 10000));
        $this->taxPercent = max(0.0, (float) ($config["tax"]["jackpot"] ?? 0.10));
        $this->interval = max(60, (int) ($config["jackpot"]["interval"] ?? 7200));

        if (!isset($this->timeLeft) || $this->timeLeft <= 0) {
            $this->timeLeft = $this->interval;
            return;
        }

        $this->timeLeft = min($this->timeLeft, $this->interval);
    }

    public function purchaseTickets(Player $player, int $amount, bool $allowOverflow = false): bool
    {
        if ($amount <= 0) {
            $player->sendMessage(C::colorize("&r&cYou must buy at least one jackpot ticket."));
            return false;
        }

        $uuid = $player->getUniqueId()->toString();
        $currentTickets = $this->tickets[$uuid] ?? 0;

        if (!$allowOverflow && $currentTickets >= self::MAX_TICKETS_PER_PLAYER) {
            $player->sendMessage(C::colorize("&r&cYou have already purchased the maximum number of jackpot tickets this round."));
            return false;
        }

        if (!$allowOverflow && ($currentTickets + $amount) > self::MAX_TICKETS_PER_PLAYER) {
            $remaining = self::MAX_TICKETS_PER_PLAYER - $currentTickets;
            $player->sendMessage(C::colorize("&r&cYou can only buy &f" . number_format($remaining) . " &cmore ticket(s) this round."));
            return false;
        }

        $cost = $amount * $this->ticketPrice;
        $session = Loader::getInstance()->getPlayerManager()->getSession($player);
        if ($session === null) {
            $player->sendMessage(C::colorize("&r&cCould not load your profile."));
            return false;
        }

        if ($session->getBalance() < $cost) {
            $player->sendMessage(C::colorize("&r&cYou need &f$" . number_format($cost) . " &cto buy &f{$amount} &cticket(s)."));
            return false;
        }

        $session->removeBalance($cost);
        $this->pot += $cost;
        $this->tickets[$uuid] = $currentTickets + $amount;
        $player->sendMessage(C::colorize("&r&aYou bought &f{$amount} &ajackpot ticket(s)."));
        Loader::getQuestBoardManager()->recordProgress($player, "jackpot_tickets_bought", $amount);
        $this->saveState();
        return true;
    }

    public function drawWinner(): void
    {
        if ($this->tickets === []) {
            $this->broadcast([
                "&r&6&lJACKPOT &r&8| &r&7The draw has ended with &fno entries&7 this round.",
                "&r&8> &7A new jackpot round has started. Use &f/jp buy <amount> &7to enter.",
            ]);
            $this->resetJackpot();
            return;
        }

        $winnerUuid = $this->selectWinner();
        if ($winnerUuid === null) {
            $this->resetJackpot();
            return;
        }

        $tax = (int) round($this->pot * $this->taxPercent);
        $prize = max(0, $this->pot - $tax);
        $winnerName = $this->awardPrize($winnerUuid, $prize);

        $this->broadcast([
            "&r&6&lJACKPOT &r&8| &r&f{$winnerName} &7won the jackpot draw.",
            "&r&8> &7Prize: &a$" . number_format($prize) . " &8| &7Tax: &c$" . number_format($tax),
            "&r&8> &7Thanks to everyone who entered. A fresh round starts now.",
        ]);

        $this->resetJackpot();
    }

    private function selectWinner(): ?string
    {
        $totalTickets = array_sum($this->tickets);
        if ($totalTickets <= 0) {
            return null;
        }

        $roll = mt_rand(1, $totalTickets);
        $current = 0;
        foreach ($this->tickets as $uuid => $count) {
            $current += max(0, $count);
            if ($roll <= $current) {
                return $uuid;
            }
        }

        return null;
    }

    public function startJackpotRoundIfTimeElapsed(): void
    {
        $now = time();
        if ($now - $this->lastDrawTime >= $this->interval) {
            $this->drawWinner();
            $this->lastDrawTime = $now;
            $this->saveState();
        }
    }

    private function resetJackpot(): void
    {
        $this->pot = 0;
        $this->tickets = [];
        $this->lastDrawTime = time();
        $this->timeLeft = $this->interval;
        $this->saveState();
    }

    public function getPot(): int
    {
        return $this->pot;
    }

    public function getTicketPrice(): int
    {
        return $this->ticketPrice;
    }

    public function getTickets(): array
    {
        return $this->tickets;
    }

    private function scheduleDrawTask(): void
    {
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            $this->timeLeft--;

            switch ($this->timeLeft) {
                case 30:
                case 10:
                    $this->broadcast([
                        "&r&6&lJACKPOT &r&8| &r&7Drawing in &f{$this->timeLeft}s&7. Current pot: &a$" . number_format($this->pot),
                    ]);
                    break;

                case 3:
                case 2:
                case 1:
                    $this->broadcast([
                        "&r&6&lJACKPOT &r&8| &r&7Drawing in &f{$this->timeLeft} " . ($this->timeLeft === 1 ? "second" : "seconds") . "&7...",
                    ]);
                    break;
            }

            if ($this->timeLeft <= 0) {
                $this->drawWinner();
            }
        }), 20);
    }

    public function getTaxPercent(): float
    {
        return $this->taxPercent;
    }

    public function getTimeLeft(): int
    {
        return $this->timeLeft;
    }

    public function setTimeLeft(int $seconds): void
    {
        $this->timeLeft = max(1, $seconds);
        $this->saveState();
    }

    public function addTime(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $this->timeLeft = max(1, $this->timeLeft + $seconds);
        $this->saveState();
    }

    public function removeTime(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $this->timeLeft = max(1, $this->timeLeft - $seconds);
        $this->saveState();
    }

    public function forceDraw(): void
    {
        $this->drawWinner();
    }

    public function resetSeasonState(): void
    {
        $this->pot = 0;
        $this->tickets = [];
        $this->lastDrawTime = 0;
        $this->timeLeft = $this->interval;
        $this->saveState();
    }

    private function saveState(): void
    {
        $databasePath = $this->getDatabasePath();
        if (!is_file($databasePath)) {
            return;
        }

        $db = null;
        try {
            $db = new SQLite3($databasePath);
            $stmt = $db->prepare(
                "INSERT OR REPLACE INTO glacia_jackpot (id, pot, last_draw, draw_at, tickets_json)
                VALUES (1, :pot, :last_draw, :draw_at, :tickets_json);"
            );
            if ($stmt === false) {
                throw new \RuntimeException($db->lastErrorMsg());
            }

            $stmt->bindValue(":pot", $this->pot, SQLITE3_INTEGER);
            $stmt->bindValue(":last_draw", $this->lastDrawTime, SQLITE3_INTEGER);
            $stmt->bindValue(":draw_at", $this->getDrawAtTimestamp(), SQLITE3_INTEGER);
            $stmt->bindValue(":tickets_json", json_encode($this->tickets, JSON_THROW_ON_ERROR), SQLITE3_TEXT);
            $result = $stmt->execute();
            if ($result === false) {
                throw new \RuntimeException($db->lastErrorMsg());
            }
            $result->finalize();
        } catch (Throwable) {
        } finally {
            if ($db instanceof SQLite3) {
                $db->close();
            }
        }
    }

    private function loadState(): void
    {
        $this->pot = 0;
        $this->tickets = [];
        $this->lastDrawTime = time();
        $this->timeLeft = $this->interval;

        $databasePath = $this->getDatabasePath();
        if (!is_file($databasePath)) {
            return;
        }

        $db = null;
        try {
            $db = new SQLite3($databasePath, SQLITE3_OPEN_READWRITE);
            $result = $db->querySingle("SELECT pot, last_draw, draw_at, tickets_json FROM glacia_jackpot WHERE id = 1;", true);
            if (!is_array($result)) {
                $db->exec(
                    "INSERT OR IGNORE INTO glacia_jackpot (id, pot, last_draw, draw_at, tickets_json)
                    VALUES (1, 0, {$this->lastDrawTime}, {$this->getDrawAtTimestamp()}, '{}');"
                );
                return;
            }

            $now = time();
            $drawAt = max(0, (int) ($result["draw_at"] ?? 0));
            $this->pot = max(0, (int) ($result["pot"] ?? 0));
            $this->lastDrawTime = max(0, (int) ($result["last_draw"] ?? 0));
            $this->tickets = $this->decodeTickets((string) ($result["tickets_json"] ?? "{}"));

            if ($drawAt <= 0) {
                $this->timeLeft = $this->interval;
            } elseif ($drawAt <= $now) {
                $this->timeLeft = 1;
            } else {
                $this->timeLeft = max(1, $drawAt - $now);
            }
        } catch (Throwable) {
            $this->pot = 0;
            $this->tickets = [];
            $this->lastDrawTime = time();
            $this->timeLeft = $this->interval;
        } finally {
            if ($db instanceof SQLite3) {
                $db->close();
            }
        }
    }

    private function awardPrize(string $uuid, int $prize): string
    {
        $online = Loader::getInstance()->getPlayerManager()->getSessions()[$uuid] ?? null;
        if ($online !== null) {
            $online->addBalance($prize);
            $username = $online->getUsername();
            $this->updateStats($uuid, $username, $prize);
            return $username;
        }

        $databasePath = $this->getDatabasePath();
        $username = "Unknown";
        if (!is_file($databasePath)) {
            return $username;
        }

        $db = null;
        try {
            $db = new SQLite3($databasePath);
            $db->exec("BEGIN IMMEDIATE TRANSACTION;");

            $escapedUuid = SQLite3::escapeString($uuid);
            $playerRow = $db->querySingle("SELECT username, balance FROM glacia_players WHERE uuid = '{$escapedUuid}' LIMIT 1;", true);
            if (is_array($playerRow)) {
                $username = (string) ($playerRow["username"] ?? "Unknown");
                $newBalance = max(0, (int) round((float) ($playerRow["balance"] ?? 0) + $prize));
                $db->exec("UPDATE glacia_players SET balance = {$newBalance} WHERE uuid = '{$escapedUuid}';");
            }

            $statsRow = $db->querySingle("SELECT wins, winnings FROM glacia_jackpot_stats WHERE uuid = '{$escapedUuid}' LIMIT 1;", true);
            $escapedUsername = SQLite3::escapeString($username);
            if (is_array($statsRow)) {
                $wins = max(0, (int) ($statsRow["wins"] ?? 0)) + 1;
                $winnings = max(0, (int) ($statsRow["winnings"] ?? 0)) + $prize;
                $db->exec("UPDATE glacia_jackpot_stats SET username = '{$escapedUsername}', wins = {$wins}, winnings = {$winnings} WHERE uuid = '{$escapedUuid}';");
            } else {
                $db->exec("INSERT OR REPLACE INTO glacia_jackpot_stats (uuid, username, wins, winnings) VALUES ('{$escapedUuid}', '{$escapedUsername}', 1, {$prize});");
            }

            $db->exec("COMMIT;");
        } catch (Throwable) {
            if ($db instanceof SQLite3) {
                @$db->exec("ROLLBACK;");
            }
        } finally {
            if ($db instanceof SQLite3) {
                $db->close();
            }
        }

        return $username;
    }

    private function updateStats(string $uuid, string $username, int $prize): void
    {
        $db = Loader::getInstance()->getDatabase();
        $db->executeSelect(QueryStmts::JACKPOT_STATS_SELECT, ["uuid" => $uuid], function (array $rows) use ($db, $uuid, $username, $prize): void {
            if (!empty($rows)) {
                $row = $rows[0];
                $db->executeChange(QueryStmts::JACKPOT_STATS_UPDATE, [
                    "uuid" => $uuid,
                    "wins" => ((int) $row["wins"]) + 1,
                    "winnings" => ((int) $row["winnings"]) + $prize
                ]);
                return;
            }

            $db->executeInsert(QueryStmts::JACKPOT_STATS_INSERT, [
                "uuid" => $uuid,
                "username" => $username,
                "wins" => 1,
                "winnings" => $prize
            ]);
        });
    }

    /**
     * @return array<string, int>
     */
    private function decodeTickets(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $tickets = [];
        foreach ($decoded as $uuid => $count) {
            if (!is_string($uuid)) {
                continue;
            }

            $normalized = max(0, (int) $count);
            if ($normalized > 0) {
                $tickets[$uuid] = $normalized;
            }
        }

        return $tickets;
    }

    private function getDrawAtTimestamp(): int
    {
        return time() + max(1, $this->timeLeft);
    }

    private function getDatabasePath(): string
    {
        return Loader::getInstance()->getDataFolder() . "sqlite.sql";
    }

    /**
     * @param list<string> $lines
     */
    private function broadcast(array $lines): void
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            foreach ($lines as $line) {
                $player->sendMessage(C::colorize($line));
            }
        }
    }
}
