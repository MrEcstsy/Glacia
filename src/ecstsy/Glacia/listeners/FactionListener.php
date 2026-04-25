<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
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
        $canFly = !$inCombat && $isOwnClaim && Loader::getFactionUpgradeManager()->canFlyInOwnClaim($faction);

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
}
