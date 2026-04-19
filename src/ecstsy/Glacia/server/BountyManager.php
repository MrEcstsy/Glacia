<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\utils\TextFormat as C;

final class BountyManager
{
    /** @var array<string, array{name: string, amount: int, updated_at: int}> */
    private array $bounties = [];

    public function __construct()
    {
        Loader::getDatabase()->executeSelect(QueryStmts::BOUNTIES_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $uuid = (string) ($row["target_uuid"] ?? "");
                if ($uuid === "") {
                    continue;
                }

                $this->bounties[$uuid] = [
                    "name" => (string) ($row["target_name"] ?? "Unknown"),
                    "amount" => max(0, (int) ($row["amount"] ?? 0)),
                    "updated_at" => (int) ($row["updated_at"] ?? time()),
                ];
            }
        });
    }

    public function getBounty(GlaciaPlayer|string $target): int
    {
        $uuid = $target instanceof GlaciaPlayer ? $target->getUuid()->toString() : $target;
        return max(0, (int) ($this->bounties[$uuid]["amount"] ?? 0));
    }

    public function hasBounty(GlaciaPlayer|string $target): bool
    {
        return $this->getBounty($target) > 0;
    }

    public function setBounty(GlaciaPlayer $target, int $amount): int
    {
        $uuid = $target->getUuid()->toString();
        $newAmount = $this->getBounty($uuid) + max(0, $amount);

        $this->bounties[$uuid] = [
            "name" => $target->getUsername(),
            "amount" => $newAmount,
            "updated_at" => time(),
        ];

        Loader::getDatabase()->executeInsert(QueryStmts::BOUNTIES_CREATE, [
            "target_uuid" => $uuid,
            "target_name" => $target->getUsername(),
            "amount" => $newAmount,
            "updated_at" => time(),
        ]);

        return $newAmount;
    }

    public function clearBounty(GlaciaPlayer|string $target): void
    {
        $uuid = $target instanceof GlaciaPlayer ? $target->getUuid()->toString() : $target;
        unset($this->bounties[$uuid]);
        Loader::getDatabase()->executeChange(QueryStmts::BOUNTIES_DELETE, ["target_uuid" => $uuid]);
    }

    public function claimBounty(GlaciaPlayer $target): int
    {
        $amount = $this->getBounty($target);
        if ($amount <= 0) {
            return 0;
        }

        $this->clearBounty($target);
        return $amount;
    }

    /**
     * @return array<int, array{uuid: string, name: string, amount: int}>
     */
    public function getTopBounties(int $limit = 10, int $offset = 0): array
    {
        $rows = [];
        foreach ($this->bounties as $uuid => $entry) {
            if (($entry["amount"] ?? 0) <= 0) {
                continue;
            }

            $rows[] = [
                "uuid" => $uuid,
                "name" => (string) ($entry["name"] ?? "Unknown"),
                "amount" => (int) ($entry["amount"] ?? 0),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => $b["amount"] <=> $a["amount"]);
        return array_slice($rows, max(0, $offset), max(1, $limit));
    }

    public function getCount(): int
    {
        return count(array_filter($this->bounties, static fn(array $entry): bool => ((int) ($entry["amount"] ?? 0)) > 0));
    }

    public function broadcastPlacement(string $setterName, string $targetName, int $amount): void
    {
        $message = C::colorize("&r&l&6Bounty &8| &r&f{$setterName} &7set a bounty on &c{$targetName} &7for &a$" . number_format($amount) . "&7.");
        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $online) {
            $online->sendMessage($message);
        }
    }

    public function broadcastClaim(string $killerName, string $targetName, int $amount): void
    {
        $message = C::colorize("&r&l&6Bounty &8| &r&f{$killerName} &7claimed the bounty on &c{$targetName} &7for &a$" . number_format($amount) . "&7.");
        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $online) {
            $online->sendMessage($message);
        }
    }
}
