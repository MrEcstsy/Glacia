<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\regions\RegionManager;
use pocketmine\block\Anvil;
use pocketmine\block\DragonEgg;
use pocketmine\block\Trapdoor;
use pocketmine\block\tile\Beacon;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Hopper;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\item\FireCharge;
use pocketmine\item\FlintSteel;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class RegionListener implements Listener {

    /** @var array<string, string|null> */
    private array $playerRegions = [];
    /** @var array<string, int> */
    private array $denyMessageCooldowns = [];

    private RegionManager $regionManager;

    /** @var string[] */
    private array $ignoredWorlds = [
        "flat"
    ];

    public function __construct(RegionManager $regionManager)
    {
        $this->regionManager = $regionManager;
    }

    public function onPlayerMove(PlayerMoveEvent $event): void
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
        $playerKey = strtolower($player->getName());
        $regionName = $this->regionManager->getRegionAt($to)?->getName();
        $previousRegionName = $this->playerRegions[$playerKey] ?? null;

        if ($regionName === $previousRegionName) {
            return;
        }

        if ($regionName !== null) {
            $player->sendActionBarMessage(C::colorize("&r&a>> Entered &f" . $regionName));
        } elseif ($previousRegionName !== null) {
            $player->sendActionBarMessage(C::colorize("&r&c>> Left &f" . $previousRegionName));
        }

        $this->playerRegions[$playerKey] = $regionName;
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $playerKey = strtolower($event->getPlayer()->getName());
        unset($this->playerRegions[$playerKey], $this->denyMessageCooldowns[$playerKey]);
    }

    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();

        if ($this->isIgnoredWorld($player->getWorld()->getFolderName())) {
            return;
        }

        $region = $this->regionManager->getRegionAt($event->getBlock()->getPosition());
        if (!$player->hasPermission("glacia.admin") && $region !== null && !$region->permissions()->canBreak) {
            $event->cancel();
            $this->sendDeniedMessage($player);
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();
        if ($player->hasPermission("glacia.admin")) {
            return;
        }

        $region = $this->regionManager->getRegionAt($player->getPosition());
        if ($region !== null && !$region->permissions()->build) {
            $event->cancel();
            $this->sendDeniedMessage($player);
        }
    }

    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void
    {
        $player = $event->getDamager();
        if (!$player instanceof Player || $player->hasPermission("glacia.admin")) {
            return;
        }

        $region = $this->regionManager->getRegionAt($player->getPosition());
        if ($region !== null && !$region->permissions()->pvp) {
            $event->cancel();
            $this->sendDeniedMessage($player);
        }
    }

    public function onDamage(EntityDamageEvent $event): void
    {
        $player = $event->getEntity();
        if (!$player instanceof Player) {
            return;
        }

        $region = $this->regionManager->getRegionAt($player->getPosition());
        if ($region !== null && !$region->permissions()->fallDamage && $event->getCause() === EntityDamageEvent::CAUSE_FALL) {
            $event->cancel();
        }
    }

    public function onInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();

        if ($this->isIgnoredWorld($player->getWorld()->getFolderName())) {
            return;
        }

        $region = $this->regionManager->getRegionAt($player->getPosition());
        if ($region === null || $region->permissions()->interact) {
            return;
        }

        $block = $event->getBlock();
        $item = $event->getItem();
        if (
            $block instanceof DragonEgg ||
            $block instanceof Chest ||
            $block instanceof Hopper ||
            $block instanceof Beacon ||
            $block instanceof Anvil ||
            $block instanceof Trapdoor ||
            $item instanceof FlintSteel ||
            $item instanceof FireCharge
        ) {
            $event->cancel();
            $this->sendDeniedMessage($player);
        }
    }

    public function onEntityExplode(EntityExplodeEvent $event): void
    {
        $worldName = $event->getPosition()->getWorld()->getFolderName();
        if ($this->isIgnoredWorld($worldName)) {
            return;
        }

        $region = $this->regionManager->getRegionAt($event->getPosition());
        if ($region !== null && !$region->permissions()->explosions) {
            $event->cancel();
        }
    }

    public function onExplosion(EntityPreExplodeEvent $event): void
    {
        $worldName = $event->getEntity()->getWorld()->getFolderName();
        if ($this->isIgnoredWorld($worldName)) {
            return;
        }

        $region = $this->regionManager->getRegionAt($event->getEntity()->getPosition());
        if ($region !== null && !$region->permissions()->explosions) {
            $event->cancel();
            $event->setBlockBreaking(false);
        }
    }

    public function useBucket(PlayerBucketFillEvent $event): void
    {
        $player = $event->getPlayer();
        if ($player->hasPermission("glacia.admin")) {
            return;
        }

        $region = $this->regionManager->getRegionAt($player->getPosition());

        if ($region !== null && !$region->permissions()->interact) {
            $event->cancel();
            $this->sendDeniedMessage($player);
        }
    }

    public function useBucketEmpty(PlayerBucketEmptyEvent $event): void
    {
        $player = $event->getPlayer();
        if ($player->hasPermission("glacia.admin")) {
            return;
        }

        $region = $this->regionManager->getRegionAt($player->getPosition());

        if ($region !== null && !$region->permissions()->interact) {
            $event->cancel();
            $this->sendDeniedMessage($player);
        }
    }

    public function onCommandEvent(CommandEvent $event): void
    {
        $sender = $event->getSender();
        if (!$sender instanceof Player) {
            return;
        }

        $command = strtolower(trim($event->getCommand()));
        if ($command === "f claim" || str_starts_with($command, "f claim ")) {
            $region = $this->regionManager->getRegionAt($sender->getPosition());
            if ($region !== null) {
                $event->cancel();
                $sender->sendMessage(C::colorize("&r&cYou can't claim in this region."));
            }
        }
    }

    private function isIgnoredWorld(string $worldName): bool
    {
        return in_array(strtolower($worldName), $this->ignoredWorlds, true);
    }

    private function sendDeniedMessage(Player $player): void
    {
        $key = strtolower($player->getName());
        $now = time();
        if (($this->denyMessageCooldowns[$key] ?? 0) > $now) {
            return;
        }

        $this->denyMessageCooldowns[$key] = $now + 1;
        $player->sendMessage(C::colorize("&r&cYou can't do that here."));
    }
}
