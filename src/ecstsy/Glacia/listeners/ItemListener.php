<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\boss\bosses\AstralDevourer;
use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\entity\GlaciaExplosiveCreeper;
use ecstsy\Glacia\entity\RiftEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\managers\RiftManager;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\disguises\DisguiseManager;
use ecstsy\Glacia\server\droppackages\DropPackageRewardPool;
use ecstsy\Glacia\server\droppackages\DropPackageSession;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\factions\FactionXpBoosterManager;
use ecstsy\Glacia\server\gkits\GKitRepository;
use ecstsy\Glacia\server\gkits\WarlordRepository;
use ecstsy\Glacia\server\hats\HatManager;
use ecstsy\Glacia\server\homes\HomeManager;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\armor\abilities\YijkiLightningStrike;
use ecstsy\Glacia\server\items\genbuckets\GenBucketManager;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\items\trackers\TrackerManager;
use ecstsy\Glacia\server\items\rename\ItemRenameManager;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\server\regions\Region;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\server\ranks\RankProgressionManager;
use ecstsy\Glacia\tasks\ChunkBusterTask;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\screens\lootboxes\LootboxAnimationScreen;
use ecstsy\Glacia\utils\screens\loot\OpenableLootPreviewScreen;
use ecstsy\Glacia\utils\screens\slotbot\SlotbotMenuScreen;
use ecstsy\Glacia\utils\screens\droppackages\DropPackageScreen;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\Location;
use pocketmine\block\utils\DyeColor;
use pocketmine\entity\object\FireworkRocket;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\Durable;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\item\FireworkRocketType;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Sword;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\Player;
use pocketmine\player\GameMode;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\sound\XpCollectSound;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\Position;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\ItemTypeIds;
use pocketmine\Server;

final class ItemListener implements Listener {

    /** @var array<string, int> */
    private static array $genBucketCooldowns = [];

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $tag = $item->getNamedTag();

        $glaciaTag = $tag->getCompoundTag("Glacia");
        if ($glaciaTag === null) {
            return;
        }

        $id = $glaciaTag->getString("glaciaItem", "");

        if (CustomTNTIds::isCustomTNT($id)) {
            return;
        }

        if ($id === "chunk_buster") {
            $event->cancel();

            $session = Loader::getPlayerManager()->getSession($player);
            $faction = $session?->getFaction();
            if ($faction === null) {
                $player->sendMessage(C::colorize("&r&cYou must be in a faction to use a Chunk Buster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $targetPos = null;
            foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
                $targetPos = new Position($x, $y, $z, $player->getWorld());
                break;
            }

            if ($targetPos === null) {
                $player->sendMessage(C::colorize("&r&cFailed to place the Chunk Buster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $claim = ClaimManager::getInstance()->getClaimAtPosition($targetPos);
            if ($claim === null || $claim->getFactionId() !== $faction->getFactionId()) {
                $player->sendMessage(C::colorize("&r&cYou can only place a Chunk Buster in your own faction claim."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $chunkX = $targetPos->getFloorX() >> 4;
            $chunkZ = $targetPos->getFloorZ() >> 4;
            if (ChunkBusterTask::isChunkActive($targetPos->getWorld()->getFolderName(), $chunkX, $chunkZ)) {
                $player->sendMessage(C::colorize("&r&cA Chunk Buster is already active in this chunk."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $targetPos->getWorld()->setBlock($targetPos, VanillaBlocks::BEACON());

            if ($player->getGamemode() !== GameMode::CREATIVE()) {
                $item->pop();
                $player->getInventory()->setItemInHand($item);
            }

            Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
                new ChunkBusterTask($player, $targetPos, $chunkX, $chunkZ),
                1
            );

            $player->sendMessage(C::colorize("&r&b&lCHUNK BUSTER &8» &r&7Deployment successful. Priming sequence started."));
            PlayerUtils::playSound($player, "random.fuse");
            return;
        }

        if ($id === "collection_chest") {
            return;
        }

        $event->cancel();
    }

    public function onItemUse(PlayerItemUseEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $session = Loader::getPlayerManager()->getSession($player);

        if ($item->getTypeId() === ItemTypeIds::ENDER_PEARL && PetManager::isPearlBlocked($player)) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cA pet effect is preventing you from using ender pearls right now."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        if ($item->getTypeId() === ItemTypeIds::ENDER_PEARL && $session !== null) {
            if ($session->isOnCooldown("ender_pearl")) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&cYou cannot throw another pearl for &f" . GeneralUtils::translateTime((int) $session->getCooldown("ender_pearl")) . "&r&c."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session->addCooldown("ender_pearl", 10);
        }

        $nbt = $item->getNamedTag();
        $glaciaTag = $nbt->getCompoundTag("Glacia");

        if ($glaciaTag === null || !$glaciaTag->getTag("glaciaItem")) {
            return;
        }

        $itemTag = $glaciaTag->getString("glaciaItem");

        if ($itemTag === "slot_ticket" || $itemTag === "heroic_slot_ticket") {
            $event->cancel();
            (new SlotbotMenuScreen($player))->display($player);
            return;
        }

        if ($itemTag === "lootbox") {
            $event->cancel();

            $lootboxId = $glaciaTag->getString("lootbox", "");
            $box = LootboxRepository::getBoxByLookup($lootboxId);
            if ($box === null) {
                $player->sendMessage(C::colorize("&r&cThis lootbox is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($player->isSneaking()) {
                OpenableLootPreviewScreen::display(
                    $player,
                    C::clean((string) ($box["name"] ?? $box["id"] ?? "Lootbox")),
                    LootboxRepository::getPreviewGroups($box)
                );
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            (new LootboxAnimationScreen($player, $box))->display($player);
            return;
        }

        if (RankProgressionManager::isRankVoucher($itemTag)) {
            $event->cancel();

            $rank = RankProgressionManager::extractRankFromIdentifier($itemTag);
            if ($rank === null) {
                $player->sendMessage(C::colorize("&r&cThis rank voucher is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!RankProgressionManager::canUseVoucher($session->getRank(), $rank)) {
                $player->sendMessage(C::colorize("&r&cYou already have this rank or a higher one."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session->setRank($rank);
            RankManager::getInstance()->updatePermission($player);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $player->sendMessage(C::colorize("&r&l&a[!] &r&aYou unlocked the &f" . ucfirst($rank) . " &arank."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if (RankProgressionManager::isRankQuest($itemTag)) {
            $event->cancel();

            $rank = RankProgressionManager::extractRankFromIdentifier($itemTag);
            if ($rank === null) {
                $player->sendMessage(C::colorize("&r&cThis rank quest is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $glaciaTag = $item->getNamedTag()->getCompoundTag("Glacia");
            $remaining = $glaciaTag?->getInt("remaining", 0) ?? 0;
            $completeTag = $glaciaTag?->getTag("complete");
            $complete = ($completeTag instanceof ByteTag || $completeTag instanceof IntTag) && $completeTag->getValue() === 1;

            if (!$complete) {
                $warzone = Loader::getRegionManager()->getRegionByName("Warzone");
                $currentRegion = Loader::getRegionManager()->getRegionAt($player->getPosition());
                if ($warzone === null || $currentRegion !== $warzone) {
                    $player->sendMessage(C::colorize("&r&cYou must be standing in the &4Warzone&r&c for this quest to progress."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $player->sendMessage(C::colorize("&r&7Warzone survival progress: &f" . GeneralUtils::translateTime($remaining) . " &7remaining."));
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            RankProgressionManager::claimQuestRewards($player, $rank);
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $player->sendMessage(C::colorize("&r&l&a[!] &r&aYou claimed your " . ucfirst($rank) . " quest rewards."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "disguise_unlock") {
            $event->cancel();

            $disguiseId = DisguiseManager::normalizeId($glaciaTag->getString("disguise", ""));
            if ($disguiseId === "" || !DisguiseManager::hasDisguise($disguiseId)) {
                $player->sendMessage(C::colorize("&r&cThis disguise unlock is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $permission = DisguiseManager::getPermission($disguiseId);
            if ($player->hasPermission($permission)) {
                $player->sendMessage(C::colorize("&r&7You already unlocked &f" . DisguiseManager::getDisplayName($disguiseId) . "&7."));
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            $session?->addExtraPermission($permission);
            RankManager::getInstance()->updatePermission($player);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&dUnlocked disguise: &f" . DisguiseManager::getDisplayName($disguiseId) . "&7. Use &f/disguise {$disguiseId}&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "renametag") {
            $event->cancel();
            ItemRenameManager::start($player);
            return;
        }

        if ($itemTag === "econote") {
            $amount = $this->readGlaciaInt($glaciaTag, "worth", 0);
            $count = $item->getCount();

            if ($amount <= 0) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&cThis bank note is invalid or corrupted."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($session->getSetting("quick_claim") && $player->isSneaking() && $count > 1) {
                $total = $amount * $count;
                $session->addBalance($total);
                $player->sendActionBarMessage(C::colorize("&r&l&a+ &r&a$" . number_format($total) . " added to your balance!"));
                $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());
                $item->pop($item->getCount());
                $player->getInventory()->setItemInHand($item);
            } else {
                if ($amount > 0) {
                    $session->addBalance($amount);
                    $player->sendActionBarMessage(C::colorize("&r&l&a+ &r&a$" . number_format($amount) . " added to your balance!"));
                    $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());
                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                }
            }

            return;
        }

        if ($itemTag === "xpnote") {
            $event->cancel();
            $amount = $this->readGlaciaInt($glaciaTag, "worth", 0);
            $count = $item->getCount();

            if ($amount <= 0) {
                $player->sendMessage(C::colorize("&r&cThis XP note is invalid or corrupted."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($session->getSetting("quick_claim") && $player->isSneaking() && $count > 1) {
                $total = $amount * $count;
                $player->getXpManager()->addXp($total);
                $player->sendMessage(C::colorize("&r&l&a+ &r&a" . number_format($total) . " EXP added!"));
                $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());
                $item->pop($item->getCount());
                $player->getInventory()->setItemInHand($item);
            } else {
                if ($amount > 0) {
                    $player->getXpManager()->addXp($amount);
                    $player->sendMessage(C::colorize("&r&l&a+ &r&a" . number_format($amount) . " EXP added!"));
                    $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());
                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                }
            }
            return;
        }

        if ($itemTag === "max_home_increase") {
            $event->cancel();
            $config = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
            $requiredPermission = (string) ($config?->get("home-increaser-permission", "glacia.home.increaser.use") ?? "glacia.home.increaser.use");
            $maxCap = max(1, (int) ($config?->get("home-max-cap", 50) ?? 50));
            $homeManager = Loader::getHomeManager();
            $currentMax = $homeManager->getMaxHomes($player->getUniqueId());

            if (!$player->hasPermission($requiredPermission)) {
                $player->sendMessage(C::colorize("&r&cYou cannot use this item yet."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($currentMax >= $maxCap) {
                $player->sendMessage(C::colorize("&r&cYou have already reached the max home cap of &f{$maxCap}&r&c."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $homeManager->setMaxHomes($player->getUniqueId(), min($maxCap, $currentMax + 1));
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $player->sendMessage(C::colorize("&r&aYour max homes increased to &f" . $homeManager->getMaxHomes($player->getUniqueId()) . "&r&a."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "frostwake_trident") {
            $event->cancel();
            $cooldown = $this->getRelicCooldownSeconds("frostwake_trident", 120);
            if ($this->handleRelicCooldown($player, $session, "relic.frostwake_trident", $cooldown, "Frostwake Trident")) {
                return;
            }

            $repaired = 0;

            foreach ($player->getInventory()->getContents() as $slot => $storedItem) {
                if (!$storedItem instanceof Durable || $storedItem->isNull() || $storedItem->getDamage() <= 0) {
                    continue;
                }

                $maxDurability = $storedItem->getMaxDurability();
                if ($maxDurability <= 0) {
                    continue;
                }

                $repair = max(1, (int) ceil($maxDurability * 0.50));
                $storedItem->setDamage(max(0, $storedItem->getDamage() - $repair));
                $player->getInventory()->setItem($slot, $storedItem);
                $repaired++;
            }

            foreach ($player->getArmorInventory()->getContents() as $slot => $storedItem) {
                if (!$storedItem instanceof Durable || $storedItem->isNull() || $storedItem->getDamage() <= 0) {
                    continue;
                }

                $maxDurability = $storedItem->getMaxDurability();
                if ($maxDurability <= 0) {
                    continue;
                }

                $repair = max(1, (int) ceil($maxDurability * 0.50));
                $storedItem->setDamage(max(0, $storedItem->getDamage() - $repair));
                $player->getArmorInventory()->setItem($slot, $storedItem);
                $repaired++;
            }

            if ($repaired <= 0) {
                $player->sendMessage(C::colorize("&r&cYou have no damaged items to restore."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session->addCooldown("relic.frostwake_trident", $cooldown);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&bFrostwake Trident &r&7restored durability throughout your inventory."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "stormflare_torch") {
            $event->cancel();
            $cooldown = $this->getRelicCooldownSeconds("stormflare_torch", 300);
            if ($this->handleRelicCooldown($player, $session, "relic.stormflare_torch", $cooldown, "Stormflare Torch")) {
                return;
            }

            $tagged = 0;
            foreach ($player->getWorld()->getPlayers() as $nearby) {
                if ($nearby->getId() === $player->getId()) {
                    continue;
                }

                if ($nearby->getPosition()->distance($player->getPosition()) > 9) {
                    continue;
                }

                Loader::getCombatManager()->startCombat($player, $nearby);
                $tagged++;
            }

            if ($tagged <= 0) {
                $player->sendMessage(C::colorize("&r&cNo players were close enough to tag."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session->addCooldown("relic.stormflare_torch", $cooldown);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&eStormflare Torch &r&7tagged &f{$tagged} &r&7player(s) in combat."));
            PlayerUtils::playSound($player, "ambient.weather.thunder");
            return;
        }

        if ($itemTag === "glacial_blessing") {
            $event->cancel();
            $cooldown = $this->getRelicCooldownSeconds("glacial_blessing", 600);
            if ($this->handleRelicCooldown($player, $session, "relic.glacial_blessing", $cooldown, "Glacial Blessing")) {
                return;
            }

            $player->setHealth($player->getMaxHealth());
            $session->addCooldown("relic.glacial_blessing", $cooldown);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&fGlacial Blessing &r&7restored you to full health."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "tempest_gateway") {
            $event->cancel();
            $cooldown = $this->getRelicCooldownSeconds("tempest_gateway", 600);
            if ($this->handleRelicCooldown($player, $session, "relic.tempest_gateway", $cooldown, "Tempest Gateway")) {
                return;
            }

            $targets = [];
            foreach ($player->getWorld()->getPlayers() as $nearby) {
                if ($nearby->getId() === $player->getId()) {
                    continue;
                }

                if ($nearby->getPosition()->distance($player->getPosition()) > 8) {
                    continue;
                }

                $targets[] = $nearby;
            }

            usort($targets, static fn(Player $a, Player $b): int => $a->getPosition()->distanceSquared($player->getPosition()) <=> $b->getPosition()->distanceSquared($player->getPosition()));
            $targetPlayer = $targets[0] ?? null;
            $strikePosition = $targetPlayer?->getPosition() ?? $player->getPosition();
            YijkiLightningStrike::strike($player, $strikePosition, 4.0, 3.0);

            $session->addCooldown("relic.tempest_gateway", $cooldown);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&3Tempest Gateway &r&7unleashed a Stormcaller ability" . ($targetPlayer !== null ? " on &f" . $targetPlayer->getName() : "") . "&r&7."));
            PlayerUtils::playSound($player, "ambient.weather.thunder");
            return;
        }

        if ($itemTag === "purity_wisp") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("purity_wisp", 10);
            if ($this->handleRelicCooldown($player, $session, "pet.purity_wisp", $cooldown, "Purity Wisp Pet")) {
                return;
            }

            GlaciaUtils::bless($player);
            if (!$this->shouldBypassPetCooldown($player, "pet.purity_wisp")) {
                $session->addCooldown("pet.purity_wisp", $cooldown);
            }
            $player->sendMessage(C::colorize("&r&fPurity Wisp Pet &r&7washed away your negative effects."));
            PlayerUtils::playSound($player, "random.orb");
            return;
        }

        if ($itemTag === "gale_fox") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("gale_fox", 300);
            if ($this->handleRelicCooldown($player, $session, "pet.gale_fox", $cooldown, "Gale Fox Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("gale_fox", "duration", 10) * RuneManager::getPetMultiplier($player, "gale_fox"));
            $speedLevel = max(1, PetManager::getInt("gale_fox", "speed-level", 5));
            $player->getEffects()->add(new \pocketmine\entity\effect\EffectInstance(\pocketmine\entity\effect\VanillaEffects::SPEED(), $duration * 20, $speedLevel - 1));
            if (!$this->shouldBypassPetCooldown($player, "pet.gale_fox")) {
                $session->addCooldown("pet.gale_fox", $cooldown);
            }
            $player->sendMessage(C::colorize("&r&bGale Fox Pet &r&7surged through you with a burst of speed."));
            PlayerUtils::playSound($player, "random.orb");
            return;
        }

        if ($itemTag === "shatterfang") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("shatterfang", 1200);
            if ($this->handleRelicCooldown($player, $session, "pet.shatterfang", $cooldown, "Shatterfang Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("shatterfang", "duration", 5) * RuneManager::getPetMultiplier($player, "shatterfang"));
            PetManager::activateShatterfang($player, $duration);
            if (!$this->shouldBypassPetCooldown($player, "pet.shatterfang")) {
                $session->addCooldown("pet.shatterfang", $cooldown);
            }
            $player->sendMessage(C::colorize("&r&8Shatterfang Pet &r&7empowered your attacks to tear through armor."));
            PlayerUtils::playSound($player, "mob.enderdragon.growl");
            return;
        }

        if ($itemTag === "hearthbloom") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("hearthbloom", 90);
            if ($this->handleRelicCooldown($player, $session, "pet.hearthbloom", $cooldown, "Hearthbloom Pet")) {
                return;
            }

            $food = PetManager::getInt("hearthbloom", "food", 20);
            $saturation = (float) PetManager::getInt("hearthbloom", "saturation", 20);
            $heal = (float) PetManager::getInt("hearthbloom", "heal", 2);
            $player->getHungerManager()->setFood(min(20, $food));
            $player->getHungerManager()->setSaturation($saturation);
            $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + $heal));
            if (!$this->shouldBypassPetCooldown($player, "pet.hearthbloom")) {
                $session->addCooldown("pet.hearthbloom", $cooldown);
            }
            $player->sendMessage(C::colorize("&r&eHearthbloom Pet &r&7restored your hunger and renewed your strength."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "ironclad_wyrm") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("ironclad_wyrm", 3600);
            if ($this->handleRelicCooldown($player, $session, "pet.ironclad_wyrm", $cooldown, "Ironclad Wyrm Pet")) {
                return;
            }

            $target = $this->findNearestEnemyPlayer($player, 8);
            if ($target === null) {
                $player->sendMessage(C::colorize("&r&cNo enemy was close enough to disarm."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            PetManager::triggerIroncladDisarm($player);
            Loader::getCombatManager()->startCombat($player, $target);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.ironclad_wyrm", $cooldown);
            $player->sendMessage(C::colorize("&r&cIronclad Wyrm Pet &r&7is ready to tear away &f" . $target->getName() . "&7's armor."));
            PlayerUtils::playSound($player, "mob.enderdragon.growl");
            return;
        }

        if ($itemTag === "aegis_ram") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("aegis_ram", 1200);
            if ($this->handleRelicCooldown($player, $session, "pet.aegis_ram", $cooldown, "Aegis Ram Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("aegis_ram", "duration", 10) * RuneManager::getPetMultiplier($player, "aegis_ram"));
            PetManager::activateAegis($player, $duration);
            GlaciaUtils::bless($player);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.aegis_ram", $cooldown);
            $player->sendMessage(C::colorize("&r&bAegis Ram Pet &r&7wrapped you in a knockback-nullifying ward."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "rift_viper") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("rift_viper", 1200);
            if ($this->handleRelicCooldown($player, $session, "pet.rift_viper", $cooldown, "Rift Viper Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("rift_viper", "duration", 6) * RuneManager::getPetMultiplier($player, "rift_viper"));
            $affected = 0;
            foreach ($this->getNearbyEnemyPlayers($player, 3.5) as $target) {
                PetManager::blockPearls($target, $duration);
                Loader::getCombatManager()->startCombat($player, $target);
                $affected++;
            }

            if ($affected <= 0) {
                $player->sendMessage(C::colorize("&r&cNo enemies were close enough to lock out ender pearls."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $this->consumePetCooldownIfNeeded($player, $session, "pet.rift_viper", $cooldown);
            $player->sendMessage(C::colorize("&r&5Rift Viper Pet &r&7sealed ender pearls for &f{$affected} &r&7enemy player(s)."));
            PlayerUtils::playSound($player, "mob.endermen.portal");
            return;
        }

        if ($itemTag === "sunscorch_jackal") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("sunscorch_jackal", 2700);
            if ($this->handleRelicCooldown($player, $session, "pet.sunscorch_jackal", $cooldown, "Sunscorch Jackal Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("sunscorch_jackal", "duration", 10) * RuneManager::getPetMultiplier($player, "sunscorch_jackal"));
            $affected = 0;
            foreach ($this->getNearbyEnemyPlayers($player, 3.5) as $target) {
                PetManager::blockGapples($target, $duration);
                Loader::getCombatManager()->startCombat($player, $target);
                $affected++;
            }

            if ($affected <= 0) {
                $player->sendMessage(C::colorize("&r&cNo enemies were close enough to scorch their apples."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $this->consumePetCooldownIfNeeded($player, $session, "pet.sunscorch_jackal", $cooldown);
            $player->sendMessage(C::colorize("&r&6Sunscorch Jackal Pet &r&7locked golden apples for &f{$affected} &r&7enemy player(s)."));
            PlayerUtils::playSound($player, "fire.ignite");
            return;
        }

        if ($itemTag === "runesage_owl") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("runesage_owl", 43200);
            if ($this->handleRelicCooldown($player, $session, "pet.runesage_owl", $cooldown, "Runesage Owl Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("runesage_owl", "duration", 300) * RuneManager::getPetMultiplier($player, "runesage_owl"));
            PetManager::activateEnchantSurge($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.runesage_owl", $cooldown);
            $player->sendMessage(C::colorize("&r&dRunesage Owl Pet &r&7empowered your enchantment applications by &f+5%&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "stormcore_behemoth") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("stormcore_behemoth", 600);
            if ($this->handleRelicCooldown($player, $session, "pet.stormcore_behemoth", $cooldown, "Stormcore Behemoth Pet")) {
                return;
            }

            $spawnPos = $player->getPosition()->add(0, 1, 0);
            $lightningPos = new Position($spawnPos->x, $spawnPos->y, $spawnPos->z, $player->getWorld());
            $location = new Location(
                $spawnPos->x + 0.5,
                $spawnPos->y,
                $spawnPos->z + 0.5,
                $player->getWorld(),
                $player->getLocation()->yaw,
                $player->getLocation()->pitch
            );
            $creeper = new GlaciaExplosiveCreeper($location);
            $creeper->setExplosiveType(CustomTNTIds::GIGANTIC);
            $creeper->setOwnerUuid($player->getUniqueId()->toString());
            $creeper->setBlockBreakingEnabled(false);
            $creeper->setScale(1.45);
            $creeper->setNameTag(C::colorize("&r&4&lCharged Giga Creeper"));
            $creeper->setNameTagAlwaysVisible(true);
            $creeper->spawnToAll();
            YijkiLightningStrike::strike($player, $lightningPos, 0.0, 0.0);
            $session->setSetting("stormcore_self_immunity_until", time() + 5);
            PlayerUtils::playSound($player, "random.fuse", 1.0, 0.9);

            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function() use ($creeper): void {
                    if ($creeper->isAlive()) {
                        $creeper->ignite();
                    }
                }
            ), 40);

            $this->consumePetCooldownIfNeeded($player, $session, "pet.stormcore_behemoth", $cooldown);
            $player->sendMessage(C::colorize("&r&cStormcore Behemoth Pet &r&7summoned a charged giga creeper."));
            PlayerUtils::playSound($player, "ambient.weather.thunder");
            return;
        }

        if ($itemTag === "veilmoth") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("veilmoth", 600);
            if ($this->handleRelicCooldown($player, $session, "pet.veilmoth", $cooldown, "Veilmoth Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("veilmoth", "duration", 60) * RuneManager::getPetMultiplier($player, "veilmoth"));
            PetManager::hideFromNear($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.veilmoth", $cooldown);
            $player->sendMessage(C::colorize("&r&8Veilmoth Pet &r&7wrapped your presence from &f/near &r&7for a short time."));
            PlayerUtils::playSound($player, "mob.endermen.portal");
            return;
        }

        if ($itemTag === "blastforge_hound") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("blastforge_hound", 900);
            if ($this->handleRelicCooldown($player, $session, "pet.blastforge_hound", $cooldown, "Blastforge Hound Pet")) {
                return;
            }

            $target = $this->findNearestEnemyPlayer($player, 8);
            if ($target === null) {
                $player->sendMessage(C::colorize("&r&cNo enemy was close enough to cripple."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("blastforge_hound", "duration", 7) * RuneManager::getPetMultiplier($player, "blastforge_hound"));
            $target->getEffects()->add(new \pocketmine\entity\effect\EffectInstance(
                \pocketmine\entity\effect\VanillaEffects::SLOWNESS(),
                $duration * 20,
                9
            ));
            PetManager::activateBlastShield($player, $duration);
            Loader::getCombatManager()->startCombat($player, $target);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.blastforge_hound", $cooldown);
            $player->sendMessage(C::colorize("&r&4Blastforge Hound Pet &r&7crippled &f" . $target->getName() . " &r&7and hardened you against explosions."));
            PlayerUtils::playSound($player, "mob.wither.shoot");
            return;
        }

        if ($itemTag === "faction_xp_booster") {
            $event->cancel();

            $faction = $session?->getFaction();
            if ($faction === null) {
                $player->sendMessage(C::colorize("&r&cYou must be in a faction to activate this booster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $manager = Loader::getFactionXpBoosterManager();
            if ($manager->hasActiveBooster($faction)) {
                $player->sendMessage(C::colorize("&r&cYour faction already has an active XP booster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $multiplierRaw = $glaciaTag->getTag("multiplier");
            $multiplier = $multiplierRaw !== null ? (float) $multiplierRaw->getValue() : 1.0;
            $durationText = $glaciaTag->getString("duration", "");
            $durationSeconds = GeneralUtils::parseTimeString($durationText);

            if ($multiplier <= 0 || $durationSeconds <= 0) {
                $player->sendMessage(C::colorize("&r&cThis booster is invalid and could not be activated."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $manager->activate($faction, $multiplier, $durationSeconds, $player);
            PlayerUtils::playSound($player, "random.levelup");
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "gkit_gem") {
            $event->cancel();

            $kitId = strtolower($glaciaTag->getString("kit", ""));
            if ($kitId === "") {
                $player->sendMessage(C::colorize("&r&cThis GKit Gem is missing its kit data."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!GKitRepository::redeemGem($player, $kitId, 1)) {
                $player->sendMessage(C::colorize("&r&cThat GKit Gem could not be redeemed."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "gkit_summoner") {
            $event->cancel();

            $kitId = strtolower($glaciaTag->getString("kit", ""));
            $kit = $kitId !== "" ? GKitRepository::getGKitById($kitId) : null;
            if ($kit === null) {
                $player->sendMessage(C::colorize("&r&cThis summoner is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $region = Loader::getRegionManager()->getRegionAt($player->getPosition());
            if ($region === null || strcasecmp($region->getName(), "Warzone") !== 0) {
                $player->sendMessage(C::colorize("&r&cYou can only use this in the &4Warzone&r&c."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $warlord = WarlordRepository::getForKit($kit);
            $reward = GKitRepository::createChampionReward($kit);
            if ($reward === null) {
                $player->sendMessage(C::colorize("&r&cThis GKit has no champion rewards configured."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $armorPool = [];
            $weaponPool = [];
            foreach (GKitRepository::resolveItemsForLevel($kit, max(1, GKitRepository::getMaxLevel($kit))) as $gearItem) {
                if ($gearItem instanceof Armor) {
                    $armorPool[] = $this->sanitizeChampionArmor(clone $gearItem);
                } elseif ($gearItem instanceof Sword || $gearItem instanceof Axe || $gearItem instanceof Bow) {
                    $weaponPool[] = clone $gearItem;
                }
            }

            $armorTag = new CompoundTag();
            foreach (array_slice($armorPool, 0, 4) as $slot => $armorPiece) {
                $armorPiece->setCount(1);
                $armorTag->setString((string) $slot, ItemUtils::encodeItem($armorPiece));
            }

            $position = $player->getPosition()->add(0, 1, 0);
            $nbt = CompoundTag::create()
                ->setInt("gkit_health", max(20, (int) ($warlord["health"] ?? 80)))
                ->setInt("gkit_damage", max(1, (int) ($warlord["damage"] ?? 9)))
                ->setFloat("gkit_move_speed", (float) ($warlord["move-speed"] ?? 0.24))
                ->setFloat("gkit_attack_reach", (float) ($warlord["attack-reach"] ?? 2.2))
                ->setFloat("gkit_target_range", (float) ($warlord["target-range"] ?? 24.0))
                ->setFloat("gkit_jump_height", (float) ($warlord["jump-height"] ?? 0.42))
                ->setInt("gkit_attack_cooldown", max(4, (int) ($warlord["attack-cooldown"] ?? 9)))
                ->setString("gkit_warlord", (string) ($warlord["id"] ?? $kitId))
                ->setString("gkit_name", C::colorize((string) ($warlord["entity-name"] ?? "&r&8Warlord")))
                ->setString("gkit_reward", ItemUtils::encodeItem($reward))
                ->setTag("gkit_armor", $armorTag);

            if ($weaponPool !== []) {
                $weapon = clone $weaponPool[array_rand($weaponPool)];
                $weapon->setCount(1);
                $nbt->setString("gkit_weapon", ItemUtils::encodeItem($weapon));
            }

            $champion = new ForsakenChampion(new Location(
                $position->x,
                $position->y,
                $position->z,
                $player->getWorld(),
                lcg_value() * 360,
                0
            ), $nbt);
            $champion->spawnToAll();

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $spawnMessage = str_replace(
                ["{player}", "{display}", "{gkit}"],
                [$player->getName(), (string) ($warlord["display"] ?? $kit["display"] ?? $kitId), (string) ($warlord["display"] ?? $kit["display"] ?? $kitId)],
                (string) ($warlord["spawn-message"] ?? "&r&8A fractured warlord has emerged in the Warzone.")
            );
            $player->sendTitle(
                C::colorize((string) ($warlord["spawn-title"] ?? "&r&8Warlord Summoned")),
                C::colorize((string) ($warlord["spawn-subtitle"] ?? "&r&7The Warzone trembles."))
            );
            $player->sendMessage(C::colorize($spawnMessage));
            PlayerUtils::playSound($player, "mob.wither.spawn");
            foreach ($player->getWorld()->getPlayers() as $worldPlayer) {
                if ($worldPlayer === $player || $worldPlayer->getPosition()->distanceSquared($position) > 64 * 64) {
                    continue;
                }

                $worldPlayer->sendMessage(C::colorize($spawnMessage));
                PlayerUtils::playSound($worldPlayer, "mob.wither.spawn");
            }
            return;
        }

        if ($itemTag === "soul_compass") {
            $event->cancel();

            $envoyManager = Loader::getEnvoyManager();
            if ($envoyManager->getActive() === null) {
                $player->sendMessage(C::colorize(
                    "&r&bSoul Compass &8| &r&7No active signal. "
                    . "&fNext Envoy &7in &b" . GeneralUtils::translateTime($envoyManager->getTimeLeft()) . "&7."
                ));
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            $nearest = $envoyManager->getNearestCrate($player->getPosition());
            if ($nearest === null) {
                $player->sendMessage(C::colorize("&r&bSoul Compass &8| &r&7The signal is unstable right now."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $uses = max(0, $glaciaTag->getInt("uses", $this->getSoulCompassDefaultUses()));
            if ($uses <= 0) {
                $player->sendMessage(C::colorize("&r&cThis Soul Compass has lost all of its charge."));
                PlayerUtils::playSound($player, "note.bass");
                $player->getInventory()->setItemInHand(VanillaItems::AIR());
                return;
            }

            $crateX = (int) ($nearest["x"] ?? 0);
            $crateY = (int) ($nearest["y"] ?? 0);
            $crateZ = (int) ($nearest["z"] ?? 0);
            $crateType = (string) ($nearest["type"] ?? "simple");
            $direction = $this->formatCompassDirection(
                ($crateX + 0.5) - $player->getPosition()->x,
                ($crateZ + 0.5) - $player->getPosition()->z
            );
            $distance = (int) round($player->getPosition()->distance(new Vector3($crateX + 0.5, $crateY + 0.5, $crateZ + 0.5)));

            $uses--;
            if ($uses <= 0) {
                $player->getInventory()->setItemInHand(VanillaItems::AIR());
                $useText = "&c0";
            } else {
                  $player->getInventory()->setItemInHand(GlaciaItemFactory::soulCompass($uses));
                  $useText = "&f" . $uses;
              }
  
              $player->sendMessage(C::colorize(
                  "&r&bSoul Compass &8| &r&7A " . ucfirst($crateType) . " signal stirs &8(&b{$direction}&8)&7. "
                  . "&7Follow the soul trail. &7Uses left: {$useText}&7."
              ));
              $this->startSoulCompassTrail($player, new Vector3($crateX + 0.5, $crateY + 0.5, $crateZ + 0.5), $crateType, $distance);
              PlayerUtils::playSound($player, "random.orb");
              return;
          }

        if ($itemTag === "envoy_beacon") {
            $event->cancel();

            $envoyManager = Loader::getEnvoyManager();
            if ($envoyManager->getActive() !== null) {
                $player->sendMessage(C::colorize("&r&cAn envoy is already active."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!$envoyManager->forceStart()) {
                $player->sendMessage(C::colorize("&r&cThe envoy could not be started right now."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $player->sendMessage(C::colorize("&r&a✔ &r&7You activated an &bEnvoy Beacon&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "rift_summoner") {
            $event->cancel();
            $riftsEnabled = false;

            if (!$riftsEnabled) {
                $player->sendMessage(C::colorize("&r&l&c(!) &r&cRifts are currently disabled."));
                return;
            }

            RiftManager::requestSpawn(
                function(Position $pos, RiftEntity $entity) use ($player, $item) {
                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                    $player->sendMessage(C::colorize("&r&l&a[!] &r&aYou have summoned a Rift!"));
                },
                function($reason = null) use ($player) {
                    if ($reason === "max_rifts") {
                        $player->sendMessage(C::colorize("&r&cYou cannot summon a Rift right now. Too many active rifts are already open."));
                    } else {
                        $player->sendMessage(C::colorize("&r&cFailed to summon a Rift. Try again in a moment."));
                    }
                }
            );        
            return;
        }

        if ($itemTag === "bossSpawn") {
            $event->cancel();

            $bossName = $glaciaTag->getString("boss", "");
            $regionManager = Loader::getRegionManager();

            $currentRegion = $regionManager->getRegionAt($player->getPosition());

            if ($currentRegion === $regionManager->getRegionByName("Spawn")) {
                $this->sendRegionDenied($player);
                return;
            }

            if ($currentRegion !== $regionManager->getRegionByName("Warzone")) {
                $player->sendMessage(C::colorize("&r&cYou can only summon that boss in the &4Warzone&r&c."));
                return;
            }

            switch ($bossName) {
                case "astral_devourer":
                    $boss = new AstralDevourer($player->getLocation());
                    $boss->spawnToAll();

                    PlayerUtils::playSound($player, "mob.wither.spawn");
                    $player->sendMessage(C::colorize("&r&l&a[!] &r&aYou have summoned an Astral Devourer!"));

                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                    break;

                default:
                    $player->sendMessage(C::colorize("&r&cThat boss cannot be summoned right now."));
            }

            return;
        }
        
        if ($itemTag === "bloodynote") {
            $event->cancel();
            $config = GeneralUtils::getConfiguration(Loader::getInstance(), "items.yml");
            

            $amount = GeneralUtils::parseRandomNumber($config->getNested("bloodynotes.pool"));
            $session = Loader::getPlayerManager()->getSession($player);

            $session->addBalance($amount);
            $player->sendMessage(C::colorize("&r&aYou have uncovered a &r&l&cBloody Note&r&a worth: $" . number_format($amount)));
            PlayerUtils::playSound($player, "random.levelup");
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }
        
        if ($itemTag === "ethereal_simple_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "simple", "Simple Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "simple",
                "Simple Ethereal Chest",
                "&r&l&8Simple Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_unique_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "unique", "Unique Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "unique",
                "Unique Ethereal Chest",
                "&r&l&aUnique Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_elite_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "elite", "Elite Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "elite",
                "Elite Ethereal Chest",
                "&r&l&bElite Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_exotic_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "exotic", "Exotic Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "exotic",
                "Exotic Ethereal Chest",
                "&r&l&eExotic Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_legendary_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "legendary", "Legendary Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "legendary",
                "Legendary Ethereal Chest",
                "&r&l&6Legendary Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_godly_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "godly", "Godly Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "godly",
                "Godly Ethereal Chest",
                "&r&l&cGodly Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "mysterymobspawner") {
            $event->cancel();

            // Available spawners from this cache
            $itemsConfig = GeneralUtils::getConfiguration(Loader::getInstance(), "items.yml");
            $spawners = $itemsConfig->getNested("mysterymobspawner.pool");

            $chosen = GlaciaUtils::getWeightedRandomSpawner($spawners);

            $reward = StringToItemParser::getInstance()->parse($chosen . "_spawner");

            if ($player->getInventory()->canAddItem($reward)) {
                $player->getInventory()->addItem($reward);
                PlayerUtils::playSound($player, "random.levelup");
            } else {
                $session->addItemToCollection($reward);
            }

            $player->sendMessage(C::colorize("&r&l&7* &r&fYou have examined a &dMystery Mob Spawner&r&f and found a &b" . str_replace("_", " ", ucfirst($chosen)) . " Spawner&r&f!"));
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "heroic_mysterymobspawner") {
            $event->cancel();

            // Available spawners from this cache
            $itemsConfig = GeneralUtils::getConfiguration(Loader::getInstance(), "items.yml");
            $spawners = $itemsConfig->getNested("heroic_mysterymobspawner.pool");

            $chosen = GlaciaUtils::getWeightedRandomSpawner($spawners);

            $reward = StringToItemParser::getInstance()->parse($chosen . "_spawner");

            if ($player->getInventory()->canAddItem($reward)) {
                $player->getInventory()->addItem($reward);
                PlayerUtils::playSound($player, "random.levelup");
            } else {
                $session->addItemToCollection($reward);
            }

            $player->sendMessage(C::colorize("&r&l&7* &r&fYou have examined a &dMystery Mob Spawner&r&f and found a &b" . str_replace("_", " ", ucfirst($chosen)) . " Spawner&r&f!"));
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }
        
        if ($itemTag === "random_magicdust") {
            $event->cancel();

            $group = $glaciaTag->getString("group", "");
            
            $reward = GlaciaItemFactory::magicDust($group, mt_rand(1, 10));

            if ($player->getInventory()->canAddItem($reward)) {
                $player->getInventory()->addItem($reward);
                PlayerUtils::playSound($player, "random.levelup");
            } else {
                $player->getWorld()->dropItem($player->getPosition(), $reward);
            }

            $player->sendMessage(C::colorize("&r&l&7* &r&fYou have examined a &dRandom Magic Dust&r&f and found &a" . str_replace("_", " ", ucfirst($group)) . " Magic Dust&r&f!"));
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "enchantment_book") {
            $event->cancel();
            $enchant = $glaciaTag->getString("enchant_book", "");
            $level = $glaciaTag->getInt("level") ?: null;

            if ($level === null) {
                $player->sendMessage(C::colorize("&r&cInvalid level!"));
                return;
            }

            $bookType = strtolower($glaciaTag->getString("book_type", "custom"));
            if ($bookType === "vanilla" || $glaciaTag->getString("vanilla_enchant", "") !== "") {
                $vanillaEnchant = $glaciaTag->getString("vanilla_enchant", $enchant);
                $enchantment = GlaciaItemFactory::resolveVanillaEnchantment($vanillaEnchant);
                if ($enchantment === null) {
                    $player->sendMessage(C::colorize("&r&cUnknown enchantment!"));
                    return;
                }

                $player->sendMessage(C::colorize("&r&7 * &eEnchantment &7| &7" . implode(' ', array_map('ucfirst', preg_split('/[_\-\s]+/', $vanillaEnchant) ?: [$vanillaEnchant])) . " " . GeneralUtils::getRomanNumeral($level)));
                $player->sendMessage(C::colorize("&r&7 * &eApplies with &7| &fVanilla enchant drag-and-drop"));
                $player->sendMessage(C::colorize("&r&7 * &eMax Level &7| &f" . GeneralUtils::getRomanNumeral($enchantment->getMaxLevel())));
                $player->sendMessage(C::colorize("&r&7 * &eDescription &7| &f" . GlaciaItemFactory::getVanillaEnchantmentDescription($vanillaEnchant)));
                return;
            }

            $enchantment = CustomEnchantments::getEnchantmentByName($enchant);
            if ($enchantment === null) {
                $player->sendMessage(C::colorize("&r&cUnknown enchantment!"));
                return;
            }

            $color = Groups::translateGroupToColor($enchantment->getRarity());
            $player->sendMessage(C::colorize("&r&7 * &eEnchantment &7| " . str_replace("{group-color}", $color, CustomEnchantments::getEnchantmentDisplayName($enchantment->getName(), $color))));
            $player->sendMessage(C::colorize("&r&7 * &eApplies to &7| &f" . implode(", ", $enchantment->getApplicableItems())));
            $player->sendMessage(C::colorize("&r&7 * &eMax Level &7| &f" . GeneralUtils::getRomanNumeral($enchantment->getMaxLevel())));
            $player->sendMessage(C::colorize("&r&7 * &eDescription &7| &f" . $enchantment->getDescription()));
            return;
        }

        if ($itemTag === "random_book") {
            $event->cancel();
            $group = $glaciaTag->getString("group", "");
            $groupEnchants = CustomEnchantments::getByGroup($group);
            $color = Groups::translateGroupToColor(Groups::getGroupId($group));

            if (empty($groupEnchants)) {
                $player->sendMessage(C::colorize("&r&4Failed to examine '&fRC Book&r&4'"));
                $player->sendMessage(C::colorize("&r&cThere are no enchantments in the group '&f" . $group . "&r&4'"));   
                return;
            }

            $randomEnchant = $groupEnchants[array_rand($groupEnchants)];

            $level = mt_rand(1, $randomEnchant->getMaxLevel());
            $success = mt_rand(1, 100);
            $destroy = mt_rand(1, 100);
            $enchantBook = GlaciaItemFactory::enchantmentBook($randomEnchant, $level, $success, $destroy);

            if ($player->getInventory()->canAddItem($enchantBook)) {
                $player->getInventory()->addItem($enchantBook);
            } else {
                $player->getWorld()->dropItem($player->getPosition(), $enchantBook);
            }
            
            PlayerUtils::playSound($player, "random.levelup");
            switch ($group) {
                case "simple":
                    $mainColors = [DyeColor::LIGHT_GRAY(), DyeColor::GRAY()];
                    $fadeColors = [DyeColor::GRAY(), DyeColor::LIGHT_GRAY()];
                    $type = FireworkRocketType::LARGE_BALL;
                    break;
                case "unique":
                    $mainColors = [DyeColor::GREEN(), DyeColor::LIME()];
                    $fadeColors = [DyeColor::LIME(), DyeColor::GREEN()];
                    $type = FireworkRocketType::LARGE_BALL;
                    break;
                case "elite":
                    $mainColors = [DyeColor::CYAN(), DyeColor::BLUE()];
                    $fadeColors = [DyeColor::BLUE(), DyeColor::CYAN()];
                    $type = FireworkRocketType::LARGE_BALL;
                    break;
                case "exotic":
                    $mainColors = [DyeColor::YELLOW(), DyeColor::ORANGE()];
                    $fadeColors = [DyeColor::ORANGE(), DyeColor::YELLOW()];
                    $type = FireworkRocketType::LARGE_BALL;
                    break;
                case "legendary":
                    $mainColors = [DyeColor::ORANGE(), DyeColor::RED()];
                    $fadeColors = [DyeColor::RED(), DyeColor::ORANGE()];
                    $type = FireworkRocketType::LARGE_BALL;
                    break;
                default:
                    $mainColors = [DyeColor::WHITE(), DyeColor::LIGHT_GRAY()];
                    $fadeColors = [DyeColor::LIGHT_GRAY(), DyeColor::WHITE()];
                    $type = FireworkRocketType::SMALL_BALL;
                    break;
                }

            $explosions = [];
            for ($i = 0; $i < 3; $i++) {
                $explosions[] = new FireworkRocketExplosion(
                    $type,
                    $mainColors, 
                    $fadeColors, 
                    true,
                    true 
                );
            }

            $firework = new FireworkRocket($player->getLocation(), 10, $explosions);
            $firework->spawnToAll();

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $player->sendMessage(C::colorize(str_replace(["{group-color}", "{group-name}", "{enchant-color}", "{level}"], [$color, $group, CustomEnchantments::getEnchantmentDisplayName($randomEnchant->getName(), $color), GeneralUtils::getRomanNumeral($level)], "&r&7You examined {group-color}{group-name}&r&7 book and found {enchant-color} {level}")));
        }
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();

        $nbt = $item->getNamedTag();
        $glaciaTag = $nbt->getCompoundTag("Glacia");

        if ($glaciaTag === null || !$glaciaTag->getTag("glaciaItem")) {
            return;
        }

        $itemTag = $glaciaTag->getString("glaciaItem");

        if ($this->isRegionRestrictedUtility($itemTag)) {
            $region = $this->getProtectedInteractRegionAt($player->getPosition());
            if ($region !== null && !$player->hasPermission("glacia.admin")) {
                $event->cancel();
                $this->sendRegionDenied($player);
                return;
            }
        }

        if ($itemTag === "gen_bucket") {
            $event->cancel();

            if (!GenBucketManager::isEnabled()) {
                $player->sendMessage(C::colorize("&r&cGen buckets are currently disabled."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session = Loader::getPlayerManager()->getSession($player);
            if ($session === null) {
                $player->sendMessage(C::colorize("&r&cYour profile is still loading. Try again in a moment."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $material = GenBucketManager::normalizeMaterial($glaciaTag->getString("material", ""));
            $direction = GenBucketManager::normalizeDirection($glaciaTag->getString("direction", ""));
            $face = GenBucketManager::getFacing($direction);
            $generatedBlock = GenBucketManager::getBlock($material);

            if ($face === null || $generatedBlock === null) {
                $player->sendMessage(C::colorize("&r&cThis gen bucket is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $cooldown = GenBucketManager::getUseCooldownSeconds();
            $now = time();
            $cooldownKey = $player->getUniqueId()->toString();
            $availableAt = self::$genBucketCooldowns[$cooldownKey] ?? 0;
            if ($cooldown > 0 && $availableAt > $now) {
                $remaining = $availableAt - $now;
                $player->sendMessage(C::colorize("&r&cYou must wait &f" . $remaining . "s &cbefore using another gen bucket."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $cost = GenBucketManager::getCost($material);
            if ($player->getGamemode() !== GameMode::CREATIVE() && $cost > 0 && $session->getBalance() < $cost) {
                $player->sendMessage(C::colorize("&r&cYou need &f$" . number_format($cost) . " &cto use this gen bucket."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $origin = $event->getBlock()->getPosition();
            $start = $origin->getSide($event->getFace());
            $world = $origin->getWorld();
            $maxLength = GenBucketManager::getMaxLength();
            $airTypeId = VanillaBlocks::AIR()->getTypeId();
            $blocksPerStep = GenBucketManager::getBlocksPerStep();

              if (
                  $this->getGenBucketBlockedRegionAt($player->getPosition()) !== null ||
                  $this->getGenBucketBlockedRegionAt($origin) !== null ||
                  $this->getGenBucketBlockedRegionAt($start) !== null
              ) {
                  $this->sendRegionDenied($player);
                  return;
            }

            if (
                $start->getFloorY() < 0 ||
                $start->getFloorY() >= $world->getMaxY() ||
                $world->getBlock($start)->getTypeId() !== $airTypeId
            ) {
                $player->sendMessage(C::colorize("&r&cThat path is already blocked."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($player->getGamemode() !== GameMode::CREATIVE()) {
                if ($cost > 0) {
                    $session->removeBalance($cost);
                }

                $item->pop();
                $player->getInventory()->setItemInHand($item);
            }

            if ($cooldown > 0) {
                self::$genBucketCooldowns[$cooldownKey] = $now + $cooldown;
            }

            PlayerUtils::playSound($player, "random.click");

            $cursor = $start;
            $placed = 0;
            $interval = GenBucketManager::getStepIntervalTicks();
            Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use ($player, $world, $generatedBlock, $airTypeId, $maxLength, $blocksPerStep, $face, $material, $direction, $cost, &$cursor, &$placed): void {
                if (!$player->isConnected() || $player->getWorld() !== $world) {
                    throw new CancelTaskException();
                }

                $placedThisStep = 0;
                for ($i = 0; $i < $blocksPerStep; $i++) {
                    if ($placed >= $maxLength) {
                        PlayerUtils::playSound($player, "random.pop");
                        throw new CancelTaskException();
                    }

                    if ($cursor->getFloorY() < 0 || $cursor->getFloorY() >= $world->getMaxY()) {
                        PlayerUtils::playSound($player, "random.pop");
                        throw new CancelTaskException();
                    }

                    if ($this->getGenBucketBlockedRegionAt($cursor) !== null) {
                        PlayerUtils::playSound($player, "note.bass");
                        throw new CancelTaskException();
                    }

                    if ($world->getBlock($cursor)->getTypeId() !== $airTypeId) {
                        PlayerUtils::playSound($player, "random.pop");
                        throw new CancelTaskException();
                    }

                    $world->setBlock($cursor, clone $generatedBlock);
                    $placed++;
                    $placedThisStep++;
                    $cursor = $this->offsetPosition($cursor, $face);
                }

                if ($placedThisStep > 0) {
                    PlayerUtils::playSound($player, "dig.stone");
                }
            }), $interval);
            return;
        }

        $explosiveType = match ($itemTag) {
            "cegg_gigantic" => "tnt_gigantic",
            "cegg_lethal" => "tnt_lethal",
            "cegg_lucky" => "tnt_lucky",
            "cegg_tactical" => "tnt_tactical",
            default => null
        };

        if ($explosiveType === null) {
            return;
        }

        $event->cancel();

        $block = $event->getBlock();
        $face = $event->getFace();

        $spawnPos = $block->getPosition()->getSide($face);

        $location = new \pocketmine\entity\Location(
            $spawnPos->getX() + 0.5,
            $spawnPos->getY(),
            $spawnPos->getZ() + 0.5,
            $spawnPos->getWorld(),
            $player->getLocation()->getYaw(),
            $player->getLocation()->getPitch()
        );

        $creeper = new GlaciaExplosiveCreeper($location);
        $creeper->setExplosiveType($explosiveType);
        $creeper->spawnToAll();

        $item->pop();
        $player->getInventory()->setItemInHand($item);
    }

    /**
     * @priority HIGHEST
     */
    public function onDragEnchant(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = array_values($transaction->getActions());

        if (count($actions) === 2) {
            foreach ($actions as $i => $action) {
                if (!$action instanceof SlotChangeAction) continue;

                if (($otherAction = $actions[($i + 1) % 2]) instanceof SlotChangeAction && ($itemClickedWith = $action->getTargetItem())->getTypeId() === VanillaItems::ENCHANTED_BOOK()->getTypeId()) {
                    if (($itemClicked = $action->getSourceItem())->getTypeId() !== VanillaItems::AIR()->getTypeId() && $itemClickedWith->getCount() === 1) {
                        $itemClickedWithTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
                        $itemClickedTag = $itemClicked->getNamedTag()->getCompoundTag("Glacia");

                        $itemClickedWithGlaciaItem = $itemClickedWithTag?->getString("glaciaItem", "");
                        $itemClickedGlaciaItem = $itemClickedTag?->getString("glaciaItem", "");

                        if(
                            $itemClickedWithGlaciaItem === "magicdust" ||
                            $itemClickedWithGlaciaItem === "primal_dust" ||
                            $itemClickedGlaciaItem === "magicdust" ||
                            $itemClickedGlaciaItem === "primal_dust"
                        ){
                            continue;
                        }

                        $nbt = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
                        if ($nbt !== null && $nbt->getTag("enchant_book") !== null) {
                            $event->cancel();

                            $player = $transaction->getSource();
                            if ($this->isBookLikeItem($itemClicked)) {
                                $player->sendMessage(C::colorize("&cYou cannot apply enchantment books to books."));
                                return;
                            }

                            $enchantName = $nbt->getString("enchant_book");
                            $level = $nbt->getInt("level");
                            $success = $nbt->getInt("success");
                            $destroy = $nbt->getInt("destroy");
                            $bookType = strtolower($nbt->getString("book_type", "custom"));

                            $isVanillaBook = $bookType === "vanilla" || $nbt->getString("vanilla_enchant", "") !== "";
                            $vanillaEnchantment = null;
                            $enchantment = null;

                            if ($isVanillaBook) {
                                $vanillaName = $nbt->getString("vanilla_enchant", $enchantName);
                                $vanillaEnchantment = GlaciaItemFactory::resolveVanillaEnchantment($vanillaName);
                                if ($vanillaEnchantment === null) {
                                    $player->sendMessage(C::colorize("&cUnknown enchantment!"));
                                    return;
                                }

                                if (!$vanillaEnchantment->canApplyTo($itemClicked)) {
                                    $player->sendMessage(C::colorize("&cThat item can't receive this enchant."));
                                    return;
                                }
                            } else {
                                $enchantment = CustomEnchantments::getEnchantmentByName($enchantName);
                                if ($enchantment === null){
                                    $player->sendMessage(C::colorize("&cUnknown enchantment!"));
                                    return;
                                }

                                $itemRoot = $itemClicked->getNamedTag()->getCompoundTag("Glacia");
                                $maxSlots = $itemRoot?->getInt("maxEnchantSlots", 9) ?? 9;
                                $customEnchantCount = count(CustomEnchantmentManager::getEnchantments($itemClicked));

                                if ($customEnchantCount >= $maxSlots) {
                                    $player->sendMessage(C::colorize("&cYou have reached the maximum amount of custom enchants for this item."));
                                    return;
                                }

                                if (!$enchantment->matches($itemClicked)) {
                                    $player->sendMessage(C::colorize("&cThat item can't receive this enchant."));
                                    return;
                                }
                            }

                            $bonusSuccess = PetManager::getEnchantBonusPercent($player);
                            $successRoll = mt_rand(1, 100) <= min(100, $success + $bonusSuccess);

                            if($successRoll){
                                if ($isVanillaBook && $vanillaEnchantment !== null) {
                                    $oldLevel = 0;
                                    foreach ($itemClicked->getEnchantments() as $instance) {
                                        if ($instance->getType() === $vanillaEnchantment) {
                                            $oldLevel = $instance->getLevel();
                                            break;
                                        }
                                    }

                                    $maxLevel = $vanillaEnchantment->getMaxLevel();
                                    $bookLevel = min($level, $maxLevel);
                                    $newLevel = $oldLevel === $bookLevel
                                        ? min($oldLevel + 1, $maxLevel)
                                        : max($oldLevel, $bookLevel);
                                    $itemClicked->addEnchantment(new EnchantmentInstance($vanillaEnchantment, $newLevel));
                                } else {
                                    $existing = CustomEnchantmentManager::getEnchantments($itemClicked);
                                    $oldLevel = $existing[$enchantName] ?? 0;
                                    $maxLevel = $enchantment->getMaxLevel();
                                    $bookLevel = min($level, $maxLevel);
                                    $newLevel = $oldLevel === $bookLevel
                                        ? min($oldLevel + 1, $maxLevel)
                                        : max($oldLevel, $bookLevel);
                                    CustomEnchantmentManager::applyEnchantment($itemClicked, $enchantment, $newLevel);
                                }

                                $player->sendMessage(C::colorize("&aEnchantment applied!"));

                                $saved = false;
                                if($player->hasPermission("glacia.book-save-chance")){
                                    if(mt_rand(1, 100) <= 15){
                                        $saved = true;
                                        $player->sendMessage(C::colorize("&aYour rank saved the book!"));
                                    }
                                }

                                if(!$saved && RuneManager::hasPassiveRune($player, "scholar") && mt_rand(1, 100) <= RuneManager::getPassiveChance("scholar", 12)){
                                    $saved = true;
                                    $player->sendMessage(C::colorize("&r&eScholar Rune &r&7preserved your enchantment book."));
                                }

                                if(!$saved){
                                    $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                                }

                                $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                                return;
                            }

                            $destroyRoll = mt_rand(1, 100) <= $destroy;

                            $isProtected = $itemClicked->getNamedTag()->getCompoundTag("Glacia")?->getByte("protected", 0) === 1;

                            if ($destroyRoll) {
                                if ($isProtected) {
                                    GlaciaUtils::consumeWhiteScroll($itemClicked, $player, $action->getInventory(), $action->getSlot());
                                } else {
                                    $action->getInventory()->setItem($action->getSlot(), VanillaItems::AIR());
                                    $player->sendMessage(C::colorize("&cEnchantment failed & your item was destroyed."));
                                }

                                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                                return;
                            }

                            $player->sendMessage(C::colorize("&eEnchantment failed, but item survived."));

                            $bookSaved = false;
                            if ($player->hasPermission("glacia.book-save-chance")) {
                                if(mt_rand(1, 100) <= 15){
                                    $bookSaved = true;
                                    $player->sendMessage(C::colorize("&aYour rank saved the book!"));
                                }
                            }

                            if (!$bookSaved && RuneManager::hasPassiveRune($player, "scholar") && mt_rand(1, 100) <= RuneManager::getPassiveChance("scholar", 12)) {
                                $bookSaved = true;
                                $player->sendMessage(C::colorize("&r&eScholar Rune &r&7preserved your enchantment book."));
                            }

                            if (!$bookSaved) {
                                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                            }

                            $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                        }
                    }
                }
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragRunes(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = array_values($transaction->getActions());

        if (count($actions) !== 2) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            $player = $transaction->getSource();

            if (!$player instanceof Player || $itemClickedWith->getCount() !== 1 || $itemClicked->isNull()) {
                continue;
            }

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if ($glaciaTag === null || $glaciaTag->getTag("glaciaItem") === null) {
                continue;
            }

            $itemTag = $glaciaTag->getString("glaciaItem");

            if ($itemTag === "rune_remover") {
                if (!RuneManager::hasAppliedRune($itemClicked)) {
                    continue;
                }

                $event->cancel();
                $appliedRuneId = RuneManager::getAppliedRuneId($itemClicked);
                if (mt_rand(1, 100) <= RuneManager::getRemovalChance()) {
                    $itemClicked = RuneManager::removeRune($itemClicked);
                    $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                    if ($appliedRuneId !== null) {
                        $rune = GlaciaItemFactory::rune($appliedRuneId);
                        foreach ($player->getInventory()->addItem($rune) as $leftover) {
                            $player->getWorld()->dropItem($player->getPosition(), $leftover);
                        }
                    }
                    $player->sendMessage(C::colorize("&r&aRune removed successfully."));
                    PlayerUtils::playSound($player, "random.levelup");
                } else {
                    $player->sendMessage(C::colorize("&r&cThe rune clung to the item. Extraction failed."));
                    PlayerUtils::playSound($player, "note.bass");
                }

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                return;
            }

            if ($itemTag !== "rune") {
                continue;
            }

            $event->cancel();
            $runeId = strtolower($glaciaTag->getString("rune_id", ""));
            if ($runeId === "" || !RuneManager::hasDefinition($runeId)) {
                $player->sendMessage(C::colorize("&r&cThis rune is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (RuneManager::isPassive($runeId)) {
                $player->sendMessage(C::colorize("&r&7This rune is passive. Keep it in your inventory to activate it."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (RuneManager::hasAppliedRune($itemClicked)) {
                $player->sendMessage(C::colorize("&r&cThis item already has a rune bound to it."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!RuneManager::canApplyTo($itemClicked, $runeId)) {
                $player->sendMessage(C::colorize("&r&cThat rune cannot be applied to this item."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (mt_rand(1, 100) <= RuneManager::getSuccessChance($runeId)) {
                $itemClicked = RuneManager::applyRune($itemClicked, $runeId);
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                $player->sendMessage(C::colorize("&r&aBound the &f" . RuneManager::getDisplayName($runeId) . " &arune to your item."));
                PlayerUtils::playSound($player, "random.levelup");
            } else {
                $player->sendMessage(C::colorize("&r&cThe rune shattered before it could take hold."));
                PlayerUtils::playSound($player, "note.bass");
            }

            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
            return;
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragPetTamer(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = array_values($transaction->getActions());

        if (count($actions) !== 2) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if ($glaciaTag === null || $glaciaTag->getString("glaciaItem", "") !== "pet_tamer") {
                continue;
            }

            $player = $transaction->getSource();
            if (!$this->isPetItem($itemClicked)) {
                $player->sendMessage(C::colorize("&cYou can only apply a Pet Tamer to a pet item."));
                continue;
            }

            $event->cancel();
            $itemClicked = $this->setPetTamerState($itemClicked, true);
            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
            $action->getInventory()->setItem($action->getSlot(), $itemClicked);
            $player->sendMessage(C::colorize("&r&aPet Tamer applied. This pet will return to you one time on death."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragScrolls(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $actions = array_values($transaction->getActions());

        if(count($actions) !== 2) return;

        foreach($actions as $i => $action){
            if(!$action instanceof SlotChangeAction) continue;

            $otherAction = $actions[($i + 1) % 2];
            if(!$otherAction instanceof SlotChangeAction) continue;

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();

            if($itemClickedWith->getCount() !== 1 || $itemClicked->getTypeId() === VanillaItems::AIR()->getTypeId()) continue;

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if($glaciaTag === null || $glaciaTag->getTag("glaciaItem") === null) continue;

            $itemTag = $glaciaTag->getString("glaciaItem");
            $player = $transaction->getSource();

            if ($itemTag !== "whitescroll" && $itemTag !== "blackscroll" && $itemTag !== "transmog" && !str_contains($itemTag, "orb")) {
                continue;
            }

            $canApply = $itemClicked instanceof Armor
                    || $itemClicked instanceof Sword
                    || $itemClicked instanceof Axe
                    || $itemClicked instanceof Pickaxe
                    || $itemClicked instanceof Shovel
                    || $itemClicked instanceof Hoe
                    || $itemClicked instanceof Bow;

            if(!$canApply){
                $player->sendMessage(C::colorize("&cYou cannot apply a scroll to this item!"));
                continue;
            }

            $event->cancel();

            if($itemTag === "whitescroll"){
                $itemTagRoot = $itemClicked->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();

                if($itemTagRoot->getByte("protected", 0) === 1){
                    $player->sendMessage(C::colorize("&cThis item is already protected!"));
                    return;
                }

                $itemTagRoot->setByte("protected", 1);
                $itemClicked->getNamedTag()->setTag("Glacia", $itemTagRoot);

                $lore = $itemClicked->getLore();
                $protectedLine = C::colorize("&r&l&fPROTECTED");
                if(!in_array($protectedLine, $lore)){
                    $lore[] = $protectedLine;
                    $itemClicked->setLore($lore);
                }

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $player->sendMessage(C::colorize("&aWhite Scroll applied! Your item is now protected."));
                PlayerUtils::playSound($player, "random.levelup");
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                return;
            }

            if ($itemTag === "transmog") {
                $itemRoot = $itemClicked->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();

                if ($itemRoot->getByte("transmog", 0) === 1) {
                    $player->sendMessage(C::colorize("&cThis item already has a Transmog Scroll applied."));
                    return;
                }

                $itemRoot->setByte("transmog", 1);
                $itemClicked->getNamedTag()->setTag("Glacia", $itemRoot);

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);

                $player->sendMessage(C::colorize("&aTransmog Scroll applied! This item's enchants will now be ordered by rarity."));
                PlayerUtils::playSound($player, "random.levelup");
                return;
            }

            if ($itemTag === "blackscroll") {
                $scrollRoot = $otherAction->getSourceItem()->getNamedTag()->getCompoundTag("Glacia");
                $scrollSuccess = $scrollRoot->getInt("success", 100); 

                $validRarities = ["simple", "unique", "elite", "exotic", "legendary"];
                $allEnchants = CustomEnchantmentManager::getEnchantments($itemClicked);

                if (empty($allEnchants)) {
                    $player->sendMessage(C::colorize("&cThis item has no removable enchantments."));
                    return;
                }

                $removable = [];
                foreach ($validRarities as $rarity) {
                    foreach (CustomEnchantments::getByGroup($rarity) as $customEnchant) {
                        $ename = $customEnchant->getName();
                        if (isset($allEnchants[$ename])) {
                            $removable[$ename] = $allEnchants[$ename];
                        }
                    }
                }

                if (empty($removable)) {
                    $player->sendMessage(C::colorize("&cNone of this item's enchantments can be removed."));
                    return;
                }

                $removedName = array_rand($removable);
                $removedLevel = $removable[$removedName];
                $removedEnchant = CustomEnchantments::getEnchantmentByName($removedName);

                CustomEnchantmentManager::removeEnchantment($itemClicked, $removedEnchant);

                $cleanName = strtolower($removedName);
                $newLore = [];
                foreach ($itemClicked->getLore() as $line) {
                    if (stripos(strtolower(C::clean($line)), $cleanName) === false) {
                        $newLore[] = $line;
                    }
                }
                $itemClicked->setLore($newLore);

                $destroyRate = mt_rand(0, 100);
                $book = GlaciaItemFactory::enchantmentBook(
                    CustomEnchantmentManager::getEnchantment($removedName),
                    $removedLevel,
                    $scrollSuccess,
                    $destroyRate
                );

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $action->getInventory()->addItem($book);
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);

                $player->sendMessage(C::colorize("&bRemoved &f{$removedName} {$removedLevel} &bfrom your item!"));
                return;
            }

            if (str_contains($itemTag, "orb")) {
                $orbLowType = explode("_", $itemTag)[0];
                $orbSlots = $glaciaTag->getInt("max", 9);
                $orbSuccess = $glaciaTag->getInt("success", 100);

                $BASE_SLOTS = 9;
                $orbBonus = max(0, $orbSlots - $BASE_SLOTS);
                $lore = $itemClicked->getLore();
                $currentMaxSlots = 0;
                $loreLineIndex = null;

                foreach ($lore as $index => $line) {
                    $clean = C::clean($line);

                    if (preg_match('/(\d+)\s*Enchantment Slots/i', $clean, $matches)) {
                        $currentMaxSlots = (int)$matches[1];
                        $loreLineIndex = $index;
                        break;
                    }
                }

                $event->cancel();

                $applies = match ($orbLowType) {
                    'weapon' => ($itemClicked instanceof Sword || $itemClicked instanceof Axe || $itemClicked instanceof Bow),
                    'armor'  => ($itemClicked instanceof Armor),
                    'tool'   => ($itemClicked instanceof Pickaxe || $itemClicked instanceof Shovel || $itemClicked instanceof Axe || $itemClicked instanceof Hoe),
                    default  => false,
                };

                if (!$applies) {
                    $player->sendMessage(C::colorize("&cThis orb cannot be applied to that item type!"));
                    return;
                }

                $root = $itemClicked->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();
                $existingOrbType = $root->getString("orb_type", "");

                if ($existingOrbType !== "" && $existingOrbType !== $orbLowType) {
                    $player->sendMessage(C::colorize("&cYou cannot apply a {$orbLowType} orb to an item already infused with a {$existingOrbType} orb!"));
                    return;
                }

                if ($orbSlots <= $currentMaxSlots) {
                    $player->sendMessage(C::colorize("&cThis orb cannot be applied because your item already has equal or higher enchantment slots."));
                    return;
                }

                if (mt_rand(1, 100) <= $orbSuccess) {
                    if ($loreLineIndex !== null) {
                        unset($lore[$loreLineIndex]);
                        $lore = array_values($lore);
                    }

                    $newLine = C::colorize("&r&l&a{$orbSlots} Enchantment Slots &r&7([&a+{$orbBonus}&7])");
                    $lore[] = $newLine;
                    $itemClicked->setLore($lore);

                    $root->setInt("maxEnchantSlots", $orbSlots);
                    $root->setString("orb_type", $orbLowType);

                    $customEnchantCount = count(CustomEnchantmentManager::getEnchantments($itemClicked));
                    $root->setInt("currentEnchants", $customEnchantCount);

                    $itemClicked->getNamedTag()->setTag("Glacia", $root);

                    $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                    $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());

                    $player->sendMessage(C::colorize("&aYour item has been upgraded to &f{$orbSlots} &aenchantment slots!"));
                    PlayerUtils::playSound($player, "random.levelup");

                } else {
                    $player->sendMessage(C::colorize("&cThe orb failed!"));
                    $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                }

                return;
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragDusts(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $actions = array_values($transaction->getActions());

        if(count($actions) !== 2) return;

        foreach($actions as $i => $action){
            if(!$action instanceof SlotChangeAction) continue;

            $otherAction = $actions[($i + 1) % 2];
            if(!$otherAction instanceof SlotChangeAction) continue;

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            $player = $transaction->getSource();

            if(!$player instanceof Player) continue;
            if($itemClickedWith->getTypeId() === VanillaItems::AIR()->getTypeId()) continue;

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if($glaciaTag === null || $glaciaTag->getTag("glaciaItem") === null) continue;

            $itemTag = $glaciaTag->getString("glaciaItem");

            $clickedTag = $itemClicked->getNamedTag()->getCompoundTag("Glacia");
            if($clickedTag === null || $clickedTag->getTag("glaciaItem") === null) continue;

            $clickedItemTag = $clickedTag->getString("glaciaItem");

            if($itemTag !== "magicdust" && $itemTag !== "primal_dust") continue;

            if($clickedItemTag !== "enchantment_book"){
                $player->sendMessage(C::colorize("&cYou can only apply dust to enchantment books!"));
                continue;
            }

            $event->cancel();

            $dustGroup = strtolower($glaciaTag->getString("group", ""));
            $dustPercent = $glaciaTag->getInt("dust-percent", 0);

            $bookEnchant = CustomEnchantmentManager::getEnchantment(strtolower($clickedTag->getString("enchant_book", "")));
            $bookRarity = $bookEnchant->getRarity();
            $bookGroup = strtolower(Groups::getGroupNameById($bookRarity));
            $bookSuccess = $clickedTag->getInt("success", 0);

            if($dustGroup === "" || $bookGroup === "" || $dustPercent === 0){
                $player->sendMessage(C::colorize("&cThis dust is missing required data."));
                return;
            }

            if(!$this->canApplyDustToBook($dustGroup, $bookGroup)){
                $player->sendMessage(C::colorize("&cThis dust cannot be applied to that enchantment book!"));
                return;
            }

            if($bookSuccess >= 100){
                $player->sendMessage(C::colorize("&cThis enchantment book is already at &f100%&c."));
                return;
            }

            $newSuccess = min(100, $bookSuccess + $dustPercent);

            $book = clone $itemClicked;
            $bookRoot = $book->getNamedTag();
            $bookTag = $bookRoot->getCompoundTag("Glacia") ?? new CompoundTag();
            $bookTag->setInt("success", $newSuccess);
            $bookRoot->setTag("Glacia", $bookTag);
            $book->setNamedTag($bookRoot);

            $book = GlaciaUtils::updateEnchantmentBook($book);

            $dust = clone $itemClickedWith;
            $dust->setCount($dust->getCount() - 1);

            if($dust->getCount() <= 0){
                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
            }else{
                $otherAction->getInventory()->setItem($otherAction->getSlot(), $dust);
            }

            PlayerUtils::playSound($player, "random.levelup");
            $action->getInventory()->setItem($action->getSlot(), $book);
            return;
        }
    }

    private function canApplyDustToBook(string $dustGroup, string $bookGroup): bool {
        return $this->getGroupWeight($dustGroup) >= $this->getGroupWeight($bookGroup);
    }

    public function onHatArmorSync(InventoryTransactionEvent $event): void
    {
        $player = $event->getTransaction()->getSource();
        if (!$player instanceof Player || !HatManager::hasRenderedHat($player)) {
            return;
        }

        foreach ($event->getTransaction()->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $inventory = $action->getInventory();
            if ($inventory instanceof ArmorInventory && $inventory->getHolder() === $player && $action->getSlot() === ArmorInventory::SLOT_HEAD) {
                Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                    static function() use ($player): void {
                        HatManager::syncHelmetChange($player);
                    }
                ), 1);
                return;
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragTrackers(InventoryTransactionEvent $event): void
    {
        $actions = array_values($event->getTransaction()->getActions());
        if (count($actions) !== 2) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            $player = $event->getTransaction()->getSource();

            if (!$player instanceof Player || $itemClickedWith->getCount() !== 1 || $itemClicked->isNull()) {
                continue;
            }

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if ($glaciaTag === null || $glaciaTag->getTag("glaciaItem") === null) {
                continue;
            }

            $itemTag = $glaciaTag->getString("glaciaItem");
            $trackerType = match ($itemTag) {
                "block_tracker" => TrackerManager::BLOCKS,
                "kill_tracker" => TrackerManager::PLAYER_KILLS,
                "mob_tracker" => TrackerManager::MOB_KILLS,
                "xp_tracker" => TrackerManager::XP,
                default => null,
            };

            if ($trackerType === null) {
                continue;
            }

            $event->cancel();

            if (!TrackerManager::canApplyTo($itemClicked, $trackerType)) {
                $player->sendMessage(C::colorize("&r&cThat tracker cannot be applied to this item."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (TrackerManager::hasTracker($itemClicked, $trackerType)) {
                $player->sendMessage(C::colorize("&r&cThis item already has that tracker applied."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $trackedItem = TrackerManager::applyTracker($itemClicked, $trackerType);
            $action->getInventory()->setItem($action->getSlot(), $trackedItem);
            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());

            $player->sendMessage(C::colorize("&r&aApplied a &f" . TrackerManager::getLabel($trackerType) . " &ato your item."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }
    }

    private function getGroupWeight(string $group): int {
        return match(strtolower($group)){
            "simple" => 1,
            "unique" => 2,
            "elite" => 3,
            "exotic" => 4,
            "legendary" => 5,
            default => 0
        };
    }

    /**
     * @priority HIGHEST
     */
    public function onCombineNotes(InventoryTransactionEvent $event): void {
        $actions = array_values($event->getTransaction()->getActions());
        if (count($actions) !== 2) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }

            $targetItem = $action->getTargetItem();
            $sourceItem = $action->getSourceItem();

            if ($targetItem->isNull() || $sourceItem->isNull()) {
                continue;
            }

            $targetGlacia = $targetItem->getNamedTag()->getCompoundTag("Glacia");
            $sourceGlacia = $sourceItem->getNamedTag()->getCompoundTag("Glacia");
            if ($targetGlacia === null || $sourceGlacia === null) {
                continue;
            }

            $targetType = $targetGlacia->getString("glaciaItem", "");
            $sourceType = $sourceGlacia->getString("glaciaItem", "");
            if ($targetType === "" || $targetType !== $sourceType || !in_array($targetType, ["econote", "xpnote"], true)) {
                continue;
            }

            $event->cancel();

            $sourceWorth = $this->readGlaciaInt($sourceGlacia, "worth", 0);
            $targetWorth = $this->readGlaciaInt($targetGlacia, "worth", 0);
            $sourceCount = max(1, $sourceItem->getCount());
            $targetCount = max(1, $targetItem->getCount());
            $mergedWorth = ($sourceWorth * $sourceCount) + ($targetWorth * $targetCount);
            $signer = $targetGlacia->getString("signer", $this->extractSigner($targetItem));

            if ($sourceWorth <= 0 || $targetWorth <= 0 || $mergedWorth <= 0) {
                $event->getTransaction()->getSource()->sendMessage(C::colorize("&r&cOne of those notes is invalid or corrupted."));
                PlayerUtils::playSound($event->getTransaction()->getSource(), "note.bass");
                return;
            }

            $merged = $targetType === "econote"
                ? GlaciaItemFactory::bankNoteSigned($signer, $mergedWorth)
                : GlaciaItemFactory::xpNoteSigned($signer, $mergedWorth);

            $action->getInventory()->setItem($action->getSlot(), $merged);
            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
            $event->getTransaction()->getSource()->sendMessage(C::colorize("&r&aMerged your notes into one."));
            PlayerUtils::playSound($event->getTransaction()->getSource(), "random.anvil_use");
            return;
        }
    }

    private function extractSigner(\pocketmine\item\Item $item): string
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia !== null) {
            $signer = $glacia->getString("signer", "");
            if ($signer !== "") {
                return $signer;
            }
        }

        foreach ($item->getLore() as $line) {
            $clean = C::clean($line);
            if (str_starts_with($clean, "Signer: ")) {
                return substr($clean, 8);
            }
        }

        return "Ethereal Hub";
    }

    private function readGlaciaInt(CompoundTag $tag, string $key, int $default = 0): int
    {
        $valueTag = $tag->getTag($key);
        if ($valueTag instanceof IntTag || $valueTag instanceof ByteTag) {
            return (int) $valueTag->getValue();
        }

        if ($valueTag instanceof \pocketmine\nbt\tag\StringTag) {
            $raw = trim($valueTag->getValue());
            if ($raw === "") {
                return $default;
            }

            if (is_numeric($raw)) {
                return (int) $raw;
            }

            $parsed = GeneralUtils::parseRandomNumber($raw);
            return is_numeric($parsed) ? (int) $parsed : $default;
        }

        return $default;
    }

    private function handleRelicCooldown(Player $player, \ecstsy\Glacia\player\GlaciaPlayer $session, string $cooldownKey, int $seconds, string $display): bool
    {
        if ($this->shouldBypassPetCooldown($player, $cooldownKey)) {
            return false;
        }

        if (!$session->isOnCooldown($cooldownKey)) {
            return false;
        }

        $player->sendMessage(C::colorize("&r&c{$display} is on cooldown for &f" . GeneralUtils::translateTime((int) $session->getCooldown($cooldownKey)) . "&r&c."));
        PlayerUtils::playSound($player, "note.bass");
        return true;
    }

    private function shouldBypassPetCooldown(Player $player, string $cooldownKey): bool
    {
        return str_starts_with($cooldownKey, "pet.")
            && ($player->hasPermission("glacia.pet.cooldown.bypass") || Server::getInstance()->isOp($player->getName()));
    }

    private function getRelicCooldownSeconds(string $key, int $fallback): int
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $value = $config?->getNested("relic-cooldowns.{$key}", "{$fallback}s");
        return max(1, GeneralUtils::parseTimeString((string) $value));
    }

    private function getSoulCompassDefaultUses(): int
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "envoy.yml");
        return max(1, (int) ($config?->getNested("soul-compass.default-uses", 8) ?? 8));
    }

    private function displayDropPackagePreview(Player $player, string $packageType, string $displayName): void
    {
        OpenableLootPreviewScreen::display(
            $player,
            $displayName,
            DropPackageRewardPool::getPreviewGroups($packageType)
        );
        PlayerUtils::playSound($player, "random.click");
    }

    private function formatCompassDirection(float $deltaX, float $deltaZ): string
    {
        $vertical = "";
        $horizontal = "";

        if ($deltaZ <= -3) {
            $vertical = "N";
        } elseif ($deltaZ >= 3) {
            $vertical = "S";
        }

        if ($deltaX >= 3) {
            $horizontal = "E";
        } elseif ($deltaX <= -3) {
            $horizontal = "W";
        }

        return $vertical . $horizontal ?: "HERE";
    }

    private function startSoulCompassTrail(Player $player, Vector3 $target, string $crateType, int $distance): void
    {
        $ticks = 0;
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() use ($player, $target, $crateType, $distance, &$ticks): void {
                if (!$player->isConnected() || $player->isClosed() || $player->getWorld() !== Loader::getInstance()->getServer()->getWorldManager()->getDefaultWorld()) {
                    throw new CancelTaskException();
                }

                $ticks++;

                $origin = $player->getEyePos();
                $delta = $target->subtractVector($origin);
                $length = sqrt(($delta->x * $delta->x) + ($delta->y * $delta->y) + ($delta->z * $delta->z));
                if ($length <= 0.001) {
                    throw new CancelTaskException();
                }

                $unitX = $delta->x / $length;
                $unitY = $delta->y / $length;
                $unitZ = $delta->z / $length;
                $segments = min(14, max(6, (int) floor($distance / 7)));

                for ($i = 1; $i <= $segments; $i++) {
                    $step = min($length, $i * 1.75);
                    $particlePos = new Vector3(
                        $origin->x + ($unitX * $step),
                        $origin->y + ($unitY * $step),
                        $origin->z + ($unitZ * $step)
                    );
                    $player->getWorld()->addParticle($particlePos, new BlockBreakParticle(VanillaBlocks::REDSTONE()));
                }

                $leadDistance = min($length, ($segments + 1) * 1.75);
                $leadPos = new Vector3(
                    $origin->x + ($unitX * $leadDistance),
                    $origin->y + ($unitY * $leadDistance),
                    $origin->z + ($unitZ * $leadDistance)
                );

                for ($ring = 0; $ring < 8; $ring++) {
                    $angle = deg2rad(($ring * 45) + ($ticks * 24));
                    $player->getWorld()->addParticle(
                        new Vector3(
                            $leadPos->x + (cos($angle) * 0.65),
                            $leadPos->y + 0.2,
                            $leadPos->z + (sin($angle) * 0.65)
                        ),
                        new BlockBreakParticle(VanillaBlocks::REDSTONE())
                    );
                }

                for ($column = 0; $column < 4; $column++) {
                    $markerPos = new Vector3($leadPos->x, $leadPos->y + ($column * 0.55), $leadPos->z);
                    $player->getWorld()->addParticle($markerPos, new BlockBreakParticle(VanillaBlocks::REDSTONE()));
                }

                if ($ticks % 4 === 0) {
                    $direction = $this->formatCompassDirection($target->x - $origin->x, $target->z - $origin->z);
                    $player->sendActionBarMessage(C::colorize(
                        "&r&bSoul Compass &8| &r&f" . ucfirst($crateType) . " signal &7pulling &b{$direction}"
                    ));
                    PlayerUtils::playSound($player, "random.click");
                }

                if ($ticks >= 40) {
                    throw new CancelTaskException();
                }
            }
        ), 1);
    }

    private function consumeHeldItem(Player $player, \pocketmine\item\Item $item): void
    {
        $item->pop();
        $player->getInventory()->setItemInHand($item);
    }

    private function consumePetCooldownIfNeeded(Player $player, \ecstsy\Glacia\player\GlaciaPlayer $session, string $cooldownKey, int $seconds): void
    {
        if (!$this->shouldBypassPetCooldown($player, $cooldownKey)) {
            $session->addCooldown($cooldownKey, $seconds);
        }
    }

    /**
     * @return Player[]
     */
    private function getNearbyEnemyPlayers(Player $player, float $radius): array
    {
        $targets = [];
        foreach ($player->getWorld()->getPlayers() as $nearby) {
            if ($nearby->getId() === $player->getId()) {
                continue;
            }

            if ($nearby->getPosition()->distance($player->getPosition()) > $radius) {
                continue;
            }

            $targets[] = $nearby;
        }

        return $targets;
    }

    private function findNearestEnemyPlayer(Player $player, float $radius): ?Player
    {
        $targets = $this->getNearbyEnemyPlayers($player, $radius);
        usort($targets, static fn(Player $a, Player $b): int => $a->getPosition()->distanceSquared($player->getPosition()) <=> $b->getPosition()->distanceSquared($player->getPosition()));
        return $targets[0] ?? null;
    }

    private function isBookLikeItem(Item $item): bool
    {
        $typeId = $item->getTypeId();

        return $typeId === VanillaItems::BOOK()->getTypeId()
            || $typeId === VanillaItems::ENCHANTED_BOOK()->getTypeId();
    }

    private function isPetItem(Item $item): bool
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return false;
        }

        return in_array($glacia->getString("glaciaItem", ""), [
            "purity_wisp",
            "gale_fox",
            "shatterfang",
            "hearthbloom",
            "ironclad_wyrm",
            "aegis_ram",
            "rift_viper",
            "sunscorch_jackal",
            "runesage_owl",
            "stormcore_behemoth",
            "veilmoth",
            "blastforge_hound"
        ], true);
    }

    private function setPetTamerState(Item $item, bool $active): Item
    {
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setByte("pet_tamer", $active ? 1 : 0);
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);

        $lore = array_values(array_filter(
            $item->getLore(),
            static fn(string $line): bool => !str_contains(C::clean($line), "Pet Tamer:")
        ));
        if ($active) {
            $lore[] = C::colorize("&r&6Pet Tamer: &aActive");
        }
        $item->setLore($lore);
        return $item;
    }

    private function isRegionRestrictedUtility(string $itemTag): bool
    {
        return in_array($itemTag, [
            "frostwake_trident",
            "stormflare_torch",
            "glacial_blessing",
            "tempest_gateway",
            "purity_wisp",
            "gale_fox",
            "shatterfang",
            "hearthbloom",
            "ironclad_wyrm",
            "aegis_ram",
            "rift_viper",
            "sunscorch_jackal",
            "runesage_owl",
            "stormcore_behemoth",
            "veilmoth",
            "blastforge_hound"
        ], true);
    }

    private function getProtectedBuildRegionAt(Vector3 $position): ?Region
    {
        $region = Loader::getRegionManager()->getRegionAt($position);
        return $region !== null && !$region->permissions()->build ? $region : null;
    }

    private function getProtectedInteractRegionAt(Vector3 $position): ?Region
    {
        $region = Loader::getRegionManager()->getRegionAt($position);
        return $region !== null && !$region->permissions()->interact ? $region : null;
    }

    private function getGenBucketBlockedRegionAt(Vector3 $position): ?Region
    {
        $region = Loader::getRegionManager()->getRegionAt($position);
        if ($region === null) {
            return null;
        }

        if (!$region->permissions()->build) {
            return $region;
        }

        return in_array(strtolower($region->getName()), ["spawn", "warzone"], true) ? $region : null;
    }

    private function sendRegionDenied(Player $player): void
    {
        $player->sendMessage(C::colorize("&r&cYou can't do that here."));
        PlayerUtils::playSound($player, "note.bass");
    }

    private function sanitizeChampionArmor(Armor $item): Armor
    {
        $root = $item->getNamedTag();
        $enchTag = $root->getCompoundTag("GlaciaCES");
        if ($enchTag === null) {
            return $item;
        }

        foreach (["drunk", "gears", "bounce", "overload", "aquatic", "glowing", "lavabound"] as $enchant) {
            $enchTag->removeTag($enchant);
        }

        if ($enchTag->count() === 0) {
            $root->removeTag("GlaciaCES");
        } else {
            $root->setTag("GlaciaCES", $enchTag);
        }

        $item->setNamedTag($root);
        return $item;
    }

    private function offsetPosition(Position $position, int $face): Position
    {
        return match ($face) {
            Facing::NORTH => new Position($position->getFloorX(), $position->getFloorY(), $position->getFloorZ() - 1, $position->getWorld()),
            Facing::SOUTH => new Position($position->getFloorX(), $position->getFloorY(), $position->getFloorZ() + 1, $position->getWorld()),
            Facing::EAST => new Position($position->getFloorX() + 1, $position->getFloorY(), $position->getFloorZ(), $position->getWorld()),
            Facing::WEST => new Position($position->getFloorX() - 1, $position->getFloorY(), $position->getFloorZ(), $position->getWorld()),
            Facing::UP => new Position($position->getFloorX(), $position->getFloorY() + 1, $position->getFloorZ(), $position->getWorld()),
            Facing::DOWN => new Position($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ(), $position->getWorld()),
        };
    }
}
