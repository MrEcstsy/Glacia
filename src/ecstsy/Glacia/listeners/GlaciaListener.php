<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\blocks\CustomSpawner;
use ecstsy\Glacia\blocks\tiles\SpawnerTile;
use ecstsy\Glacia\entity\CombatLoggerEntity;
use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\entity\zones\ZoneMob;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\managers\BragTracker;
use ecstsy\Glacia\managers\ChatItemTracker;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\GraceInstance;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\items\GlaciaSpawnerItem;
use ecstsy\Glacia\server\gkits\WarlordRepository;
use ecstsy\Glacia\server\player\BackLocationManager;
use ecstsy\Glacia\server\hats\HatManager;
use ecstsy\Glacia\server\hats\HatPlayer;
use ecstsy\Glacia\server\items\rename\ItemRenameManager;
use ecstsy\Glacia\server\items\trackers\TrackerManager;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\server\scoreboard\ScoreboardHelper;
use ecstsy\Glacia\server\spawners\SpawnerData;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\server\zones\ZoneMobManager;
use ecstsy\Glacia\utils\ChatTypes;
use ecstsy\Glacia\utils\DiscordLogger;
use ecstsy\Glacia\utils\EmojiManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use ecstsy\Glacia\utils\screens\spawners\SpawnerStackScreen;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
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
use pocketmine\event\player\PlayerRespawnEvent;
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
use pocketmine\entity\projectile\Projectile;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\sound\XpCollectSound;
use Yanoox\ScoreBoardAPI;

final class GlaciaListener implements Listener {

    private const REACH_LOG_THRESHOLD = 3.0;
    private const REACH_LOG_COOLDOWN = 10;

    private array $blockedDotSlashCmds = [
        "./ah",
        "./auctionhouse",
        "./bah",
        "./bin",
        "./blackmarket",
        "./bot",
        "./ce",
        "./cf",
        "./cbrag",
        "./citem",
        "./coinflip",
        "./collect",
        "./disguise",
        "./disguises",
        "./dispose",
        "./ec",
        "./ecsee",
        "./enchanter",
        "./enderchest",
        "./endersee",
        "./f disband",
        "./f upgrade",
        "./f upgrades",
        "./faction disband",
        "./faction upgrade",
        "./faction upgrades",
        "./factions disband",
        "./factions upgrade",
        "./factions upgrades",
        "./gkit",
        "./gkits",
        "./glaciasettings",
        "./invsee",
        "./kit",
        "./lbtest",
        "./lootboxtest",
        "./playervault",
        "./pv",
        "./seebrag",
        "./seeitem",
        "./settings",
        "./shop",
        "./slot",
        "./slotbot",
        "./trash",
        "./vault",
        "./zone",
        "./zones",
    ];

    /** @var Player[] */
    private array $online = [];

    private array $cooldowns = [];
    /** @var array<string, int> */
    private array $reachLogCooldowns = [];
    private const GAP_LIMIT = 3;
    private const GAP_WINDOW = 20; // seconds allowed between apples
    private const CHAT_MESSAGE_COOLDOWN = 0.5;
    private const CHAT_REPEAT_COOLDOWN = 3.0;
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
        HatManager::clearRenderedHat($player);
        Loader::getDisguiseManager()->removeFor($player);
        RuneManager::clearPlayerCaches($player);

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
        $message = EmojiManager::replaceTokens($event->getMessage());

        if (ItemRenameManager::handleChat($sender, $message)) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null) {
            return;
        }

        $chatType = $session->getCurrentChat();
        if (($message !== "" && ($message[0] === '!' || $message[0] === '#')) && $this->canUseStaffChat($sender)) {
            $chatType = ChatTypes::STAFF;
            $message = ltrim(substr($message, 1));
            if ($message === "") {
                return;
            }
        }

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

                if ($filterMode === ChatTypes::STAFF && !$this->canUseStaffChat($recipient)) {
                    continue;
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

        if ($packet instanceof InventoryTransactionPacket) {
            $originPlayer = $event->getOrigin()->getPlayer();
            $trData = $packet->trData;
            if (
                $originPlayer instanceof Player &&
                $trData instanceof UseItemOnEntityTransactionData &&
                $trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK &&
                Loader::getDisguiseManager()->handleAttackPacket($originPlayer, $trData->getActorRuntimeId())
            ) {
                $event->cancel();
                return;
            }
        }

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
        $message = EmojiManager::replaceTokens($event->getMessage());
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
                : C::colorize("&r&f[" . ($item->getCount() > 1 ? "&d" : "&f") . "{$item->getName()}" . ($item->getCount() > 1 ? "&r&f x{$item->getCount()}" : "") . "&r&f]");
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

        if (PetManager::hasBlastShield($player)) {
            $cause = $event->getCause();
            if ($cause === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION || $cause === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION) {
                $event->cancel();
                return;
            }
        }

        $stormcoreImmuneUntil = (int) (Loader::getPlayerManager()->getSession($player)?->getSetting("stormcore_self_immunity_until") ?? 0);
        if ($stormcoreImmuneUntil > time()) {
            $cause = $event->getCause();
            if ($cause === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION || $cause === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION) {
                $event->cancel();
                return;
            }
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
                    $event->getSender()->sendMessage(C::colorize("&r&cYou cannot use this command during the grace period."));
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
                    $this->sendCombatCommandDenied($sender);
                    $event->cancel();
                    return;
                }
            }

            foreach ($combatBlockedPrefix as $blocked) {
                if ($root === $blocked || str_starts_with($root, $blocked)) {
                    $this->sendCombatCommandDenied($sender);
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
                $player->sendToastNotification("Glacia Factions", C::colorize("&bExplosions are disabled during the grace period."));
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

        if ($damager instanceof Projectile && $event->getCause() === EntityDamageEvent::CAUSE_PROJECTILE) {
            $owner = $damager->getOwningEntity();
            if ($owner instanceof Player && $player instanceof Player) {
                if ($damager->getPosition()->y >= ($player->getEyePos()->y - 0.2)) {
                    PlayerUtils::playSound($owner, "note.pling");
                }
            }
        }

        if ($damager instanceof FireworkRocket) {
            $event->cancel();
            return;
        }
        
        if (!($player instanceof Player) || !($damager instanceof Player)) return;

        $this->checkReach($damager, $player);

        $combatManager = Loader::getCombatManager();
        $combatManager->startCombat($player, $damager);

        $extraCombat = max(
            RuneManager::getCombatTagExtensionSeconds($damager),
            RuneManager::getCombatTagExtensionSeconds($player)
        );
        if ($extraCombat > 0) {
            $combatManager->extendCombat($player, $extraCombat);
            $combatManager->extendCombat($damager, $extraCombat);
        }

        if (PetManager::isAegisActive($player)) {
            $event->setKnockBack(0.0);
            $event->setVerticalKnockBackLimit(0.0);
        } else {
            $event->setKnockBack(0.40);
            $event->setVerticalKnockBackLimit($player->isOnGround() ? 0.28 : 1.0);
        }
        $event->setAttackCooldown(7);

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

          if ($damager instanceof Player && $player instanceof Player && PetManager::consumeIroncladDisarm($damager)) {
              $armorInventory = $player->getArmorInventory();
              $candidates = [];
              foreach ($armorInventory->getContents() as $slot => $armorPiece) {
                  if ($armorPiece->isNull()) {
                      continue;
                  }
                  $candidates[] = $slot;
              }

              if ($candidates !== []) {
                  $slot = $candidates[array_rand($candidates)];
                  $removed = $armorInventory->getItem($slot);
                  $armorInventory->setItem($slot, \pocketmine\item\VanillaItems::AIR());
                  $player->getWorld()->dropItem($player->getPosition(), $removed);
                  $player->sendMessage(C::colorize("&r&cAn Ironclad Wyrm ripped away a piece of your armor."));
                  $damager->sendMessage(C::colorize("&r&aIronclad Wyrm Pet &r&7disarmed a piece of &f" . $player->getName() . "&7's armor."));
              }
          }
      }

    private function checkReach(Player $attacker, Player $victim): void
    {
        $eye = $attacker->getEyePos();
        $bb = $victim->getBoundingBox();

        $closestX = max($bb->minX, min($eye->x, $bb->maxX));
        $closestY = max($bb->minY, min($eye->y, $bb->maxY));
        $closestZ = max($bb->minZ, min($eye->z, $bb->maxZ));

        $dx = $eye->x - $closestX;
        $dy = $eye->y - $closestY;
        $dz = $eye->z - $closestZ;
        $reach = sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));

        if ($reach <= self::REACH_LOG_THRESHOLD) {
            return;
        }

        $key = strtolower($attacker->getName());
        $now = time();
        if (($this->reachLogCooldowns[$key] ?? 0) > $now) {
            return;
        }

        $this->reachLogCooldowns[$key] = $now + self::REACH_LOG_COOLDOWN;

        $world = $attacker->getWorld()->getFolderName();
        $attackerPing = $attacker->getNetworkSession()->getPing();
        $victimPing = $victim->getNetworkSession()->getPing();
        $distance = number_format($reach, 2);
        $attackerPos = $attacker->getPosition();
        $victimPos = $victim->getPosition();

        DiscordLogger::send(
            "**Reach Detection**\n" .
            "**Player:** `{$attacker->getName()}`\n" .
            "**Target:** `{$victim->getName()}`\n" .
            "**Reach:** `{$distance}`\n" .
            "**Attacker Ping:** `{$attackerPing}ms`\n" .
            "**Target Ping:** `{$victimPing}ms`\n" .
            "**World:** `{$world}`\n" .
            "**Attacker Pos:** `" . $attackerPos->getFloorX() . ", " . $attackerPos->getFloorY() . ", " . $attackerPos->getFloorZ() . "`\n" .
            "**Target Pos:** `" . $victimPos->getFloorX() . ", " . $victimPos->getFloorY() . ", " . $victimPos->getFloorZ() . "`"
        );
    }

    private function canUseStaffChat(Player $player): bool
    {
        return $player->hasPermission("glacia.staffchat");
    }

    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        ZoneMobManager::clearPlayerState($player);
        BackLocationManager::store($player, $player->getLocation());
        $xpDrop = $player->getXpDropAmount();
        $xpBottle = GlaciaItemFactory::xpNote($player, $xpDrop);

        $keptPets = [];
        $remainingDrops = [];
        foreach ($event->getDrops() as $drop) {
            $glaciaTag = $drop->getNamedTag()->getCompoundTag("Glacia");
            if ($glaciaTag !== null && $glaciaTag->getByte("pet_tamer", 0) === 1) {
                $keptPets[] = $this->stripPetTamer($drop);
                continue;
            }

            $remainingDrops[] = $drop;
        }
        if ($keptPets !== []) {
            PetManager::queueTamedPets($player, $keptPets);
            $event->setDrops($remainingDrops);
        }

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
        if ($killer instanceof Projectile) {
            $owner = $killer->getOwningEntity();
            if ($owner instanceof Player) {
                $killer = $owner;
            }
        }

        $weaponName = "Unknown";
        if ($cause->getDamager() instanceof Projectile && $killer instanceof Player) {
            $weaponName = $killer->getInventory()->getItemInHand()->getName();
        } elseif ($killer instanceof Player) {
            $weapon = $killer->getInventory()->getItemInHand();
            $weaponName = $weapon->isNull() ? "Bare Hands" : $weapon->getName();
        }

        if ($killer instanceof Player) {
            $event->setDeathMessage(C::colorize(
                "&r&c" . $player->getName() .
                " &7was slain by &f" . $killer->getName() .
                " &8[&c" . number_format(max(0, $killer->getHealth()), 1) . " HP&8] &7using &f" . $weaponName
            ));
        }

        if (!$killer instanceof Player || $killer->getUniqueId()->toString() === $player->getUniqueId()->toString()) {
            return;
        }

        $victimSession = Loader::getPlayerManager()->getSession($player);
        $killerSession = Loader::getPlayerManager()->getSession($killer);
        if ($victimSession === null || $killerSession === null) {
            return;
        }

        $victimSession->setDeaths($victimSession->getDeaths() + 1);
        $killerSession->setKills($killerSession->getKills() + 1);
        GlaciaUtils::updateScoreTag($player);
        GlaciaUtils::updateScoreTag($killer);

        $bounty = Loader::getBountyManager()->claimBounty($victimSession);
        if ($bounty <= 0) {
            return;
        }

        $killerSession->addBalance($bounty);
        $killer->sendMessage(C::colorize("&r&6Bounty &8| &r&aYou claimed &f$" . number_format($bounty) . " &afor killing &c" . $player->getName() . "&a."));
        Loader::getBountyManager()->broadcastClaim($killer->getName(), $player->getName(), $bounty);
    }

    public function onPlayerRespawn(PlayerRespawnEvent $event): void
    {
        $player = $event->getPlayer();
        $items = PetManager::claimQueuedTamedPets($player);
        if ($items === []) {
            return;
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($player, $items): void {
                if (!$player->isConnected()) {
                    return;
                }

                foreach ($items as $item) {
                    if ($player->getInventory()->canAddItem($item)) {
                        $player->getInventory()->addItem($item);
                    } else {
                        $player->getWorld()->dropItem($player->getPosition(), $item);
                    }
                }

                $player->sendMessage(C::colorize("&r&6Pet Tamer &8| &r&7Your protected pet returned to you."));
            }
        ), 1);
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
        $isZoneSpawner = GlaciaSpawnerItem::isZoneSpawner($item);

        if (!($blockPrototype instanceof CustomSpawner) && $entityIdFromItem <= 0 && !$isZoneSpawner) {
            return;
        }

        $entityId = $entityIdFromItem;
        if ($entityId <= 0 && $blockPrototype instanceof CustomSpawner) {
            $entityId = $blockPrototype->getLegacyEntityId();
        }

        if ($entityId <= 0 && !$isZoneSpawner) {
            return;
        }


        $player = $event->getPlayer();
        $transaction = $event->getTransaction();
        $placedPositions = [];
        $zoneId = GlaciaSpawnerItem::getZoneSpawnerZoneId($item);
        $zone = $isZoneSpawner ? ZoneManager::getZone($zoneId) : null;

        foreach ($transaction->getBlocks() as [$x, $y, $z, $transBlock]) {
            $pos = $transBlock->getPosition();

            try {
                $world = $pos->getWorld();
            } catch (AssumptionFailedError $e) {
                continue;
            }

            if ($isZoneSpawner) {
                if ($zone === null || !ZoneManager::isInsideZone($pos, $zone)) {
                    $event->cancel();
                    $player->sendMessage(C::colorize("&r&cThat zone spawner must be placed inside its assigned zone."));
                    return;
                }

                $transaction->addBlock($pos->asVector3(), GlaciaSpawnerItem::MONSTER_SPAWNER()->setLegacyEntityId($entityId > 0 ? $entityId : 32));
                $placedPositions[] = $pos;
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

        if ($isZoneSpawner && $placedPositions !== []) {
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function() use ($placedPositions, $zoneId, $entityId): void {
                    foreach ($placedPositions as $pos) {
                        $tile = $pos->getWorld()->getTile($pos);
                        if (!$tile instanceof SpawnerTile) {
                            continue;
                        }

                        $tile->setLegacyEntityId($entityId > 0 ? $entityId : 32);
                        $tile->setMode(SpawnerTile::MODE_ZONE);
                        $tile->setZoneId($zoneId);
                    }
                }
            ), 1);
        }
    }

    public function onSpawnerBreak(BlockBreakEvent $event): void {
        if ($event->isCancelled()) return;

        $item = $event->getItem();
        $tile = ($position = $event->getBlock()->getPosition())->getWorld()->getTile($position);

        if (!$tile instanceof SpawnerTile) return;

        if ($tile->isZoneSpawner()) {
            $zone = ZoneManager::getZone($tile->getZoneId());
            $event->setDrops([
                GlaciaSpawnerItem::createZoneSpawner(
                    $tile->getZoneId(),
                    $zone !== null ? C::clean((string) ($zone["display"] ?? $tile->getZoneId())) : $tile->getZoneId()
                )
            ]);
            return;
        }

        if (!$item instanceof Pickaxe || !$item->hasEnchantment(VanillaEnchantments::SILK_TOUCH())) return;

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

        if ($tile->isZoneSpawner()) {
            $zone = ZoneManager::getZone($tile->getZoneId());
            $status = ZoneMobManager::getSpawnerStatus($tile->getZoneId());
            $zoneDisplay = $zone !== null ? (string) ($zone["display"] ?? $tile->getZoneId()) : $tile->getZoneId();

            $lines = [
                "&r&l&dZone Spawner",
                "&7 Zone: &f{$zoneDisplay}",
                "&7 Players Inside: &f" . (int) ($status["players"] ?? 0),
                "&7 Alive Mobs: &f" . (int) ($status["alive"] ?? 0) . "&7/&f" . (int) ($status["desired"] ?? 0),
                "&7 Spawn Check: &f" . (int) ($status["interval"] ?? 0) . "s"
            ];

            $player->sendMessage(C::colorize("\n" . implode("\n", $lines) . "\n"));
            $event->cancel();
            return;
        }

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

        if ($entity instanceof ForsakenChampion) {
            $killerName = "Unknown";
            if ($last instanceof EntityDamageByEntityEvent && $last->getDamager() instanceof Player) {
                $killer = $last->getDamager();
                if (!$killer instanceof Player) return;

                $killerName = $killer->getName();
            }

            $profile = WarlordRepository::getWarlordById($entity->getWarlordId()) ?? [];
            $warlordName = (string) ($profile["display"] ?? C::clean($entity->getNameTag()));
            $message = str_replace(
                ["{killer}", "{warlord}", "{display}", "{gkit}"],
                [$killerName, $warlordName, $warlordName, $warlordName],
                (string) ($profile["death-message"] ?? "&r&f{killer} &r&7defeated &f{warlord}&7.")
            );
            Server::getInstance()->broadcastMessage(C::colorize($message));
        }

        if (!$last instanceof EntityDamageByEntityEvent) return;

        $damager = $last->getDamager();
        if (!$damager instanceof Player) return;

        if ($entity instanceof ZoneMob) {
            ZoneMobManager::handleMobKill($damager, $entity);
        }

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
            if (PetManager::isGappleBlocked($player)) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&cA pet effect is preventing you from eating golden apples right now."));
                return;
            }

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
            if (PetManager::isGappleBlocked($player)) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&cA pet effect is preventing you from eating golden apples right now."));
                return;
            }

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
                $player->sendMessage(C::colorize("&r&cYour message contained prohibited language."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }
        }

        if (!$player->hasPermission("glacia.chat.advertise.bypass")) {
            if (preg_match('/\b([a-z0-9-]+\.)+(com|net|org|gg|co|xyz|io|me|us|uk|ru|ca|de|fr|au|nl|info|biz|site|store)\b/i', $message)) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&cAdvertising is not allowed."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }
        }

        $key = strtolower($name);
        $now = microtime(true);
        $normalizedMessage = trim(strtolower($message));
        $lastMsg = self::$lastMessages[$key] ?? "";
        $lastTime = self::$lastMessageTime[$key] ?? 0;
        $timeSinceLastMessage = $now - $lastTime;

        if ($timeSinceLastMessage < self::CHAT_MESSAGE_COOLDOWN) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cYou are sending messages too quickly."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        if ($normalizedMessage !== "" && $normalizedMessage === $lastMsg && $timeSinceLastMessage < self::CHAT_REPEAT_COOLDOWN) {
            $event->cancel();
            $player->sendMessage(C::colorize(
                "&r&cYou can repeat that message again in &f" .
                number_format(self::CHAT_REPEAT_COOLDOWN - $timeSinceLastMessage, 1) .
                "s&r&c."
            ));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        self::$lastMessages[$key] = $normalizedMessage;
        self::$lastMessageTime[$key] = $now;
    }

    private function sendCombatCommandDenied(Player $player): void
    {
        $player->sendMessage(C::colorize("&r&cYou cannot use this command while in combat."));
        PlayerUtils::playSound($player, "note.bass");
    }

    private function stripPetTamer(\pocketmine\item\Item $item): \pocketmine\item\Item
    {
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia");
        if ($glacia === null) {
            return $item;
        }

        $glacia->setByte("pet_tamer", 0);
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);

        $lore = array_values(array_filter(
            $item->getLore(),
            static fn(string $line): bool => !str_contains(C::clean($line), "Pet Tamer:")
        ));
        $item->setLore($lore);
        return $item;
    }
}

