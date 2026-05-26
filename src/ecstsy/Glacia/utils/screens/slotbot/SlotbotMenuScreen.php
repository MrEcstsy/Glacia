<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\slotbot;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\slotbot\SlotbotRewardRepository;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use ecstsy\Glacia\utils\RewardAnnouncer;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as C;

final class SlotbotMenuScreen extends BaseScreen {

    private const STATUS_IDLE = 0;
    private const STATUS_ANIMATING = 1;
    private const STATUS_DONE = 2;
    private const STATUS_STOPPING = 3;

    private const TAG_LOOT_TABLE = "glacia_slotbot_loot_table";
    private const TAG_CREDIT_SHOP = "glacia_slotbot_credit_shop";
    private const TAG_SPIN = "glacia_slotbot_spin";
    private const TAG_HELP = "glacia_slotbot_help";
    private const TAG_ADD_TICKETS = "glacia_slotbot_add_tickets";
    private const TAG_ADD_ALL_TICKETS = "glacia_slotbot_add_all_tickets";
    private const TAG_EMPTY_SLOT = "glacia_slotbot_empty_slot";

    private InvMenu $menu;
    private int $status = self::STATUS_IDLE;
    /** @var Item[] */
    private array $rewards = [];
    /** @var Item[] */
    private array $pendingRewards = [];
    private ?TaskHandler $animationTask = null;

    public function __construct(private Player $player)
    {
        $this->menu = CustomSizedInvMenu::create(54);
        $this->menu->setName(C::colorize("&r&l&dSlotbot &8> &r&8Main Menu"));
        $this->rewards = SlotbotRewardRepository::getRewards();

        $this->placeItems($this->menu->getInventory());
        $this->menu->setListener(InvMenu::readonly(
            fn(DeterministicInvMenuTransaction $transaction): bool => $this->handle($transaction)
        ));
        $this->menu->setInventoryCloseListener(function(Player $player, Inventory $inventory): void {
            $this->handleClose($player, $inventory);
        });
    }

    public function display(Player $player): void
    {
        $this->menu->send($player);
    }

    public function getMenu(): ?InvMenu
    {
        return $this->menu;
    }

    private function placeItems(Inventory $inventory): void
    {
        $grayGlassSlots = [9, 27, 36, 45, 46, 8, 17, 35, 44, 52];
        $whiteGlassSlots = [2, 3, 4, 5, 6, 11, 12, 13, 14, 15, 29, 30, 31, 32, 33, 38, 39, 40, 41, 42];
        $greenGlassSlots = [20, 21, 22, 23, 24];
        $blackGlassSlots = array_merge(range(1, 37, 9), range(7, 43, 9));

        $glass = VanillaBlocks::STAINED_GLASS_PANE();

        foreach ($grayGlassSlots as $slot) {
            $inventory->setItem($slot, $glass->setColor(DyeColor::GRAY())->asItem()->setCustomName(" "));
        }
        foreach ($whiteGlassSlots as $slot) {
            $inventory->setItem($slot, $glass->setColor(DyeColor::WHITE())->asItem()->setCustomName(" "));
        }
        foreach ($greenGlassSlots as $slot) {
            $inventory->setItem($slot, $glass->setColor(DyeColor::LIME())->asItem()->setCustomName(C::colorize("&r&l&aReward"))->setLore([
                C::colorize("&r&7The item that appears here"),
                C::colorize("&r&7is what you win.")
            ]));
        }
        foreach ($blackGlassSlots as $slot) {
            $inventory->setItem($slot, $glass->setColor(DyeColor::BLACK())->asItem()->setCustomName(" "));
        }

        $inventory->setItem(18, $this->createCreditShopItem());
        $inventory->setItem(0, $this->createMenuItem(VanillaBlocks::BEACON()->asItem(), "&r&l&fLoot Table", self::TAG_LOOT_TABLE, [
            "&r&7View the normal and jackpot loot tables."
        ]));
        $inventory->setItem(8, $this->createAddTicketItem(false));
        $inventory->setItem(7, $this->createAddAllTicketsItem(false));
        $inventory->setItem(26, $this->createSpinItem(false));
        $inventory->setItem(53, $this->createMenuItem(VanillaItems::BOOK(), "&r&l&bHow To Play", self::TAG_HELP, [
            "&r&d1. &r&7Insert up to 5 slot tickets.",
            "&r&d2. &r&7Click spin to start the bot.",
            "&r&d3. &r&7Each ticket rolls one reward."
        ]));

        foreach (range(47, 51) as $slot) {
            $inventory->setItem($slot, $this->createEmptyTicketSlotItem($slot - 46));
        }
    }

    private function handle(DeterministicInvMenuTransaction $transaction): bool
    {
        if ($this->status !== self::STATUS_IDLE) {
            return false;
        }

        $player = $transaction->getPlayer();
        $item = $transaction->getItemClicked();
        $tag = $item->getNamedTag();

        if ($tag->getTag(self::TAG_ADD_TICKETS) !== null) {
            $this->addTicket($player);
            return false;
        }
        if ($tag->getTag(self::TAG_ADD_ALL_TICKETS) !== null) {
            $this->addAllTickets($player);
            return false;
        }

        if (GlaciaItemFactory::isGlaciaItem($item, "slot_ticket") || GlaciaItemFactory::isGlaciaItem($item, "heroic_slot_ticket")) {
            $this->removeTicket($player, $transaction->getAction()->getSlot());
            return false;
        }

        if ($tag->getTag(self::TAG_SPIN) !== null) {
            $this->spin($player);
            return false;
        }

        if ($tag->getTag(self::TAG_LOOT_TABLE) !== null) {
            (new SlotbotLootTableSelectorScreen($player))->display($player);
            return false;
        }

        if ($tag->getTag(self::TAG_CREDIT_SHOP) !== null) {
            (new SlotbotCreditShopScreen($player))->display($player);
            return false;
        }

        if ($tag->getTag(self::TAG_HELP) !== null) {
            PlayerUtils::playSound($player, "item.book.page_turn");
            return false;
        }

        return false;
    }

    private function handleClose(Player $player, Inventory $inventory): void
    {
        if ($this->status === self::STATUS_IDLE) {
            foreach ($this->getFilledTicketSlots() as $slot) {
                foreach ($player->getInventory()->addItem($this->createTicketRefund($this->menu->getInventory()->getItem($slot))) as $leftover) {
                    $player->getWorld()->dropItem($player->getPosition(), $leftover);
                }
            }
            return;
        }

        if ($this->status === self::STATUS_ANIMATING) {
            $this->status = self::STATUS_STOPPING;
            $this->finishSpin(true);
        }
    }

    private function addTicket(Player $player): void
    {
        $emptySlots = $this->getEmptyTicketSlots();
        if ($emptySlots === []) {
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $ticketData = $this->findFirstTicket($player);
        if ($ticketData === null) {
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $slot = (int) $ticketData["slot"];
        $item = clone $ticketData["item"];
        $item->setCount($item->getCount() - 1);
        $player->getInventory()->setItem($slot, $item);

        $menuSlot = $emptySlots[0];
        $ticketItem = clone $ticketData["item"];
        $ticketItem->setCount($menuSlot - 46);
        $this->menu->getInventory()->setItem($menuSlot, $ticketItem);

        $filledCount = count($this->getFilledTicketSlots());
        $this->menu->getInventory()->setItem(26, $this->createSpinItem($filledCount > 0));
        $this->menu->getInventory()->setItem(8, $this->createAddTicketItem($filledCount >= 5));
        $this->menu->getInventory()->setItem(7, $this->createAddAllTicketsItem($filledCount >= 5));
        PlayerUtils::playSound($player, "random.orb");
    }

    private function addAllTickets(Player $player): void
    {
        $addedAny = false;
        while ($this->getEmptyTicketSlots() !== []) {
            $ticketData = $this->findFirstTicket($player);
            if ($ticketData === null) {
                break;
            }

            $this->addTicket($player);
            $addedAny = true;
        }

        if (!$addedAny) {
            PlayerUtils::playSound($player, "note.bass");
        }
    }

    private function removeTicket(Player $player, int $slot): void
    {
        if (!in_array($slot, range(47, 51), true)) {
            return;
        }

        $ticket = clone $this->menu->getInventory()->getItem($slot);
        $this->menu->getInventory()->setItem($slot, $this->createEmptyTicketSlotItem($slot - 46));

        foreach ($player->getInventory()->addItem($this->createTicketRefund($ticket)) as $leftover) {
            $player->getWorld()->dropItem($player->getPosition(), $leftover);
        }

        $filledCount = count($this->getFilledTicketSlots());
        $this->menu->getInventory()->setItem(26, $this->createSpinItem($filledCount > 0));
        $this->menu->getInventory()->setItem(8, $this->createAddTicketItem($filledCount >= 5));
        $this->menu->getInventory()->setItem(7, $this->createAddAllTicketsItem($filledCount >= 5));
        PlayerUtils::playSound($player, "random.fizz");
    }

    private function spin(Player $player): void
    {
        $filledSlots = $this->getFilledTicketSlots();
        if ($filledSlots === []) {
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        if ($this->rewards === []) {
            $player->sendMessage(C::colorize("&r&cSlotbot has no rewards configured."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $this->status = self::STATUS_ANIMATING;
        $this->pendingRewards = [];
        $inventory = $this->menu->getInventory();

        foreach (array_merge([2, 3, 4, 5, 6, 11, 12, 13, 14, 15, 20, 21, 22, 23, 24, 29, 30, 31, 32, 33, 38, 39, 40, 41, 42], [0, 8, 18, 26, 53]) as $slot) {
            if (in_array($slot, [0, 8, 18, 26, 53], true)) {
                continue;
            }
            $inventory->setItem($slot, VanillaItems::AIR());
        }

        foreach ($filledSlots as $slot) {
            $ticket = $inventory->getItem($slot);
            $heroic = GlaciaItemFactory::isGlaciaItem($ticket, "heroic_slot_ticket");
            $this->pendingRewards[] = $heroic ? $this->getJackpotReward() : $this->getSpinReward();
            $inventory->setItem($slot - 45, $heroic ? $this->getJackpotDisplayReward() : $this->getDisplayReward());
        }

        $ticks = 0;
        $this->animationTask = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() use (&$ticks, $player, $filledSlots): void {
                if (!$player->isConnected() || $this->status === self::STATUS_STOPPING || $ticks >= 160) {
                    $this->finishSpin(false);
                    return;
                }

                if ($ticks > 0) {
                    $this->animateColumns($filledSlots);
                }

                PlayerUtils::playSound($player, "random.orb");
                $ticks += 5;
            }
        ), 3);
    }

    private function animateColumns(array $filledSlots): void
    {
        $inventory = $this->menu->getInventory();

        foreach ($filledSlots as $slot) {
            $offset = $slot - 47;
            $animationMap = [
                29 + $offset => 38 + $offset,
                20 + $offset => 29 + $offset,
                11 + $offset => 20 + $offset,
                2 + $offset => 11 + $offset
            ];

            foreach ($animationMap as $source => $target) {
                $inventory->setItem($target, $inventory->getItem($source));
            }
        }

        foreach ($filledSlots as $slot) {
            $ticket = $inventory->getItem($slot);
            $heroic = GlaciaItemFactory::isGlaciaItem($ticket, "heroic_slot_ticket");
            $inventory->setItem($slot - 45, $heroic ? $this->getJackpotDisplayReward() : $this->getDisplayReward());
        }
    }

    private function finishSpin(bool $forced): void
    {
        if ($this->status === self::STATUS_DONE) {
            return;
        }

        if ($this->animationTask !== null) {
            $this->animationTask->cancel();
            $this->animationTask = null;
        }

        $filledSlots = $this->getFilledTicketSlots();
        $rewards = [];
        foreach (array_values($filledSlots) as $index => $slot) {
            $reward = $this->pendingRewards[$index] ?? $this->getSpinReward();
            if ($reward === null) {
                continue;
            }

            $rewards[] = clone $reward;
            $this->menu->getInventory()->setItem($slot - 27, clone $reward);
        }

        $session = Loader::getPlayerManager()->getSession($this->player);
        $recipient = $session?->getPlayer();
        if ($recipient instanceof Player && $recipient->isConnected()) {
            Loader::getQuestBoardManager()->recordProgress($recipient, "slotbot_tickets_rolled", count($filledSlots));
            foreach ($recipient->getInventory()->addItem(...$rewards) as $leftover) {
                $session->addItemToCollection($leftover);
            }
        } elseif ($session !== null) {
            foreach ($rewards as $reward) {
                $session->addItemToCollection($reward);
            }
        }

        if ($session !== null) {
            $session->addSlotCredits(count($rewards));
        }

        $savedTickets = 0;
        if (
            !$forced &&
            $recipient instanceof Player &&
            $recipient->isConnected() &&
            RuneManager::hasPassiveRune($recipient, "slotkeeper")
        ) {
            foreach ($filledSlots as $slot) {
                if (mt_rand(1, 100) > RuneManager::getPassiveChance("slotkeeper", 20)) {
                    continue;
                }

                $savedTickets++;
                $refund = $this->createTicketRefund($this->menu->getInventory()->getItem($slot));
                foreach ($recipient->getInventory()->addItem($refund) as $leftover) {
                    $session?->addItemToCollection($leftover);
                }
            }
        }

        if ($recipient instanceof Player && $recipient->isConnected()) {
            RewardAnnouncer::announce($recipient, "Slotbot", "Slotbot", $rewards);
            PlayerUtils::playSound($recipient, "random.levelup");
        }

        $this->status = self::STATUS_DONE;
        $this->pendingRewards = [];
    }

    /**
     * @return int[]
     */
    private function getEmptyTicketSlots(): array
    {
        $inventory = $this->menu->getInventory();
        return array_values(array_filter(
            range(47, 51),
            fn(int $slot): bool => $inventory->getItem($slot)->getNamedTag()->getTag(self::TAG_EMPTY_SLOT) !== null
        ));
    }

    /**
     * @return int[]
     */
    private function getFilledTicketSlots(): array
    {
        return array_values(array_diff(range(47, 51), $this->getEmptyTicketSlots()));
    }

    private function createAddTicketItem(bool $full): Item
    {
        if ($full) {
            return $this->createMenuItem(VanillaItems::DYE()->setColor(DyeColor::LIME()), "&r&l&aAdd Tickets &r&7(Full)", self::TAG_ADD_TICKETS, [
                "&r&7All ticket slots are already filled."
            ]);
        }

        return $this->createMenuItem(VanillaItems::DYE()->setColor(DyeColor::LIME()), "&r&l&aAdd Tickets", self::TAG_ADD_TICKETS, [
            "&r&7Click here to insert one slot ticket."
        ]);
    }

    private function createAddAllTicketsItem(bool $full): Item
    {
        if ($full) {
            return $this->createMenuItem(VanillaItems::DYE()->setColor(DyeColor::YELLOW()), "&r&l&eAdd All &r&7(Full)", self::TAG_ADD_ALL_TICKETS, [
                "&r&7All ticket slots are already filled."
            ]);
        }

        return $this->createMenuItem(VanillaItems::DYE()->setColor(DyeColor::YELLOW()), "&r&l&eAdd All", self::TAG_ADD_ALL_TICKETS, [
            "&r&7Fill every open slot with tickets from your inventory."
        ]);
    }

    private function createSpinItem(bool $ready): Item
    {
        if (!$ready) {
            return $this->createMenuItem(VanillaItems::MAGMA_CREAM(), "&r&l&cSpin &r&7(Missing Tickets)", self::TAG_SPIN, [
                "&r&7Insert at least one slot ticket first."
            ]);
        }

        return $this->createMenuItem(VanillaItems::SLIMEBALL(), "&r&l&aSpin", self::TAG_SPIN, [
            "&r&7Click to spin the slotbot."
        ]);
    }

    private function createCreditShopItem(): Item
    {
        $credits = Loader::getPlayerManager()->getSession($this->player)?->getSlotCredits() ?? 0;
        return $this->createMenuItem(VanillaItems::CLOCK(), "&r&l&eCredit Shop &r&7(" . number_format($credits) . " Credits)", self::TAG_CREDIT_SHOP, [
            "&r&7Spend slotbot credits in the credit shop.",
            "&r&7This screen is being set up."
        ]);
    }

    private function createEmptyTicketSlotItem(int $count): Item
    {
        $item = $this->createMenuItem(VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::RED())->asItem(), "&r&l&cLOCKED", self::TAG_EMPTY_SLOT, [
            "&r&7Insert a slotbot ticket to unlock this slot."
        ]);
        $item->setCount($count);
        return $item;
    }

    private function createMenuItem(Item $item, string $name, string $tagName, array $lore = []): Item
    {
        $item->setCustomName(C::colorize($name));
        if ($lore !== []) {
            $item->setLore(array_map(static fn(string $line): string => C::colorize($line), $lore));
        }

        $tag = $item->getNamedTag();
        $tag->setByte($tagName, 1);
        $item->setNamedTag($tag);
        return $item;
    }

    private function getSpinReward(): Item
    {
        return SlotbotRewardRepository::getSpinReward($this->player) ?? VanillaItems::BARRIER()->setCustomName(C::colorize("&r&cMissing Reward"));
    }

    private function getJackpotReward(): Item
    {
        return SlotbotRewardRepository::getJackpotReward($this->player) ?? $this->getSpinReward();
    }

    private function getDisplayReward(): Item
    {
        return SlotbotRewardRepository::getDisplayReward() ?? VanillaItems::BARRIER()->setCustomName(C::colorize("&r&cMissing Reward"));
    }

    private function getJackpotDisplayReward(): Item
    {
        return SlotbotRewardRepository::getJackpotDisplayReward() ?? $this->getDisplayReward();
    }

    private function findFirstTicket(Player $player): ?array
    {
        $inventory = $player->getInventory();
        foreach ($inventory->getContents() as $slot => $item) {
            if (GlaciaItemFactory::isGlaciaItem($item, "slot_ticket") || GlaciaItemFactory::isGlaciaItem($item, "heroic_slot_ticket")) {
                return ["slot" => $slot, "item" => $item];
            }
        }

        return null;
    }

    private function createTicketRefund(Item $item): Item
    {
        return GlaciaItemFactory::isGlaciaItem($item, "heroic_slot_ticket")
            ? GlaciaItemFactory::heroicSlotTicket()
            : GlaciaItemFactory::slotTicket();
    }

    private function getRewardName(Item $item): string
    {
        $customName = $item->getCustomName();
        return $customName !== "" ? $customName : $item->getName();
    }
}
