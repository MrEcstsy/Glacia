<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class GlobalFundManager
{
    /** @var array<string, mixed> */
    private array $config = [];

    private bool $enabled = true;
    private int $interval = 7200;
    /** @var int[] */
    private array $milestones = [25, 50, 75, 100];
    private int $defaultMinimumContribution = 50000;

    /** @var array<string, mixed>|null */
    private ?array $activeFund = null;
    private int $nextStartAt = 0;
    private bool $loaded = false;

    public function __construct()
    {
        $this->reloadConfig();
        $this->loadState();
    }

    public function reloadConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "fund.yml");
        $this->config = $config?->getAll() ?? [];
        $this->enabled = (bool) ($this->config["enabled"] ?? true);
        $this->interval = max(60, GeneralUtils::parseTimeString((string) ($this->config["interval"] ?? "2h")));
        $this->defaultMinimumContribution = max(1, GeneralUtils::parseRandomNumber($this->config["minimum-contribution"] ?? 50000));

        $milestones = array_map(
            static fn(mixed $value): int => max(1, min(100, (int) $value)),
            (array) ($this->config["milestone-broadcasts"] ?? [25, 50, 75, 100])
        );
        $milestones = array_values(array_unique($milestones));
        sort($milestones);
        $this->milestones = $milestones === [] ? [25, 50, 75, 100] : $milestones;

        if (!$this->loaded && $this->nextStartAt <= 0) {
            $this->nextStartAt = time() + $this->interval;
        }
    }

    public function tick(): void
    {
        if (!$this->enabled || !$this->loaded) {
            return;
        }

        $now = time();
        if ($this->activeFund !== null) {
            if (($this->activeFund["ends_at"] ?? 0) <= $now) {
                $this->failActiveFund();
            }
            return;
        }

        if ($this->nextStartAt <= 0) {
            $this->scheduleNextFund();
            return;
        }

        if ($this->nextStartAt <= $now) {
            $this->startRandomFund();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFundInfo(): ?array
    {
        return $this->activeFund;
    }

    public function getTimeLeft(): int
    {
        if ($this->activeFund !== null) {
            return max(0, (int) ($this->activeFund["ends_at"] ?? 0) - time());
        }

        return max(0, $this->nextStartAt - time());
    }

    public function contribute(Player $player, int $amount): string
    {
        if ($this->activeFund === null) {
            return C::colorize("&r&cThere is no active global fund right now.");
        }

        if ($amount <= 0) {
            return C::colorize("&r&cContribution amount must be greater than 0.");
        }

        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return C::colorize("&r&cYour session is unavailable right now.");
        }

        $remaining = $this->getRemainingGoal();
        if ($remaining <= 0) {
            return C::colorize("&r&cThis fund is already fully funded.");
        }

        $contribution = min($amount, $remaining);
        $minimum = $this->getMinimumContribution();
        if ($contribution < $minimum && $remaining > $minimum) {
            return C::colorize("&r&cMinimum fund contribution is &f$" . number_format($minimum) . "&c.");
        }

        if ($session->getBalance() < $contribution) {
            return C::colorize("&r&cYou do not have enough money to contribute that much.");
        }

        $session->removeBalance($contribution);
        $this->activeFund["contributed"] = (int) ($this->activeFund["contributed"] ?? 0) + $contribution;
        $contributors = (array) ($this->activeFund["contributors"] ?? []);
        $uuid = $player->getUniqueId()->toString();
        $entry = is_array($contributors[$uuid] ?? null) ? $contributors[$uuid] : [];
        $entry["name"] = $player->getName();
        $entry["amount"] = (int) ($entry["amount"] ?? 0) + $contribution;
        $contributors[$uuid] = $entry;
        $this->activeFund["contributors"] = $contributors;
        $this->saveState();

        $display = (string) ($this->activeFund["display_name"] ?? "Global Fund");
        $player->sendMessage(C::colorize(
            "&r&aYou contributed &f$" . number_format($contribution) . " &ato the &b{$display}&a."
        ));

        $this->broadcastMilestones($player->getName(), $contribution);

        if ($this->getRemainingGoal() <= 0) {
            $this->completeActiveFund();
        }

        return "";
    }

    public function forceStart(): bool
    {
        if ($this->activeFund !== null) {
            return false;
        }

        return $this->startRandomFund();
    }

    public function forceStartById(string $id): bool
    {
        if ($this->activeFund !== null) {
            return false;
        }

        return $this->startFundById($id);
    }

    public function forceFail(): bool
    {
        if ($this->activeFund === null) {
            return false;
        }

        $this->failActiveFund();
        return true;
    }

    public function forceComplete(): bool
    {
        if ($this->activeFund === null) {
            return false;
        }

        $this->activeFund["contributed"] = (int) ($this->activeFund["goal"] ?? 0);
        $this->completeActiveFund();
        return true;
    }

    public function setActiveTimeLeft(int $seconds): bool
    {
        if ($this->activeFund === null) {
            return false;
        }

        $seconds = max(1, $seconds);
        $this->activeFund["ends_at"] = time() + $seconds;
        $this->saveState();
        return true;
    }

    public function addActiveTime(int $seconds): bool
    {
        if ($this->activeFund === null) {
            return false;
        }

        $currentEndsAt = (int) ($this->activeFund["ends_at"] ?? time());
        $newEndsAt = max(time() + 1, $currentEndsAt + $seconds);
        $this->activeFund["ends_at"] = $newEndsAt;
        $this->saveState();
        return true;
    }

    public function setNextStartDelay(int $seconds): void
    {
        $this->activeFund = null;
        $this->nextStartAt = time() + max(1, $seconds);
        $this->saveState();
    }

    public function setGoal(int $amount): bool
    {
        if ($this->activeFund === null) {
            return false;
        }

        $goal = max(1, $amount);
        $this->activeFund["goal"] = $goal;
        $this->activeFund["contributed"] = min((int) ($this->activeFund["contributed"] ?? 0), $goal);
        $this->saveState();
        return true;
    }

    public function setContribution(int $amount): bool
    {
        if ($this->activeFund === null) {
            return false;
        }

        $goal = max(1, (int) ($this->activeFund["goal"] ?? 1));
        $this->activeFund["contributed"] = max(0, min($amount, $goal));
        $this->saveState();
        return true;
    }

    /**
     * @return string[]
     */
    public function getEventIds(): array
    {
        return array_keys($this->getEnabledFunds());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getEnabledFunds(): array
    {
        $entries = [];
        foreach ((array) ($this->config["events"] ?? []) as $id => $entry) {
            if (!is_array($entry) || !(bool) ($entry["enabled"] ?? true)) {
                continue;
            }

            $entries[(string) $id] = $entry;
        }

        return $entries;
    }

    private function startRandomFund(): bool
    {
        $funds = $this->getEnabledFunds();
        if ($funds === []) {
            $this->scheduleNextFund();
            return false;
        }

        $ids = array_keys($funds);
        $selectedId = $ids[array_rand($ids)];
        return $this->startFundFromEntry($selectedId, $funds[$selectedId]);
    }

    private function startFundById(string $id): bool
    {
        $funds = $this->getEnabledFunds();
        $selected = $funds[$id] ?? null;
        if ($selected === null) {
            return false;
        }

        return $this->startFundFromEntry($id, $selected);
    }

    /**
     * @param array<string, mixed> $selected
     */
    private function startFundFromEntry(string $selectedId, array $selected): bool
    {
        $selected = $selected;

        $duration = max(
            60,
            GeneralUtils::parseTimeString((string) ($selected["duration"] ?? $this->config["default-duration"] ?? "30m"))
        );

        $goal = max(1, GeneralUtils::parseRandomNumber($selected["goal"] ?? 1000000));
        $displayName = (string) ($selected["display-name"] ?? $selectedId);
        $description = array_values(array_map("strval", (array) ($selected["description"] ?? [])));
        $rewards = is_array($selected["rewards"] ?? null) ? array_values($selected["rewards"]) : [];

        $this->activeFund = [
            "id" => $selectedId,
            "display_name" => $displayName,
            "description" => $description,
            "goal" => $goal,
            "minimum_contribution" => max(
                1,
                GeneralUtils::parseRandomNumber($selected["minimum-contribution"] ?? $this->config["minimum-contribution"] ?? 50000)
            ),
            "contributed" => 0,
            "contributors" => [],
            "starts_at" => time(),
            "ends_at" => time() + $duration,
            "milestones_hit" => [],
            "rewards" => $rewards,
        ];
        $this->nextStartAt = 0;
        $this->saveState();

        $lines = [
            "&r&l&bGlobal Fund &8| &r&f{$displayName}",
            "&r&7Goal: &a$" . number_format($goal) . " &8| &7Time: &f" . GeneralUtils::translateTime($duration),
            "&r&7Minimum Contribution: &f$" . number_format($this->getMinimumContribution()),
        ];

        foreach ($description as $line) {
            $lines[] = $line;
        }

        $lines[] = "&r&7Use &f/fund <amount> &7to contribute.";
        $this->broadcastLines($lines);
        return true;
    }

    private function completeActiveFund(): void
    {
        if ($this->activeFund === null) {
            return;
        }

        $display = (string) ($this->activeFund["display_name"] ?? "Global Fund");
        $contributors = (array) ($this->activeFund["contributors"] ?? []);
        $rewarded = 0;
        $this->broadcastLines([
            "&r&l&bGlobal Fund &8| &r&a{$display} has been fully funded!",
            "&r&7Only players who contributed will receive the event reward.",
        ]);

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if (!isset($contributors[$player->getUniqueId()->toString()])) {
                continue;
            }

            $this->grantRewards($player, (array) ($this->activeFund["rewards"] ?? []));
            $rewarded++;
        }

        $this->broadcastLines([
            "&r&7Rewarded Contributors Online: &f{$rewarded}&7/&f" . count($contributors),
        ]);

        $this->activeFund = null;
        $this->scheduleNextFund();
        $this->saveState();
    }

    private function failActiveFund(): void
    {
        if ($this->activeFund === null) {
            return;
        }

        $display = (string) ($this->activeFund["display_name"] ?? "Global Fund");
        $raised = (int) ($this->activeFund["contributed"] ?? 0);
        $goal = (int) ($this->activeFund["goal"] ?? 0);

        $this->broadcastLines([
            "&r&l&bGlobal Fund &8| &r&c{$display} has ended.",
            "&r&7Raised: &f$" . number_format($raised) . " &8/ &a$" . number_format($goal),
        ]);

        $this->activeFund = null;
        $this->scheduleNextFund();
        $this->saveState();
    }

    private function getRemainingGoal(): int
    {
        if ($this->activeFund === null) {
            return 0;
        }

        return max(0, (int) ($this->activeFund["goal"] ?? 0) - (int) ($this->activeFund["contributed"] ?? 0));
    }

    private function scheduleNextFund(): void
    {
        $this->nextStartAt = time() + $this->interval;
    }

    private function getMinimumContribution(): int
    {
        if ($this->activeFund !== null) {
            return max(1, (int) ($this->activeFund["minimum_contribution"] ?? $this->defaultMinimumContribution));
        }

        return $this->defaultMinimumContribution;
    }

    private function broadcastMilestones(string $contributor, int $contribution): void
    {
        if ($this->activeFund === null) {
            return;
        }

        $goal = max(1, (int) ($this->activeFund["goal"] ?? 1));
        $contributed = (int) ($this->activeFund["contributed"] ?? 0);
        $percent = (int) floor(($contributed / $goal) * 100);
        $hit = (array) ($this->activeFund["milestones_hit"] ?? []);
        $updated = false;

        foreach ($this->milestones as $milestone) {
            if ($percent < $milestone || in_array($milestone, $hit, true)) {
                continue;
            }

            $hit[] = $milestone;
            $updated = true;
            $display = (string) ($this->activeFund["display_name"] ?? "Global Fund");
            $this->broadcastLines([
                "&r&l&bGlobal Fund &8| &r&f{$display} &7has reached &b{$milestone}%&7!",
                "&r&7Latest Contribution: &f{$contributor} &8(+&a$" . number_format($contribution) . "&8)",
                "&r&7Progress: &a$" . number_format($contributed) . " &8/ &f$" . number_format($goal),
            ]);
        }

        if ($updated) {
            $this->activeFund["milestones_hit"] = $hit;
            $this->saveState();
        }
    }

    /**
     * @param array<int, mixed> $rewards
     */
    private function grantRewards(Player $player, array $rewards): void
    {
        $session = Loader::getPlayerManager()->getSession($player);

        foreach ($rewards as $reward) {
            if (!is_array($reward)) {
                continue;
            }

            $type = strtolower((string) ($reward["type"] ?? "item"));
            switch ($type) {
                case "money":
                    if ($session === null) {
                        break;
                    }

                    $amount = max(1, GeneralUtils::parseRandomNumber($reward["amount"] ?? 0));
                    $session->addBalance($amount);
                    $this->sendRewardMessage($player, "$" . number_format($amount));
                    break;

                case "xp":
                    $amount = max(1, GeneralUtils::parseRandomNumber($reward["amount"] ?? 0));
                    $player->getXpManager()->addXp($amount);
                    $this->sendRewardMessage($player, number_format($amount) . " XP");
                    break;

                case "command":
                    $command = str_replace("{player}", $player->getName(), (string) ($reward["command"] ?? ""));
                    if ($command !== "") {
                        Server::getInstance()->dispatchCommand(new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage()), $command);
                    }
                    break;

                default:
                    $item = RewardResolver::resolveItem($reward, $player);
                    if ($item === null) {
                        break;
                    }

                    foreach ($player->getInventory()->addItem($item) as $leftover) {
                        $player->getWorld()->dropItem($player->getPosition(), $leftover);
                    }

                    $this->sendRewardMessage($player, $item->getName());
                    break;
            }
        }
    }

    /**
     * @param string[] $lines
     */
    private function broadcastLines(array $lines): void
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            foreach ($lines as $line) {
                $online->sendMessage(C::colorize($line));
            }
        }
    }

    private function sendRewardMessage(Player $player, string $rewardText): void
    {
        $player->sendMessage(C::colorize("&r&aFund Reward: &f" . $rewardText));
    }

    private function loadState(): void
    {
        Loader::getDatabase()->executeSelect(QueryStmts::SERVER_GLACIA_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                if (($row["key"] ?? "") !== GlaciaServerKeys::GLOBAL_FUND_STATE) {
                    continue;
                }

                $decoded = json_decode((string) ($row["value"] ?? ""), true);
                if (!is_array($decoded)) {
                    break;
                }

                $this->activeFund = is_array($decoded["active"] ?? null) ? $decoded["active"] : null;
                $this->nextStartAt = max(0, (int) ($decoded["next_start_at"] ?? 0));
                break;
            }

            if ($this->activeFund === null && $this->nextStartAt <= 0) {
                $this->scheduleNextFund();
            }

            $this->loaded = true;
            $this->saveState();
        });
    }

    private function saveState(): void
    {
        $payload = json_encode([
            "active" => $this->activeFund,
            "next_start_at" => $this->nextStartAt,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return;
        }

        Loader::getDatabase()->executeInsert(QueryStmts::SERVER_GLACIA_CREATE, [
            "key" => GlaciaServerKeys::GLOBAL_FUND_STATE,
            "value" => $payload,
        ]);
    }
}
