<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\regions\RegionManager;
use pocketmine\block\Anvil;
use pocketmine\block\DragonEgg;
use pocketmine\block\tile\Beacon;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Hopper;
use pocketmine\block\Trapdoor;
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
use pocketmine\event\server\CommandEvent;
use pocketmine\item\FireCharge;
use pocketmine\item\FlintSteel;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class RegionListener implements Listener {

    private $playerRegions = [];

    private RegionManager $regionManager;

    /** @var string[] */
    private array $ignoredWorlds = [
        "flat"
    ];

    public function __construct(RegionManager $regionManager)
    {
        $this->regionManager = $regionManager;
    }

    public function onPlayerMove(PlayerMoveEvent $ev): void {
        $p = $ev->getPlayer();
        $region = $this->regionManager->getRegionAt($p->getPosition());
        $prev = $this->playerRegions[$p->getName()] ?? null;
        if ($region !== $prev) {
            if ($region !== null) {
                $p->sendActionBarMessage(C::colorize("§a>> Entered ".$region->getName()));
            } else {
                $p->sendActionBarMessage(C::colorize("§c>> Left region"));
            }
            $this->playerRegions[$p->getName()] = $region;
        }
    }

    public function onBlockBreak(BlockBreakEvent $ev): void {
        $p = $ev->getPlayer();

        if ($this->isIgnoredWorld($p->getWorld()->getFolderName())) {
            return;
        }

        $block = $ev->getBlock();
        $region = $this->regionManager->getRegionAt($block->getPosition());

        if (!$p->hasPermission("glacia.admin") && $region !== null && !$region->permissions()->canBreak) {
            $ev->cancel();
            $p->sendMessage(C::colorize("&r&l&cHey! &r&fYou can't do that here!"));
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event)
    {
        $player = $event->getPlayer();
        $region = $this->regionManager->getRegionAt($player->getPosition());

        if (!$player->hasPermission("glacia.admin")) {
            if ($region !== null && !$region->permissions()->build) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&l&cHey! &r&fYou can't do that here!"));
            }
        }
    }

    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event)
    {
        $player = $event->getDamager();

        if ($player instanceof Player) {
            $region = $this->regionManager->getRegionAt($player->getPosition());

            if (!$player->hasPermission("glacia.admin")) {
                if ($region !== null && !$region->permissions()->pvp) {
                    $event->cancel();
                    $player->sendMessage(C::colorize("&r&l&cHey! &r&fYou can't do that here!"));
                }
            }
        }
    }

    public function onDamage(EntityDamageEvent $event)
    {
        $player = $event->getEntity();

        if ($player instanceof Player) {
            $region = $this->regionManager->getRegionAt($player->getPosition());

            if ($region !== null && !$region->permissions()->fallDamage) {
                if ($event->getCause() === EntityDamageEvent::CAUSE_FALL) {
                    $event->cancel();
                }
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();

        if ($this->isIgnoredWorld($player->getWorld()->getFolderName())) {
            return;
        }

        $block = $event->getBlock();
        $item = $event->getItem();

        $region = $this->regionManager->getRegionAt($player->getPosition());

        if ($region !== null && !$region->permissions()->interact) {
            if ($block instanceof DragonEgg || $block instanceof Chest || $block instanceof Hopper || $block instanceof Beacon || $block instanceof Anvil || $item instanceof FlintSteel || $item instanceof FireCharge || $item instanceof Trapdoor) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&l&cHey! &r&fYou can't do that here!"));
            }
        }
    }

    public function onEntityExplode(EntityExplodeEvent $event): void {
        $worldName = $event->getPosition()->getWorld()->getFolderName();

        if ($this->isIgnoredWorld($worldName)) {
            return;
        }

        $region = $this->regionManager->getRegionAt($event->getPosition());

        if ($region !== null && !$region->permissions()->explosions) {
            $event->cancel();
        }
    }

    public function onExplosion(EntityPreExplodeEvent $event): void {
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

        if ($player instanceof Player) {
            $region = $this->regionManager->getRegionAt($player->getPosition());

            if ($region !== null && !$region->permissions()->interact) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&l&cHey! &r&fYou can't do that here!"));
            }
        }
    }

    public function useBucketEmpty(PlayerBucketEmptyEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player instanceof Player) {
            $region = $this->regionManager->getRegionAt($player->getPosition());

            if ($region !== null && !$region->permissions()->interact) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&l&cHey! &r&fYou can't do that here!"));
            }
        }
    }

    public function onCommandEvent(CommandEvent $event): void
    {
        $sender = $event->getSender();
        if (!$sender instanceof Player) {
            return;
        }
        $command = strtolower($event->getCommand());
        if ($command === "f claim") {
            $region = $this->regionManager->getRegionAt($sender->getPosition());
            if ($region !== null) {
                $event->cancel();
                $sender->sendMessage(C::colorize("&r&l&cHey! &r&fYou can't claim here!"));
            }
        }
    }

    private function isIgnoredWorld(string $worldName): bool {
        return in_array(strtolower($worldName), $this->ignoredWorlds, true);
    }
}