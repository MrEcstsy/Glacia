<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\duels\DuelManager;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class DuelListener implements Listener
{
    /** @var array<string, int> */
    private array $arenaEntryMessageCooldowns = [];

    public function onCommand(CommandEvent $event): void
    {
        $sender = $event->getSender();
        if (!$sender instanceof Player || !DuelManager::isInitialized()) {
            return;
        }

        $raw = ltrim($event->getCommand(), "/ \t");
        $root = strtolower(explode(" ", $raw, 2)[0]);
        if (DuelManager::getInstance()->canUseCommand($sender, $root)) {
            return;
        }

        $event->cancel();
        $sender->sendMessage(C::colorize("&r&cThat command is disabled while you are in a duel."));
    }

    public function onMove(PlayerMoveEvent $event): void
    {
        if (!DuelManager::isInitialized() || $event->getTo() === null) {
            return;
        }

        $manager = DuelManager::getInstance();
        $player = $event->getPlayer();
        $to = $event->getTo();

        if ($manager->shouldKeepDuelerInside($player, $to)) {
            $event->cancel();
            $this->pushTowardArenaCenter($player, true);
            $player->sendActionBarMessage(C::colorize("&r&cYou cannot leave the duel arena."));
            return;
        }

        if ($manager->shouldBlockArenaEntry($player, $to)) {
            $event->cancel();
            $this->pushAwayFromArenaCenter($player);
            $player->sendActionBarMessage(C::colorize("&r&cYou can't enter the duel arena."));
            $this->sendArenaEntryDeniedMessage($player);
            return;
        }

        if ($manager->isInDuel($player) && ($player->getAllowFlight() || $player->isFlying())) {
            $player->setAllowFlight(false);
            $player->setFlying(false);
            $player->resetFallDistance();
        }
    }

    public function onTeleport(EntityTeleportEvent $event): void
    {
        if (!DuelManager::isInitialized()) {
            return;
        }

        $player = $event->getEntity();
        if (!$player instanceof Player) {
            return;
        }

        $manager = DuelManager::getInstance();
        $to = $event->getTo();
        if ($manager->shouldKeepDuelerInside($player, $to)) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cYou cannot teleport out of the duel arena."));
            return;
        }

        if ($manager->shouldBlockArenaEntry($player, $to)) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cYou can't enter the duel arena. Try &f/duel &cif you want to fight there."));
        }
    }

    public function onDamage(EntityDamageByEntityEvent $event): void
    {
        if (!DuelManager::isInitialized()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if ($damager instanceof Projectile) {
            $damager = $damager->getOwningEntity();
        }
        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $manager = DuelManager::getInstance();
        $damagerInDuel = $manager->isInDuel($damager);
        $victimInDuel = $manager->isInDuel($victim);
        if (!$damagerInDuel && !$victimInDuel) {
            return;
        }

        if (!$manager->isCombatLive()) {
            $event->cancel();
            if ($damagerInDuel) {
                $damager->sendActionBarMessage(C::colorize("&r&eDuel countdown in progress..."));
            }
            return;
        }

        $opponent = $manager->getOpponent($damager);
        if (!$damagerInDuel || !$victimInDuel || $opponent === null || !$opponent->getUniqueId()->equals($victim->getUniqueId())) {
            $event->cancel();
        }
    }

    public function onDeath(PlayerDeathEvent $event): void
    {
        if (!DuelManager::isInitialized()) {
            return;
        }

        $player = $event->getPlayer();
        if (!DuelManager::getInstance()->isInDuel($player)) {
            return;
        }

        $event->setKeepInventory(true);
        $event->setDrops([]);
        $event->setXpDropAmount(0);
        DuelManager::getInstance()->handlePlayerDeath($player);
    }

    public function onRespawn(PlayerRespawnEvent $event): void
    {
        if (!DuelManager::isInitialized()) {
            return;
        }

        DuelManager::getInstance()->handleRespawn($event->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        if (!DuelManager::isInitialized()) {
            return;
        }

        unset($this->arenaEntryMessageCooldowns[strtolower($event->getPlayer()->getUniqueId()->toString())]);
        DuelManager::getInstance()->handlePlayerQuit($event->getPlayer());
    }

    public function onDrop(PlayerDropItemEvent $event): void
    {
        if (!DuelManager::isInitialized()) {
            return;
        }

        if (!DuelManager::getInstance()->isInDuel($event->getPlayer())) {
            return;
        }

        $event->cancel();
        $event->getPlayer()->sendMessage(C::colorize("&r&cYou cannot drop items during a duel."));
    }

    private function pushTowardArenaCenter(Player $player, bool $addUpwardBoost): void
    {
        $center = DuelManager::getInstance()->getArenaCenter();
        if ($center === null) {
            return;
        }

        $dx = $center->x - $player->getLocation()->x;
        $dz = $center->z - $player->getLocation()->z;
        $distance = max(0.001, sqrt(($dx * $dx) + ($dz * $dz)));
        $player->setMotion(new Vector3(($dx / $distance) * 1.15, $addUpwardBoost ? 0.22 : 0.08, ($dz / $distance) * 1.15));
    }

    private function pushAwayFromArenaCenter(Player $player): void
    {
        $center = DuelManager::getInstance()->getArenaCenter();
        if ($center === null) {
            return;
        }

        $dx = $player->getLocation()->x - $center->x;
        $dz = $player->getLocation()->z - $center->z;
        $distance = max(0.001, sqrt(($dx * $dx) + ($dz * $dz)));
        $player->setMotion(new Vector3(($dx / $distance) * 0.95, 0.08, ($dz / $distance) * 0.95));
    }

    private function sendArenaEntryDeniedMessage(Player $player): void
    {
        $key = strtolower($player->getUniqueId()->toString());
        $now = time();
        if (($this->arenaEntryMessageCooldowns[$key] ?? 0) > $now) {
            return;
        }

        $this->arenaEntryMessageCooldowns[$key] = $now + 2;
        $player->sendMessage(C::colorize("&r&cYou can't enter this place. Try &f/duel &cif you want to fight there."));
    }
}
