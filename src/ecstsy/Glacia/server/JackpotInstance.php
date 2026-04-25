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
use Ramsey\Uuid\Uuid;

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
        $this->loadPot();
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

    public function purchaseTickets(Player $player, int $amount): bool
    {
        if ($amount <= 0) {
            $player->sendMessage(C::colorize("&r&cYou must buy at least one jackpot ticket."));
            return false;
        }

        $uuid = $player->getUniqueId()->toString();
        $currentTickets = $this->tickets[$uuid] ?? 0;

        if ($currentTickets >= self::MAX_TICKETS_PER_PLAYER) {
            $player->sendMessage(C::colorize("&r&cYou have already purchased the maximum number of jackpot tickets this round."));
            return false;
        }

        if (($currentTickets + $amount) > self::MAX_TICKETS_PER_PLAYER) {
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
            $player->sendMessage(C::colorize("&r&cYou do not have enough money to buy &f{$amount} &cticket(s)."));
            return false;
        }

        $session->removeBalance($cost);
        $this->pot += $cost;
        $this->tickets[$uuid] = $currentTickets + $amount;
        $player->sendMessage(C::colorize("&r&aYou bought &f{$amount} &ajackpot ticket(s)."));
        $this->savePot();
        return true;
    }

    public function drawWinner(): void
    {
        if ($this->tickets === []) {
            $this->broadcast([
                "&r&l&dJackpot",
                "&r&7No tickets were sold this round.",
            ]);
            $this->resetJackpot();
            return;
        }

        $winnerUuid = $this->selectWinner();
        if ($winnerUuid === null) {
            $this->resetJackpot();
            return;
        }

        $winnerSession = Loader::getInstance()->getPlayerManager()->getSessionByUuid(Uuid::fromString($winnerUuid));
        $winnerName = $winnerSession?->getUsername() ?? "Unknown";
        $tax = (int) round($this->pot * $this->taxPercent);
        $prize = max(0, $this->pot - $tax);

        if ($winnerSession !== null) {
            $winnerSession->addBalance($prize);
            $this->updateStats($winnerUuid, $winnerName, $prize);
        }

        $this->broadcast([
            "&r&l&dJackpot",
            "&r&7Winner: &f{$winnerName}",
            "&r&7Prize: &d$" . number_format($prize) . " &8(Tax: &c$" . number_format($tax) . "&8)",
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
            $this->savePot();
        }
    }

    private function resetJackpot(): void
    {
        $this->pot = 0;
        $this->tickets = [];
        $this->lastDrawTime = time();
        $this->savePot();
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
                    $this->broadcast(["&r&dJackpot rolls in &b{$this->timeLeft} &dseconds!"]);
                    break;

                case 3:
                case 2:
                case 1:
                    $this->broadcast(["&r&dJackpot rolls in &b{$this->timeLeft} &dsecond" . ($this->timeLeft === 1 ? "" : "s") . "!"]);
                    break;
            }

            if ($this->timeLeft <= 0) {
                $this->drawWinner();
                $this->timeLeft = $this->interval;
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

    private function savePot(): void
    {
        Loader::getInstance()->getDatabase()->executeChange(QueryStmts::JACKPOT_UPDATE, [
            "pot" => $this->pot,
            "last_draw" => $this->lastDrawTime
        ]);
    }

    private function loadPot(): void
    {
        Loader::getInstance()->getDatabase()->executeSelect(QueryStmts::JACKPOT_SELECT, [], function (array $rows): void {
            if (!empty($rows)) {
                $row = $rows[0];
                $this->pot = (int) $row["pot"];
                $this->lastDrawTime = (int) $row["last_draw"];
                return;
            }

            $this->lastDrawTime = time();
            Loader::getInstance()->getDatabase()->executeInsert(QueryStmts::JACKPOT_INSERT, [
                "pot" => 0,
                "last_draw" => $this->lastDrawTime
            ]);
        });
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
