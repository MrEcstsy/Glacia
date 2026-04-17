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
        $currentFactionId = $claim !== null ? $claim->getFaction()->getFactionId() : null;

        if ((self::$claimStates[$uuid] ?? null) === $currentFactionId) {
            return;
        }
        self::$claimStates[$uuid] = $currentFactionId;

        GlaciaUtils::handleClaimTitle($player, $claim);
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