<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\blocks\CustomSpawner;
use ecstsy\Glacia\blocks\tiles\SpawnerTile;
use ecstsy\Glacia\entity\CombatLoggerEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\managers\BragTracker;
use ecstsy\Glacia\managers\ChatItemTracker;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\GraceInstance;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\items\GlaciaSpawnerItem;
use ecstsy\Glacia\server\player\BackLocationManager;
use ecstsy\Glacia\server\hats\HatPlayer;
use ecstsy\Glacia\server\items\rename\ItemRenameManager;
use ecstsy\Glacia\server\items\trackers\TrackerManager;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\server\scoreboard\ScoreboardHelper;
use ecstsy\Glacia\server\spawners\SpawnerData;
use ecstsy\Glacia\utils\ChatTypes;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use ecstsy\Glacia\utils\screens\spawners\SpawnerStackScreen;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\MonsterSpawner as BlockMonsterSpawner;
use pocketmine\block\tile\MonsterSpawner;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\ItemBlock;
use pocketmine\item\Durable;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\Pickaxe;
use pocketmine\item\StringToItemParser;
use pocketmine\entity\object\FireworkRocket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\sound\XpCollectSound;
use Yanoox\ScoreBoardAPI;

final class GlaciaListener implements Listener {

    private array $blockedDotSlashCmds = [
        "./f disband",
        "./faction disband",
        "./factions disband",
        "./cf",
        "./coinflip",
          "./settings",
          "./glaciasettings",
          "./f upgrades",
          "./faction upgrades",
          "./factions upgrades",
          "./f upgrade",
          "./faction upgrade",
          "./factions upgrade",
          "./ah sell",
          "./auctionhouse sell",
      ];

    /** @var Player[] */
    private array $online = [];

    private array $cooldowns = [];
    private const GAP_LIMIT = 3;
    private const GAP_WINDOW = 20; // seconds allowed between apples
    public static array $lastMessages = [];
    public static array $lastMessageTime = [];

    public function onPlayerCreation(PlayerCreationEvent $event): void {
        $event->setPlayerClass(HatPlayer::class);
    }

    public function onLogin(PlayerLoginEvent $event): void {
        $player = $event->getPlayer();

        if (PlayerManager::getInstance()->getSession($player) === null) {
            PlayerManager::getInstance()->createSession($player);
        }
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $nametag = str_replace("\n", " ", $player->getNameTag());
        $this->online[$player->getUniqueId()->toString()] = $player;

        $session = Loader::getPlayerManager()->getSession($player);

        $session->setConnected(true);

        $attachment = $player->addAttachment(Loader::getInstance());
        RankManager::getInstance()->setAttachment($player, $attachment);
        RankManager::getInstance()->updatePermission($player);

        GlaciaUtils::refreshNametagsForTarget($player);
        GlaciaUtils::updateScoreTag($player);
        ScoreboardHelper::initScoreboard($player);
        FactionListener::syncClaimFlight($player);

        $combatManager = Loader::getCombatManager();
        $combatManager->register($player);

        if ($combatManager->hasCombatEntry($player)) {
            $combatManager->handleLogin($player);
        }

        $event->setJoinMessage(C::colorize("&r&8[&a+&8] &r&8" . $player->getName()));

        foreach (MessageTemplates::$joinMessages as $line) {
            $player->sendMessage(C::colorize(str_replace(["{player}", "{nametag}", "{online}", "{pingcolor}", "{ping}", "{networkbar}"], [$player->getName(), $nametag, count($this->online), GlaciaUtils::getPingColor($player->getNetworkSession()->getPing()), $player->getNetworkSession()->getPing(), GlaciaUtils::getNetworkBar($player->getNetworkSession()->getPing())], $line)));
        }
    }

    public function onLeave(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        unset($this->online[$player->getUniqueId()->toString()]);
        ItemRenameManager::abort($player);

        PlayerManager::getInstance()->getSession($player)?->setConnected(false);

        $combatManager = Loader::getCombatManager();

        if ($combatManager->isInCombat($player)) {
            $combatManager->handleLogout($player);
        }

        $combatManager->unregister($player);

        if (ScoreBoardAPI::hasScore($player)) {
            ScoreBoardAPI::removeScore($player);
        }

        $event->setQuitMessage("");
    }

    /**
     * @priority HIGH
     */
    public function onPlayerChat(PlayerChatEvent $event): void {
        $event->cancel();

        $sender = $event->getPlayer();
        $message = $event->getMessage();

        if (ItemRenameManager::handleChat($sender, $message)) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null) {
            return;
        }

        $chatType = $session->getCurrentChat();

        $rank = $session->getRankObject();
        if ($rank === null) {
            $sender->sendMessage(C::colorize("&r&l&cERROR: &r&7You have no rank assigned, contact an &cADMIN"));
            return;
        }

        $globalFormat = $rank->getChatFormat();
        if (trim($globalFormat) === '') {
            $sender->sendMessage(C::colorize("&r&l&cERROR: &r&7Your rank does not have a chat format, contact an &cADMIN"));
            return;
        }

        $senderFac = $session->getFaction();
        $factionRole = $session->getFactionRole();
        $roleSymbol = GlaciaUtils::getFactionRoleSymbol($factionRole);

        $format = $globalFormat;
        $isGlobal = true;
        $filterMode = ChatTypes::ALL;

        switch ($chatType) {
            case ChatTypes::FACTION:
                $format = ChatTypes::FORMAT_FACTION;
                $filterMode = ChatTypes::FACTION;
                $isGlobal = false;
                break;
            case ChatTypes::ALLY:
                $format = ChatTypes::FORMAT_ALLY;
                $filterMode = ChatTypes::ALLY;
                $isGlobal = false;
                break;
            case ChatTypes::STAFF:
                $format = ChatTypes::FORMAT_STAFF;
                $filterMode = ChatTypes::STAFF;
                $isGlobal = false;
                break;
        }

        foreach ($this->online as $recipient) {
            $recSes = Loader::getPlayerManager()->getSession($recipient);
            if ($recSes === null) continue;

            $recFac = $recSes->getFaction();

            if (!$isGlobal) {
                if ($filterMode === ChatTypes::FACTION) {
                    if ($senderFac === null || $recFac === null || $recFac->getFactionId() !== $senderFac->getFactionId()) {
                        continue;
                    }
                }

                if ($filterMode === ChatTypes::ALLY) {
                    if ($senderFac === null || $recFac === null) {
                        continue;
                    }
                    $rel = RelationManager::getInstance()->getRelation($senderFac->getFactionId(), $recFac->getFactionId());

                    if ($rel !== 'ally' && $recipient !== $sender) {
                        continue;
                    }
                }
            }

            $relation = 'neutral';
            if ($senderFac !== null && $recFac !== null) {
                if ($senderFac->getFactionId() === $recFac->getFactionId()) {
                    $relation = 'own'; 
                } elseif ($recipient === $sender) {
                    $relation = 'own'; 
                } else {
                    $relation = RelationManager::getInstance()->getRelation($senderFac->getFactionId(), $recFac->getFactionId());
                }
            }
            
            $replacements = [
                '{fac_relation}' => GlaciaUtils::getRelationColor($relation),
                '{fac_rank}' => $roleSymbol ?: '',
                '{fac_name}' => $senderFac?->getName() ?: '',
                '{name}' => $sender->getName(),
                '{message}' => $message
            ];

            $out = str_replace(array_keys($replacements), array_values($replacements), $format);
            $recipient->sendMessage(C::colorize($out));
        }
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
        $packet = $event->getPacket();
        if (!$packet instanceof TextPacket) {
            return;
        }

        if ($packet->type !== TextPacket::TYPE_CHAT) {
            return;
        }

        $msg = strtolower(trim($packet->message));
        foreach ($this->blockedDotSlashCmds as $bad) {
            if (str_starts_with($msg, $bad)) {
                $event->cancel();

                $origin = $event->getOrigin(); 
                $player = $origin->getPlayer();
                if ($player instanceof Player) {
                    $correct = substr($bad, 1);
                    $player->sendMessage(C::colorize("&r&eYou can't use './' with that command. Try '" . $correct . "' instead."));
                }
                return;
            }
        }
    }

    public function onPlayerEmbed(PlayerChatEvent $event): void {
        $message = $event->getMessage();
        $player = $event->getPlayer();

        if (str_contains($message, '[brag]')) {
            $message = str_replace(
                '[brag]',
                C::colorize("&r&f[&d{$player->getName()}&f's Inventory]"),
                $message
            );
            BragTracker::setLastInventoryBrag($player);
        }

        if (str_contains($message, '[item]')) {
            $item = $player->getInventory()->getItemInHand();
            $replacement = $item->isNull() || $item->getCount() === 0
                ? C::colorize("&r&f[Air]")
                : C::colorize("&r&f[" . ($item->getCount() > 1 ? "&d" : "&f") . "{$item->getName()}" . ($item->getCount() > 1 ? " x{$item->getCount()}" : "") . "&f]");
            $message = str_replace('[item]', $replacement, $message);
            ChatItemTracker::setLastItem($player, $item);
        }

        $event->setMessage($message);
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $player = $event->getEntity();
        
        if (!$player instanceof Player) {
            return;
        }   

        GlaciaUtils::updateScoreTag($player);
    }

    public function onEntityRegainHealth(EntityRegainHealthEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof Player) {
            return;
        }
        
        GlaciaUtils::updateScoreTag($entity);
    }

    public function onCommand(CommandEvent $event): void {
        $raw = ltrim($event->getCommand(), "/ \t");
        $root = strtolower(explode(" ", $raw, 2)[0]);

        if (GraceInstance::getInstance()->isGraceActive()) {
            $graceBlocked = ["wild"];
            foreach ($graceBlocked as $blocked) {
                if ($root === $blocked || str_starts_with($root, $blocked)) {
                    $event->cancel();
                    $event->getSender()->sendMessage(C::colorize("&cYou cannot use this command during the grace period."));
                    return;
                }
            }
        }

        $sender = $event->getSender();
        if (!$sender instanceof Player) return;

        if (Loader::getCombatManager()->isInCombat($sender)) {
            $combatBlockedExact = ["tpa", "spawn", "home", "warp"];
            $combatBlockedPrefix = ["wild"];

            foreach ($combatBlockedExact as $blocked) {
                if ($root === $blocked) {
                    $sender->sendMessage(C::colorize("&r&l&c(!) &r&cYou cannot use this command while in combat!"));
                    $event->cancel();
                    return;
                }
            }

            foreach ($combatBlockedPrefix as $blocked) {
                if ($root === $blocked || str_starts_with($root, $blocked)) {
                    $sender->sendMessage(C::colorize("&r&l&c(!) &r&cYou cannot use this command while in combat!"));
                    $event->cancel();
                    return;
                }
            }
        }
    }

    public function onEntityExplode(EntityExplodeEvent $event): void {
        if (GraceInstance::getInstance()->isGraceActive()) {
            $event->cancel();
            foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                $player->sendToastNotification("Glacia Factions", "§bExplosions are disabled during grace period!");
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void {
        if ($event->isCancelled()) return;

        $player = $event->getEntity();
        $damager = $event->getDamager();

        if ($damager instanceof FireworkRocket) {
            $event->cancel();
            return;
        }
        
        if (!($player instanceof Player) || !($damager instanceof Player)) return;

        $combatManager = Loader::getCombatManager();
        $combatManager->startCombat($player, $damager);

        if ($damager instanceof Player && $damager->isFlying()) {
            GlaciaUtils::toggleFlight($damager, true);
        }
    
        if ($player instanceof Player && $player->isFlying()) {
            GlaciaUtils::toggleFlight($player, true);
        }

        if ($damager->isSprinting()) {
            $event->setKnockBack(0.6);
            $event->setVerticalKnockBackLimit($player->isOnGround() ? 0.44 : 0.5);
        } else {
            $event->setKnockBack(0.4);
            $event->setVerticalKnockBackLimit($player->isOnGround() ? 0.36 : 0.8);
        }

        if ($damager instanceof Player && $player instanceof CombatLoggerEntity) {
            Loader::getCombatManager()->startCombat($damager);
            return;
        }

          if ($damager instanceof CombatLoggerEntity && $player instanceof Player) {
              Loader::getCombatManager()->startCombat($player);
              return;
          }

          if ($damager instanceof Player && $player instanceof Player && PetManager::isShatterfangActive($damager)) {
              $extraDamage = max(1, PetManager::getInt("shatterfang", "extra-durability-damage", 1));
              $armorInventory = $player->getArmorInventory();
              foreach ($armorInventory->getContents() as $slot => $armorPiece) {
                  if (!$armorPiece instanceof Durable || $armorPiece->isNull()) {
                      continue;
                  }

                  $armorPiece->setDamage(min($armorPiece->getMaxDurability(), $armorPiece->getDamage() + $extraDamage));
                  $armorInventory->setItem($slot, $armorPiece);
              }
          }
      }

    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        BackLocationManager::store($player, $player->getLocation());
        $xpDrop = $player->getXpDropAmount();
        $xpBottle = GlaciaItemFactory::xpNote($player, $xpDrop);

        if ($player->getXpManager()->getCurrentTotalXp() > 0) {
            $player->getWorld()->dropItem($player->getPosition(), $xpBottle);
            $event->setXpDropAmount(0);
        } elseif ($player->getXpManager()->getCurrentTotalXp() === 0) {
            // continue to bounty payout handling below
        }

        $cause = $player->getLastDamageCause();
        if (!$cause instanceof EntityDamageByEntityEvent) {
            return;
        }

        $killer = $cause->getDamager();
        if (!$killer instanceof Player || $killer->getUniqueId()->toString() === $player->getUniqueId()->toString()) {
            return;
        }

        $victimSession = Loader::getPlayerManager()->getSession($player);
        $killerSession = Loader::getPlayerManager()->getSession($killer);
        if ($victimSession === null || $killerSession === null) {
            return;
        }

        $bounty = Loader::getBountyManager()->claimBounty($victimSession);
        if ($bounty <= 0) {
            return;
        }

        $killerSession->addBalance($bounty);
        $killer->sendMessage(C::colorize("&r&6Bounty &8| &r&aYou claimed &f$" . number_format($bounty) . " &afor killing &c" . $player->getName() . "&a."));
        Loader::getBountyManager()->broadcastClaim($killer->getName(), $player->getName(), $bounty);
    }

    public function onTeleport(EntityTeleportEvent $event): void {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }

        BackLocationManager::store($entity, $event->getFrom());
    }

    public function onSpawnerPlace(BlockPlaceEvent $event) : void {
        if ($event->isCancelled()) return;

        $item = $event->getItem();
        if (!$item instanceof ItemBlock) return;

        $blockPrototype = $item->getBlock();
        $entityIdFromItem = GlaciaSpawnerItem::getSpawnerEntityId($item); 

        if (!($blockPrototype instanceof CustomSpawner) && $entityIdFromItem <= 0) {
            return;
        }

        $entityId = $entityIdFromItem;
        if ($entityId <= 0 && $blockPrototype instanceof CustomSpawner) {
            $entityId = $blockPrototype->getLegacyEntityId();
        }

        if ($entityId <= 0) {
            return;
        }


        $player = $event->getPlayer();
        $transaction = $event->getTransaction();

        foreach ($transaction->getBlocks() as [$x, $y, $z, $transBlock]) {
            $pos = $transBlock->getPosition();

            try {
                $world = $pos->getWorld();
            } catch (AssumptionFailedError $e) {
                continue;
            }

            $tile = $world->getTile($pos);
            if ($tile instanceof SpawnerTile && $tile->getLegacyEntityId() === $entityId) {
                $current = $tile->getStackCount();
                $max = $tile->getMaxStack();

                if ($current < $max) {
                    $toAdd = min($item->getCount(), $max - $current);

                    $tile->setStackCount($current + $toAdd);

                    $item->pop($toAdd);
                    $player->getInventory()->setItemInHand($item);

                    $event->cancel();

                    $player->sendMessage(C::colorize("&a+{$toAdd} &7added to spawner &8({$tile->getStackCount()}/{$max})"));
                    return;
                }
            }

            $transaction->addBlock($pos->asVector3(), GlaciaSpawnerItem::MONSTER_SPAWNER()->setLegacyEntityId($entityId));
         }
    }

    public function onSpawnerBreak(BlockBreakEvent $event): void {
        if ($event->isCancelled()) return;

        $item = $event->getItem();
        $tile = ($position = $event->getBlock()->getPosition())->getWorld()->getTile($position);

        if (!$tile instanceof SpawnerTile || !$item instanceof Pickaxe || !$item->hasEnchantment(VanillaEnchantments::SILK_TOUCH())) return;

        $stackCount = $tile->getStackCount();
        $entityName = GlaciaUtils::convertMobIdToName($tile->getLegacyEntityId());
        $dropItem = StringToItemParser::getInstance()->parse("{$entityName}_spawner")
            ?? GlaciaSpawnerItem::MONSTER_SPAWNER()->asItem();

        $dropItem->setCount($stackCount);

        $event->setDrops([$dropItem]);
    }

    public function onSpawnerInteract(PlayerInteractEvent $event): void {
        if ($event->isCancelled()) return;

        $block = $event->getBlock();
        $player = $event->getPlayer();
        $pos = $block->getPosition();
        $tile = $pos->getWorld()->getTile($pos);

        if (!$tile instanceof SpawnerTile) return;

        $now = time();
        $name = strtolower($player->getName());

        if (isset($this->cooldowns[$name]) && $now < $this->cooldowns[$name]){
            return; 
        }

        $this->cooldowns[$name] = $now + 1; 

        if ($player->isSneaking()){
            SpawnerStackScreen::displayForm($player, $tile);
            $event->cancel();
            return;
        }

        if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            $stack = $tile->getStackCount();
            $max = $tile->getMaxStack();
            $entity = $tile->getEntityTypeId();
            $delayTicks = $tile->getDelay();
            $delaySec = max(0.05, $delayTicks / 20.0);
            $mobsPerCycle = min($stack, 2);
            $spawnPerMinute = (int) round($mobsPerCycle * (60.0 / $delaySec));

            $parts = explode(":", $entity);
            $mob = $parts[1]; 

            $lines = [
                "&r&l&b✦ &f" . ucfirst($mob) . " Spawner &b✦",
                "&7 Stack: &f{$stack}&7/&f{$max}",
                "&7 Delay: &f{$delayTicks} ticks &8(&f" . number_format($delaySec, 2) . "s&8)",
                "&7 Est. Mobs/Min: &f{$spawnPerMinute}",
            ];

            $player->sendMessage(C::colorize("\n" . implode("\n", $lines) . "\n"));
            $event->cancel();
        }
    }

    public function onChunkLoad(ChunkLoadEvent $event) : void {
        $world = $event->getWorld();
        $chunk = $event->getChunk();

        foreach ($chunk->getTiles() as $tile) {
            if ($tile instanceof SpawnerTile) {
                $world->scheduleDelayedBlockUpdate($tile->getPosition(), 1);
                $tile->refreshFactionWorthContribution();
            }
        }
    }

    public function onEntityDeath(EntityDeathEvent $event): void {
        $entity = $event->getEntity();
        $last = $entity->getLastDamageCause();

        if (!$last instanceof EntityDamageByEntityEvent) return;

        $damager = $last->getDamager();
        if (!$damager instanceof Player) return;

        $pos = $damager->getPosition();
        $damager->getWorld()->addSound($pos->asVector3(), new XpCollectSound());
    }

    public function onCombatEntityDeath(EntityDeathEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof CombatLoggerEntity) {
            return;
        }

        $event->setDrops([]);
        $event->setXpDropAmount(0);

        Loader::getCombatManager()->handleNPCDeath($entity);
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $hand = $player->getInventory()->getItemInHand();
        $updatedHand = TrackerManager::addProgress($hand, TrackerManager::BLOCKS);

        if (!$updatedHand->equalsExact($hand)) {
            $player->getInventory()->setItemInHand($updatedHand);
        }

        if (!GlaciaUtils::isFullyGrownCrop($block)) return;

        $pos = $player->getPosition();
        $world = $pos->getWorld();

        $world->addSound($pos->asVector3(), new XpCollectSound());
    }

    public function onEat(PlayerItemConsumeEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $session = Loader::getPlayerManager()->getSession($player);
        $now = time();

        if ($item->getTypeId() === ItemTypeIds::GOLDEN_APPLE) {
            if ($session->isOnCooldown("golden_apple")) {
                $event->cancel();
                $player->sendMessage(C::colorize(
                    "&r&c&l(!) &r&7You can't eat this again for " .
                    GeneralUtils::translateTime(
                        $session->getCooldown("golden_apple")
                    )
                ));
                return;
            }

            $count = $session->getGoldenApplesEaten();
            $lastEat = $session->getLastGoldenAppleAte();

            if ($lastEat > 0 && ($now - $lastEat) > self::GAP_WINDOW) {
                $count = 0;
            }

            $count++;
            $session->setGoldenApplesEaten($count);
            $session->setLastGoldenAppleAte($now);

            if ($count >= self::GAP_LIMIT) {
                $session->addCooldown("golden_apple", 15);
                $session->resetGoldenAppleStats();
            }

            return;
        }

        if ($item->getTypeId() === ItemTypeIds::ENCHANTED_GOLDEN_APPLE) {
            if (!$session->isOnCooldown("enchanted_golden_apple")) {
                $session->addCooldown("enchanted_golden_apple", 60);
            } else {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&c&l(!) &r&7You can't eat this again for " . GeneralUtils::translateTime($session->getCooldown("enchanted_golden_apple"))));
            }
        }
    }

    public function chatModeration(PlayerChatEvent $event): void {
        $message = $event->getMessage();
        $player = $event->getPlayer();
        $name = $player->getName();

        $slurs = [
            '/\bnigg[ae]r?\b/i'
        ];

        foreach ($slurs as $slur) {
            if (preg_match($slur, $message)) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&l&c(!) &r&cYour message contained prohibited language!"));
                return;
            }
        }

        if (!$player->hasPermission("aetheris.chat.advertise.bypass")) {
            if (preg_match('/\b([a-z0-9-]+\.)+(com|net|org|gg|co|xyz|io|me|us|uk|ru|ca|de|fr|au|nl|info|biz|site|store)\b/i', $message)) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&l&c(!) &r&cAdvertising is not allowed!"));
                return;
            }
        }

        $now = microtime(true);
        $lastMsg = self::$lastMessages[$name] ?? "";
        $lastTime = self::$lastMessageTime[$name] ?? 0;

        if ($now - $lastTime < 0.5) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&l&c(!) &r&cYou are sending messages too quickly!"));
            return;
        }

        if (trim(strtolower($message)) === trim(strtolower($lastMsg))) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&l&c(!) &r&cYou are sending the same message too quickly!"));
            return;
        }

        self::$lastMessages[$name] = $message;
        self::$lastMessageTime[$name] = $now;
    }
}
