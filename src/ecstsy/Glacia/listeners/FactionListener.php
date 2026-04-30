<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FactionListener implements Listener 
{
    private static array $claimStates = [];
    private static array $claimFlight = [];
    private const POWER_KILL_REWARD = 5;
    private const POWER_DEATH_PENALTY = 10;
    private const POWER_MAX = 100;
    private const FARM_WINDOW_SECONDS = 900;
    private const FARM_MAX_KILLS_PER_WINDOW = 2;
    /** @var array<string, list<int>> */
    private static array $recentKillRewards = [];

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $uuid = $player->getUniqueId()->toString();

        $fromChunkX = $event->getFrom()->getFloorX() >> 4;
        $fromChunkZ = $event->getFrom()->getFloorZ() >> 4;
        $toChunkX = $event->getTo()->getFloorX() >> 4;
        $toChunkZ = $event->getTo()->getFloorZ() >> 4;
        if ($fromChunkX === $toChunkX && $fromChunkZ === $toChunkZ) {
            return; 
        }

        $claim = ClaimManager::getInstance()->getClaimAtPosition($player->getPosition());
        if ($claim !== null && $claim->getFaction() === null) {
            ClaimManager::getInstance()->deleteClaim($claim);
            $claim = null;
        }

        $currentFactionId = $claim?->getFactionId();

        if ((self::$claimStates[$uuid] ?? null) === $currentFactionId) {
            return;
        }
        self::$claimStates[$uuid] = $currentFactionId;

        self::syncClaimFlight($player, $claim);
        GlaciaUtils::handleClaimTitle($player, $claim);
    }

    public static function syncClaimFlight(Player $player, ?\ecstsy\Glacia\server\factions\claims\FactionClaim $claim = null): void
    {
        $uuid = $player->getUniqueId()->toString();
        $session = Loader::getPlayerManager()->getSession($player);
        $claim ??= ClaimManager::getInstance()->getClaimAtPosition($player->getPosition());
        $faction = $session?->getFaction();
        $isOwnClaim = $claim !== null && $faction !== null && $claim->getFactionId() === $faction->getFactionId();
        $inCombat = Loader::getCombatManager()->isInCombat($player);
        $manualFly = GlaciaUtils::hasManualFlightEnabled($player) && $player->isSurvival();
        $zone = ZoneManager::getZoneAt($player->getPosition());
        $zoneBlocksFlight = $zone !== null && !ZoneManager::allowsFlight($zone) && !$player->hasPermission("glacia.staff");
        $canFly = !$inCombat && $isOwnClaim && Loader::getFactionUpgradeManager()->canFlyInOwnClaim($faction);

        if ($zoneBlocksFlight) {
            if ($player->isFlying()) {
                $player->setFlying(false);
                $player->resetFallDistance();
            }
            if ($player->getAllowFlight()) {
                $player->setAllowFlight(false);
            }
            unset(self::$claimFlight[$uuid]);
            return;
        }

        if ($canFly) {
            if (!$player->getAllowFlight()) {
                $player->setAllowFlight(true);
                $player->sendMessage(C::colorize("&r&bSkybound Accord &7lets you fly within your claim."));
            }
            self::$claimFlight[$uuid] = true;
            return;
        }

        if ((self::$claimFlight[$uuid] ?? false) === true) {
            unset(self::$claimFlight[$uuid]);
            if (!$manualFly) {
                $player->setAllowFlight(false);
                $player->setFlying(false);
                $player->resetFallDistance();
            }
        }

        if ($inCombat) {
            GlaciaUtils::disableFlightForCombat($player);
            return;
        }

        if ($manualFly && !$player->getAllowFlight()) {
            $player->setAllowFlight(true);
        } elseif (!$manualFly && !$canFly && $player->isSurvival() && $player->getAllowFlight()) {
            $player->setAllowFlight(false);
            $player->setFlying(false);
            $player->resetFallDistance();
        }
    }

    public function onEntityDamage(EntityDamageByEntityEvent $event): void {
        $damager = $event->getDamager();
        $victim = $event->getEntity();

        if (!$damager instanceof Player || !$victim instanceof Player) {
            return; 
        }

        $attacker = Loader::getPlayerManager()->getSession($damager);
        $target = Loader::getPlayerManager()->getSession($victim);

        $attackerFac = $attacker->getFaction()?->getFactionId();
        $targetFac = $target->getFaction()?->getFactionId();

        if ($attackerFac === null || $targetFac === null) {
            return; 
        }

        if ($attackerFac === $targetFac) {
            $event->cancel();
            $damager->sendMessage(C::colorize("&r&a" . GlaciaUtils::getFactionRoleSymbol($target->getFactionRole()) . $victim->getName() . " &7can't be hurt!"));
            return;
        }

        if (RelationManager::getInstance()->getRelation($attackerFac, $targetFac) === "ally") {
            $event->cancel();
            $damager->sendMessage(C::colorize("&r&d" . GlaciaUtils::getFactionRoleSymbol($target->getFactionRole()) . $victim->getName() . " &7can't be hurt!"));
            return;
        }
    }

    public function onPowerChange(EntityDamageByEntityEvent $event): void {
        $damager = $event->getDamager();
        $victim = $event->getEntity();

    }

    public function onPlayerDeath(PlayerDeathEvent $event): void
    {
        $victim = $event->getPlayer();
        $victimSession = Loader::getPlayerManager()->getSession($victim);
        if ($victimSession === null) {
            return;
        }

        // Death penalty only outside warzone and only for faction members.
        if ($victimSession->getFactionId() !== null) {
            $region = Loader::getRegionManager()->getRegionAt($victim->getPosition());
            $isWarzone = $region !== null && strtolower($region->getName()) === "warzone";
            if (!$isWarzone) {
                $newPower = max(0, $victimSession->getPower() - self::POWER_DEATH_PENALTY);
                if ($newPower !== $victimSession->getPower()) {
                    $victimSession->setPower($newPower);
                    $victim->sendMessage(C::colorize("&r&cYou lost &f" . self::POWER_DEATH_PENALTY . " &cpower for dying outside warzone."));
                }
            }
        }

        $cause = $victim->getLastDamageCause();
        if (!$cause instanceof EntityDamageByEntityEvent) {
            return;
        }

        $damager = $cause->getDamager();
        if ($damager instanceof Projectile) {
            $owner = $damager->getOwningEntity();
            $damager = $owner instanceof Player ? $owner : $damager;
        }
        if (!$damager instanceof Player || !$damager->isConnected() || $damager->getUniqueId()->toString() === $victim->getUniqueId()->toString()) {
            return;
        }

        $killerSession = Loader::getPlayerManager()->getSession($damager);
        if ($killerSession === null || $killerSession->getFactionId() === null || $victimSession->getFactionId() === null) {
            return;
        }
        if ($killerSession->getFactionId() === $victimSession->getFactionId()) {
            return;
        }
        if (!$this->canRewardKillPower($damager, $victim)) {
            $damager->sendMessage(C::colorize("&r&7No power gained: anti-farm cooldown for this target."));
            return;
        }

        $newPower = min(self::POWER_MAX, $killerSession->getPower() + self::POWER_KILL_REWARD);
        if ($newPower !== $killerSession->getPower()) {
            $killerSession->setPower($newPower);
            $damager->sendMessage(C::colorize("&r&aYou gained &f" . self::POWER_KILL_REWARD . " &apower for that kill."));
        }
    }

    private function canRewardKillPower(Player $killer, Player $victim): bool
    {
        $now = time();
        $pairKey = strtolower($killer->getUniqueId()->toString() . ":" . $victim->getUniqueId()->toString());
        $windowStart = $now - self::FARM_WINDOW_SECONDS;
        $history = self::$recentKillRewards[$pairKey] ?? [];
        $history = array_values(array_filter($history, static fn(int $ts): bool => $ts >= $windowStart));
        if (count($history) >= self::FARM_MAX_KILLS_PER_WINDOW) {
            self::$recentKillRewards[$pairKey] = $history;
            return false;
        }
        $history[] = $now;
        self::$recentKillRewards[$pairKey] = $history;
        return true;
    }
}
