<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\boss\bosses\AstralDevourer;
use ecstsy\Glacia\entity\GlaciaExplosiveCreeper;
use ecstsy\Glacia\entity\RiftEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\managers\RiftManager;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\droppackages\DropPackageSession;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\screens\droppackages\DropPackageScreen;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\utils\DyeColor;
use pocketmine\entity\object\FireworkRocket;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\item\FireworkRocketType;
use pocketmine\item\Hoe;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Sword;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\sound\XpCollectSound;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;

final class ItemListener implements Listener {

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

        $event->cancel();
    }

    public function onItemUse(PlayerItemUseEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $nbt = $item->getNamedTag();
        $glaciaTag = $nbt->getCompoundTag("Glacia");
        $session = Loader::getPlayerManager()->getSession($player);

        if ($glaciaTag === null || !$glaciaTag->getTag("glaciaItem")) {
            return;
        }

        $itemTag = $glaciaTag->getString("glaciaItem");

        if ($itemTag === "econote") {
            $amount = $glaciaTag->getInt("worth", 0);
            $count = $item->getCount();

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
            $amount = $glaciaTag->getInt("worth", 0);
            $count = $item->getCount();

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
                        $player->sendMessage("§cYou cannot summon a Rift right now. Too many active rifts.");
                    } else {
                        $player->sendMessage("§cFailed to summon Rift. Try again later.");
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
                $player->sendMessage(C::colorize("&r&l&cHey! &r&fYou can't do that here!"));
                return;
            }

            if ($currentRegion !== $regionManager->getRegionByName("Warzone")) {
                $player->sendMessage(C::colorize("&r&l&cHey! &r&fYou can't summon that boss here!"));
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
                    $player->sendMessage(C::colorize("&r&l&cHey! &r&fThat boss cannot be summoned here!"));
            }

            return;
        }
        
        if ($itemTag === "bloodynote") {
            $event->cancel();

            $amount = rand(50000, 500000);
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

        if ($itemTag === "ethereal_ultimate_chest") {
            $event->cancel();

            $session = new DropPackageSession(
                "ultimate",
                "Ultimate Ethereal Chest",
                "&r&l&cUltimate Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_legendary_chest") {
            $event->cancel();

            $session = new DropPackageSession(
                "legendary",
                "Legendary Ethereal Chest",
                "&r&l&dLegendary Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_godly_chest") {
            $event->cancel();

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
            $enchantment = CustomEnchantments::getEnchantmentByName($enchant);

            if ($enchantment === null) {
                $player->sendMessage(C::colorize("&r&cUnknown enchantment!"));
                return;
            }

            $level = $glaciaTag->getInt("level") ?: null;

            if ($level === null) {
                $player->sendMessage(C::colorize("&r&cInvalid level!"));
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
                            $enchantName = $nbt->getString("enchant_book");
                            $level = $nbt->getInt("level");
                            $success = $nbt->getInt("success");
                            $destroy = $nbt->getInt("destroy");

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

                            $successRoll = mt_rand(1, 100) <= $success;

                            if($successRoll){
                                $existing = CustomEnchantmentManager::getEnchantments($itemClicked);
                                $oldLevel = $existing[$enchantName] ?? 0;
                                $newLevel = max($oldLevel, min($level, $enchantment->getMaxLevel()));

                                CustomEnchantmentManager::applyEnchantment($itemClicked, $enchantment, $newLevel);
                                $player->sendMessage(C::colorize("&aEnchantment applied!"));

                                $saved = false;
                                if($player->hasPermission("glacia.book-save-chance")){
                                    if(mt_rand(1, 100) <= 15){
                                        $saved = true;
                                        $player->sendMessage(C::colorize("&aYour rank saved the book!"));
                                    }
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

            if ($itemTag !== "whitescroll" && $itemTag !== "blackscroll" && !str_contains($itemTag, "orb")) {
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
}