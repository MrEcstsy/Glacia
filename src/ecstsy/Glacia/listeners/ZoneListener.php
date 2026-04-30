<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\scoreboard\ScoreboardHelper;
use ecstsy\Glacia\server\world\theme\DimensionThemeManager;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\server\zones\ZoneMobManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ZoneListener implements Listener {

    /** @var array<string, string|null> */
    private array $playerZones = [];
    /** @var array<string, int> */
    private array $denyMessageCooldowns = [];
    /** @var array<string, bool> */
    private array $portalExitActive = [];

    public function onPlayerMove(PlayerMoveEvent $event) : void
    {
        $from = $event->getFrom();
        $to = $event->getTo();
        if (
            $to === null ||
            (
                $from->getFloorX() === $to->getFloorX() &&
                $from->getFloorY() === $to->getFloorY() &&
                $from->getFloorZ() === $to->getFloorZ() &&
                $from->getWorld()->getFolderName() === $to->getWorld()->getFolderName()
            )
        ) {
            return;
        }

        $player = $event->getPlayer();
        $fromZone = ZoneManager::getZoneAt($from);
        $toZone = ZoneManager::getZoneAt($to);

        if ($fromZone !== null && ($toZone["id"] ?? null) !== ($fromZone["id"] ?? null)) {
            $event->cancel();
            $this->sendBoundaryMessage($player, $fromZone);
            return;
        }

        if ($toZone !== null && ($fromZone["id"] ?? null) !== ($toZone["id"] ?? null) && !ZoneManager::canPlayerAccess($player, $toZone)) {
            $event->cancel();
            $this->sendLockedMessage($player, $toZone);
            return;
        }

        $this->syncZoneState($player, $toZone);
        $this->syncZoneFlight($player, $toZone);
        $this->handlePortalExitArea($player, $toZone);
    }

    public function onTeleport(EntityTeleportEvent $event) : void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }

        $skipPortalExitConsume = false;
        $fromWorldName = strtolower($event->getFrom()->getWorld()->getFolderName());
        $toWorldName = strtolower($event->getTo()->getWorld()->getFolderName());

        $endWorlds = ["end", "world_the_end"];

        // In the vanilla-like "end" dimension, the only valid exit is the configured Astral Gate portal.
        // Block /tp, pearls, and other teleports that change worlds unless they were triggered by the portal.
        if (in_array($fromWorldName, $endWorlds, true) && !in_array($toWorldName, $endWorlds, true)) {
            if ($entity->hasPermission("glacia.staff")) {
                // Staff handled by the bypass above; let them through.
            } else {
                if (ZoneManager::consumePortalExitBypass($entity)) {
                    $skipPortalExitConsume = true; // already consumed; don't consume again below
                } else {
                    $event->cancel();
                    $entity->sendMessage(C::colorize("&r&cYou can only leave the End dimension via the Astral Gate portal."));
                    PlayerUtils::playSound($entity, "note.bass");
                    return;
                }
            }
        }

        if ($entity->hasPermission("glacia.staff")) {
            unset($this->portalExitActive[$entity->getUniqueId()->toString()]);
            $this->syncZoneState($entity, ZoneManager::getZoneAt($event->getTo()));
            return;
        }

        $fromZone = ZoneManager::getZoneAt($event->getFrom());
        $toZone = ZoneManager::getZoneAt($event->getTo());
        if ($fromZone !== null && ($toZone["id"] ?? null) !== ($fromZone["id"] ?? null)) {
            if (ZoneManager::hasPortalOnlyExit($fromZone)) {
                if (!$skipPortalExitConsume && !ZoneManager::consumePortalExitBypass($entity)) {
                    $event->cancel();
                    $this->sendBoundaryMessage($entity, $fromZone, "&r&5Astral Veil can only be left through the exit portal.");
                    return;
                }
            } elseif (!ZoneManager::consumeBoundaryCrossingBypass($entity)) {
                $event->cancel();
                $this->sendBoundaryMessage($entity, $fromZone);
                return;
            }
        }

        if ($toZone !== null && !ZoneManager::canPlayerAccess($entity, $toZone)) {
            $event->cancel();
            $this->sendLockedMessage($entity, $toZone);
            return;
        }

        unset($this->portalExitActive[$entity->getUniqueId()->toString()]);
        $this->syncZoneState($entity, $toZone);
        $this->syncZoneFlight($entity, $toZone);
    }

    public function onZoneWandUse(PlayerInteractEvent $event) : void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        if (!$player->hasPermission("glacia.admin") || !ZoneManager::isBoundaryWand($item)) {
            return;
        }

        $zoneId = ZoneManager::getBoundaryWandZoneId($item) ?? ZoneManager::getBoundaryEditZone($player);
        if ($zoneId === null) {
            return;
        }

        $point = match ($event->getAction()) {
            PlayerInteractEvent::LEFT_CLICK_BLOCK => "pos1",
            PlayerInteractEvent::RIGHT_CLICK_BLOCK => "pos2",
            default => null,
        };
        if ($point === null) {
            return;
        }

        $event->cancel();
        $event->setUseBlock(false);
        $event->setUseItem(false);
        $position = $event->getBlock()->getPosition();
        if (ZoneManager::getBoundaryWandMode($item) === "safe") {
            $safeId = ZoneManager::getBoundaryWandSafeId($item);
            if ($safeId === null || !ZoneManager::setSafeAreaPoint($zoneId, $safeId, $position, $point)) {
                $player->sendMessage(C::colorize("&r&cFailed to save {$point} for safe area in &f{$zoneId}&c."));
                return;
            }

            $zone = ZoneManager::getZone($zoneId);
            $player->sendMessage(C::colorize(
                "&r&aSet safe area &f{$safeId}&a in &f" . (string) ($zone["display"] ?? $zoneId) . " &a{$point} to &f" .
                $position->getWorld()->getFolderName() . " {$position->getFloorX()}, {$position->getFloorY()}, {$position->getFloorZ()}&a."
            ));
            return;
        }

        if (!ZoneManager::setBoundaryPoint($zoneId, $position, $point)) {
            $player->sendMessage(C::colorize("&r&cFailed to save {$point} for zone &f{$zoneId}&c."));
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        $player->sendMessage(C::colorize(
            "&r&aSet &f" . (string) ($zone["display"] ?? $zoneId) . " &a{$point} to &f" .
            $position->getWorld()->getFolderName() . " {$position->getFloorX()}, {$position->getFloorY()}, {$position->getFloorZ()}&a."
        ));

        $note = ZoneManager::getLastBoundaryEditNote();
        if ($note !== null) {
            $player->sendMessage(C::colorize($note));
        }
    }

    public function onItemUse(PlayerItemUseEvent $event) : void
    {
        $player = $event->getPlayer();
        if ($event->getItem()->getTypeId() !== ItemTypeIds::ENDER_PEARL) {
            return;
        }

        $zone = ZoneManager::getZoneAt($player->getPosition());

        // Allow pearls in the End dimension so players can save themselves.
        if (
            in_array(strtolower($player->getWorld()->getFolderName()), ["end", "world_the_end"], true) ||
            (($zone["id"] ?? null) === "endfall")
        ) {
            return;
        }

        // Everywhere else: pearls are blocked inside zones.
        if ($zone === null) {
            return;
        }

        $event->cancel();
        $this->sendBoundaryMessage($player, $zone, "&r&cEnder pearls are disabled inside zones.");
    }

    public function onBlockBreak(BlockBreakEvent $event) : void
    {
        $player = $event->getPlayer();
        if ($player->hasPermission("glacia.admin")) {
            return;
        }

        $zone = ZoneManager::getZoneAt($event->getBlock()->getPosition());
        if ($zone === null) {
            return;
        }

        if (ZoneManager::shouldRegenerateOre($zone, $event->getBlock())) {
            return;
        }

        $event->cancel();
        $this->sendBoundaryMessage($player, $zone, "&r&cYou cannot break blocks in &f" . (string) $zone["display"] . "&r&c.");
    }

    public function onBlockPlace(BlockPlaceEvent $event) : void
    {
        $player = $event->getPlayer();
        if ($player->hasPermission("glacia.admin")) {
            return;
        }

        $zone = ZoneManager::getZoneAt($player->getPosition());
        if ($zone === null) {
            return;
        }

        $event->cancel();
        $this->sendBoundaryMessage($player, $zone, "&r&cYou cannot place blocks in &f" . (string) $zone["display"] . "&r&c.");
    }

    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event) : void
    {
        $victim = $event->getEntity();
        $attacker = $event->getDamager();
        if (!$victim instanceof Player || !$attacker instanceof Player) {
            return;
        }

        $zone = ZoneManager::getZoneAt($victim->getPosition()) ?? ZoneManager::getZoneAt($attacker->getPosition());
        if ($zone === null) {
            return;
        }

        $safePocket = ZoneManager::isInsideSafeArea($victim->getPosition(), $zone) || ZoneManager::isInsideSafeArea($attacker->getPosition(), $zone);
        if (ZoneManager::isPvpEnabled($zone) && !$safePocket) {
            return;
        }

        $event->cancel();
        $message = $safePocket
            ? "&r&cPvP is disabled in this &fsafe pocket&r&c."
            : "&r&cPvP is disabled in &f" . (string) $zone["display"] . "&r&c.";
        $this->sendBoundaryMessage($attacker, $zone, $message);
    }

    /**
     * @priority HIGHEST
     */
    public function onZoneWandBreak(BlockBreakEvent $event) : void
    {
        if ($event->getPlayer()->hasPermission("glacia.admin") && ZoneManager::isBoundaryWand($event->getItem())) {
            $event->cancel();
        }
    }

    /**
     * @priority MONITOR
     */
    public function onZoneOreBreak(BlockBreakEvent $event) : void
    {
        if ($event->isCancelled()) {
            return;
        }

        $zone = ZoneManager::getZoneAt($event->getBlock()->getPosition());
        if ($zone === null || !ZoneManager::shouldRegenerateOre($zone, $event->getBlock())) {
            return;
        }

        ZoneManager::queueOreRespawn($event->getBlock()->getPosition(), $event->getBlock(), $zone);
    }

    public function onQuit(PlayerQuitEvent $event) : void
    {
        $key = $event->getPlayer()->getUniqueId()->toString();
        unset($this->playerZones[$key], $this->denyMessageCooldowns[$key], $this->portalExitActive[$key]);
        ZoneManager::endBoundaryEdit($event->getPlayer());
        ZoneMobManager::clearPlayerState($event->getPlayer());
        DimensionThemeManager::clearPlayerState($event->getPlayer());
    }

    private function handlePortalExitArea(Player $player, ?array $zone) : void
    {
        $key = $player->getUniqueId()->toString();
        if ($zone === null || !ZoneManager::hasPortalOnlyExit($zone) || ZoneManager::canBypassExitRestrictions($player, $zone)) {
            unset($this->portalExitActive[$key]);
            return;
        }

        if (!ZoneManager::isInsideExitPortal($player->getPosition(), $zone)) {
            unset($this->portalExitActive[$key]);
            return;
        }

        if (($this->portalExitActive[$key] ?? false) === true) {
            return;
        }

        $destination = ZoneManager::getExitDestination($zone);
        if ($destination === null) {
            $this->sendBoundaryMessage($player, $zone, "&r&cThis exit portal is not configured yet.");
            return;
        }

        $this->portalExitActive[$key] = true;
        GlaciaUtils::timedTeleport(
            $player,
            $destination,
            (int) (($zone["exit"]["portal"]["teleport_seconds"] ?? 8)),
            function(Player $cancelled) use ($key): void {
                ZoneManager::clearPortalExitBypass($cancelled);
                $cancelled->sendMessage(C::colorize(
                    "&r&cAstral Gate channel cancelled. Step fully out of the gate and back in to restart.\n" .
                    "&r&7(If you move too much while channeling, it cancels.)"
                ));
            },
            function(Player $teleporting) use ($key, $zone): void {
                ZoneManager::allowNextPortalExit($teleporting, ZoneManager::getExitPortalDelaySeconds($teleporting, $zone) + 3);
                unset($this->portalExitActive[$key]);
            }
        );
        $player->sendMessage(C::colorize(
            "&r&dAstral Gate &8| &r&7Only exit is the portal (or DEATH).\n" .
            "&r&7Heroic enchantments are disabled during this escape attempt."
        ));
    }

    private function syncZoneFlight(Player $player, ?array $zone) : void
    {
        if (!$player->isSurvival()) {
            return;
        }

        if ($zone !== null && !ZoneManager::allowsFlight($zone) && !$player->hasPermission("glacia.staff")) {
            if ($player->isFlying()) {
                $player->setFlying(false);
                $player->resetFallDistance();
            }
            if ($player->getAllowFlight()) {
                $player->setAllowFlight(false);
            }
            return;
        }

        FactionListener::syncClaimFlight($player);
    }

    private function syncZoneState(Player $player, ?array $zone) : void
    {
        $key = $player->getUniqueId()->toString();
        $zoneId = $zone["id"] ?? null;
        if (($this->playerZones[$key] ?? null) === $zoneId) {
            return;
        }

        $this->playerZones[$key] = $zoneId;
        ZoneMobManager::handleZoneState($player, $zoneId);
        ScoreboardHelper::refreshScoreboard($player);
        if ($zone !== null) {
            $player->sendActionBarMessage(C::colorize("&r&a>> Entered &f" . (string) $zone["display"]));
        }
    }

    private function sendLockedMessage(Player $player, array $zone) : void
    {
        $key = $player->getUniqueId()->toString();
        $now = time();
        if (($this->denyMessageCooldowns[$key] ?? 0) > $now) {
            return;
        }

        $this->denyMessageCooldowns[$key] = $now + 2;
        $session = Loader::getPlayerManager()->getSession($player);
        $level = $session?->getLevel() ?? 0;
        $player->sendMessage(C::colorize(
            "&r&c" . (string) $zone["display"] . " &cis locked. Required level: &f" .
            number_format(ZoneManager::getUnlockRequirement($zone)) . "&c. Your level: &f" . number_format($level) . "&c."
        ));
        PlayerUtils::playSound($player, "note.bass");
    }

    private function sendBoundaryMessage(Player $player, array $zone, ?string $message = null) : void
    {
        $key = $player->getUniqueId()->toString() . ":boundary";
        $now = time();
        if (($this->denyMessageCooldowns[$key] ?? 0) > $now) {
            return;
        }

        $this->denyMessageCooldowns[$key] = $now + 2;
        $player->sendActionBarMessage(C::colorize($message ?? "&r&cYou cannot leave &f" . (string) $zone["display"] . "&r&c from here."));
        PlayerUtils::playSound($player, "note.bass");
    }
}
