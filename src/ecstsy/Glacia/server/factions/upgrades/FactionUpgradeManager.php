<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\upgrades;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\listeners\FactionListener;
use ecstsy\Glacia\server\factions\Faction;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\scheduler\ClosureTask;

final class FactionUpgradeManager
{
    /** @var array<string, array{bank_balance: float, upgrade_data: array<string, int>}> */
    private array $entries = [];

    public function __construct()
    {
        Loader::getDatabase()->executeSelect(QueryStmts::FACTION_UPGRADE_BANK_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $decoded = StorageUtils::decodeJsonString((string) ($row["upgrade_data"] ?? "{}"), "faction upgrades for " . (string) ($row["faction_id"] ?? "unknown"));
                $this->entries[(string) $row["faction_id"]] = [
                    "bank_balance" => (float) ($row["bank_balance"] ?? 0.0),
                    "upgrade_data" => is_array($decoded) ? array_map("intval", $decoded) : [],
                ];
            }

            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function(): void {
                $this->applyAllEffects();
            }), 1);
        });
    }

    public function ensureFaction(Faction $faction): void
    {
        $factionId = $faction->getFactionId();
        if (!isset($this->entries[$factionId])) {
            $this->entries[$factionId] = [
                "bank_balance" => 0.0,
                "upgrade_data" => [],
            ];
            $this->persist($factionId);
        }

        $this->applyUpgradeEffects($faction);
    }

    public function applyAllEffects(): void
    {
        foreach (Loader::getFactionManager()->getAllFactions() as $faction) {
            $this->ensureFaction($faction);
        }
    }

    public function removeFaction(string $factionId): void
    {
        unset($this->entries[$factionId]);
        Loader::getDatabase()->executeChange(QueryStmts::FACTION_UPGRADE_BANK_DELETE, ["faction_id" => $factionId]);
    }

    public function resetSeasonState(): void
    {
        $this->entries = [];
    }

    public function getBankBalance(string $factionId): float
    {
        return (float) ($this->entries[$factionId]["bank_balance"] ?? 0.0);
    }

    public function deposit(Faction $faction, float $amount): void
    {
        $this->ensureFaction($faction);
        $factionId = $faction->getFactionId();
        $this->entries[$factionId]["bank_balance"] += max(0.0, $amount);
        $this->persist($factionId);
    }

    public function withdraw(Faction $faction, float $amount): bool
    {
        $this->ensureFaction($faction);
        $factionId = $faction->getFactionId();
        if ($this->entries[$factionId]["bank_balance"] < $amount) {
            return false;
        }

        $this->entries[$factionId]["bank_balance"] -= $amount;
        $this->persist($factionId);
        return true;
    }

    public function getUpgradeLevel(string $factionId, string $upgradeKey): int
    {
        return (int) ($this->entries[$factionId]["upgrade_data"][$upgradeKey] ?? 0);
    }

    public function getCollectionSellMultiplier(?Faction $faction): float
    {
        if ($faction === null) {
            return 1.0;
        }

        $definition = $this->getUpgradeDefinition("entrepreneur");
        if ($definition === null || !((bool) ($definition["enabled"] ?? false))) {
            return 1.0;
        }

        $level = $this->getUpgradeLevel($faction->getFactionId(), "entrepreneur");
        if ($level <= 0) {
            return 1.0;
        }

        $multiplierPerLevel = max(1.0, (float) ($definition["sell-multiplier-per-level"] ?? 1.0));
        return $multiplierPerLevel ** $level;
    }

    public function canFlyInOwnClaim(?Faction $faction): bool
    {
        if ($faction === null) {
            return false;
        }

        return $this->getUpgradeLevel($faction->getFactionId(), "aerodynamic") > 0;
    }

    public function getArmorSetMitigationPercent(?Faction $faction): float
    {
        if ($faction === null) {
            return 0.0;
        }

        $definition = $this->getUpgradeDefinition("megaknight");
        if ($definition === null || !((bool) ($definition["enabled"] ?? false))) {
            return 0.0;
        }

        $level = $this->getUpgradeLevel($faction->getFactionId(), "megaknight");
        $percentPerLevel = max(0.0, (float) ($definition["reduction-percent-per-level"] ?? 0.0));
        return $percentPerLevel * $level;
    }

    public function getCombatTagReductionSeconds(?Faction $faction): int
    {
        if ($faction === null) {
            return 0;
        }

        $definition = $this->getUpgradeDefinition("veteran");
        if ($definition === null || !((bool) ($definition["enabled"] ?? false))) {
            return 0;
        }

        $level = $this->getUpgradeLevel($faction->getFactionId(), "veteran");
        $secondsPerLevel = max(0, (int) ($definition["seconds-reduction-per-level"] ?? 0));
        return $secondsPerLevel * $level;
    }

    public function getQuestBonusChance(?Faction $faction): float
    {
        if ($faction === null) {
            return 0.0;
        }

        $definition = $this->getUpgradeDefinition("heroic_rush");
        if ($definition === null || !((bool) ($definition["enabled"] ?? false))) {
            return 0.0;
        }

        $level = $this->getUpgradeLevel($faction->getFactionId(), "heroic_rush");
        $chancePerLevel = max(0.0, (float) ($definition["extra-reward-chance-per-level"] ?? 0.0));
        return $chancePerLevel * $level;
    }

    public function getChallengeParticipantBonus(?Faction $faction): int
    {
        if ($faction === null) {
            return 0;
        }

        $definition = $this->getUpgradeDefinition("challenge_roster");
        if ($definition === null || !((bool) ($definition["enabled"] ?? false))) {
            return 0;
        }

        $level = $this->getUpgradeLevel($faction->getFactionId(), "challenge_roster");
        $bonusPerLevel = max(0, (int) ($definition["extra-participants-per-level"] ?? 0));
        return $level * $bonusPerLevel;
    }

    public function rollQuestBonus(?Faction $faction): bool
    {
        $chance = $this->getQuestBonusChance($faction);
        if ($chance <= 0) {
            return false;
        }

        return mt_rand(1, 10000) <= (int) round($chance * 100);
    }

    public function getUpgradeDefinition(string $upgradeKey): ?array
    {
        $upgrades = $this->getUpgradeDefinitions();
        $definition = $upgrades[$upgradeKey] ?? null;
        return is_array($definition) ? $definition : null;
    }

    public function getEnabledUpgradeDefinitions(): array
    {
        return array_filter(
            $this->getUpgradeDefinitions(),
            static fn(mixed $definition): bool => is_array($definition) && ((bool) ($definition["enabled"] ?? false))
        );
    }

    public function getUpgradeCost(string $upgradeKey, int $nextLevel): int
    {
        $definition = $this->getUpgradeDefinition($upgradeKey);
        if ($definition === null) {
            return 0;
        }

        $baseCost = max(1.0, (float) ($definition["base-cost"] ?? 1));
        $multiplier = max(1.0, (float) ($definition["cost-multiplier"] ?? 1.0));
        return (int) round($baseCost * ($multiplier ** max(0, $nextLevel - 1)));
    }

    public function purchaseUpgrade(Faction $faction, string $upgradeKey): string
    {
        $this->ensureFaction($faction);

        $definition = $this->getUpgradeDefinition($upgradeKey);
        if ($definition === null || !((bool) ($definition["enabled"] ?? false))) {
            return "That upgrade is not available.";
        }

        $currentLevel = $this->getUpgradeLevel($faction->getFactionId(), $upgradeKey);
        $maxLevel = max(1, (int) ($definition["max-level"] ?? 1));
        if ($currentLevel >= $maxLevel) {
            return "That upgrade is already maxed out.";
        }

        $cost = $this->getUpgradeCost($upgradeKey, $currentLevel + 1);
        if ($this->getBankBalance($faction->getFactionId()) < $cost) {
            return "Your faction bank does not have enough money.";
        }

        $this->entries[$faction->getFactionId()]["bank_balance"] -= $cost;
        $this->entries[$faction->getFactionId()]["upgrade_data"][$upgradeKey] = $currentLevel + 1;
        $this->persist($faction->getFactionId());
        $this->applyUpgradeEffects($faction);
        if ($upgradeKey === "aerodynamic") {
            foreach ($faction->getOnlineMembers() as $member) {
                FactionListener::syncClaimFlight($member);
            }
        }
        return "";
    }

    public function applyUpgradeEffects(Faction $faction): void
    {
        // Current faction upgrades are runtime perks and do not mutate base faction stats.
    }

    private function getUpgradeDefinitions(): array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "faction_upgrades.yml");
        $upgrades = $config?->get("upgrades", []);
        return is_array($upgrades) ? $upgrades : [];
    }

    private function persist(string $factionId): void
    {
        $entry = $this->entries[$factionId] ?? ["bank_balance" => 0.0, "upgrade_data" => []];
        $payload = StorageUtils::encodeJsonString($entry["upgrade_data"] ?? [], "faction upgrades for {$factionId}");
        if ($payload === null) {
            return;
        }

        Loader::getDatabase()->executeInsert(QueryStmts::FACTION_UPGRADE_BANK_CREATE, [
            "faction_id" => $factionId,
            "bank_balance" => (float) ($entry["bank_balance"] ?? 0.0),
            "upgrade_data" => $payload,
        ]);
    }
}
