<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\collectionchests;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\collectionchests\CollectionChestManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;

final class CollectionChestScreen {

    private const ITEMS_PER_PAGE = 45;
    /** @var array<string, array<int, self>> */
    private static array $openScreens = [];

    private InvMenu $menu;
    private int $page = 1;

    public function __construct(
        private Player $viewer,
        private Position $position
    ) {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize($this->isHarvesterChest() ? "&r&8Harvester Chest" : "&r&8Collection Chest"));
        $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();
            $manager = Loader::getCollectionChestManager();

            if ($slot === 45 && $this->page > 1) {
                $this->page--;
                $this->build();
                return;
            }

            if ($slot === 49) {
                $result = $manager->sellAll($this->position, $player);
                if (($result["money"] ?? 0.0) <= 0.0) {
                    $player->sendMessage(C::colorize("&r&cThis chest has nothing sellable right now."));
                    PlayerUtils::playSound($player, "note.bass");
                } else {
                    $multiplierText = ($result["multiplier"] ?? 1.0) > 1.0 ? " &r&7(x" . rtrim(rtrim(number_format((float) $result["multiplier"], 2), "0"), ".") . " Multiplier)" : "";
                    $player->sendMessage(C::colorize("&r&aSold &f" . number_format((int) $result["items"]) . " &aitems from this chest for &b$" . number_format((float) $result["money"], 2) . "{$multiplierText}&r&a."));
                    PlayerUtils::playSound($player, "random.orb");
                }
                $this->build();
                return;
            }

            if ($slot === 50) {
                $claimed = $manager->claimStoredXp($this->position, $player);
                if ($claimed <= 0) {
                    $player->sendMessage(C::colorize("&r&cThere is no stored XP to claim."));
                    PlayerUtils::playSound($player, "note.bass");
                } else {
                    $player->sendMessage(C::colorize("&r&aClaimed &f" . number_format($claimed) . " &aXP from this chest."));
                    PlayerUtils::playSound($player, "random.levelup");
                }
                $this->build();
                return;
            }

            if ($slot === 53 && $this->page * self::ITEMS_PER_PAGE < count($manager->getDisplayEntries($this->position))) {
                $this->page++;
                $this->build();
                return;
            }

            if ($slot >= 0 && $slot < self::ITEMS_PER_PAGE) {
                $entries = $this->getPageEntries();
                $entry = $entries[$slot] ?? null;
                if ($entry === null) {
                    return;
                }

                $withdrawn = $manager->withdrawEntry($this->position, (string) $entry["fingerprint"]);
                if ($withdrawn === []) {
                    $player->sendMessage(C::colorize("&r&cThat entry is no longer available."));
                    PlayerUtils::playSound($player, "note.bass");
                    $this->build();
                    return;
                }

                $overflowed = false;
                foreach ($withdrawn as $item) {
                    foreach ($player->getInventory()->addItem($item) as $overflow) {
                        $manager->restoreOverflow($this->position, $overflow);
                        $overflowed = true;
                    }
                }

                if ($overflowed) {
                    $player->sendMessage(C::colorize("&r&eSome of those items stayed in this chest because your inventory is full."));
                } else {
                    $player->sendMessage(C::colorize("&r&aWithdrawn items from this chest."));
                }
                PlayerUtils::playSound($player, "random.pop");
                $this->build();
            }
        }));
        $this->menu->setInventoryCloseListener(function(Player $player, Inventory $inventory): void {
            unset(self::$openScreens[$this->getPositionKey()][$player->getId()]);
            if ((self::$openScreens[$this->getPositionKey()] ?? []) === []) {
                unset(self::$openScreens[$this->getPositionKey()]);
            }
        });

        $this->build();
    }

    public static function display(Player $player, Position $position): void
    {
        $screen = new self($player, $position);
        self::$openScreens[$screen->getPositionKey()][$player->getId()] = $screen;
        $screen->menu->send($player);
    }

    public static function refreshFor(Position $position): void
    {
        $key = self::positionKey($position);
        foreach (self::$openScreens[$key] ?? [] as $screen) {
            $screen->build();
        }
    }

    private function build(): void
    {
        $inv = $this->menu->getInventory();
        $inv->clearAll();

        foreach ($this->getPageEntries() as $slot => $entry) {
            $inv->setItem($slot, $entry["item"]);
        }

        for ($i = 45; $i <= 53; $i++) {
            $inv->setItem($i, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(C::colorize("&r ")));
        }

        if ($this->page > 1) {
            $prev = VanillaItems::PAPER();
            $prev->setCustomName(C::colorize("&r&ePrevious Page"));
            $inv->setItem(45, $prev);
        }

        $info = VanillaBlocks::CHEST()->asItem();
        if ($this->isHarvesterChest()) {
            $info->setCustomName(C::colorize("&r&eFarm Collector"));
            $info->setLore([
                C::colorize("&r&7Collects crop and farm block drops"),
                C::colorize("&r&7from this chunk (breaks and loose"),
                C::colorize("&r&7items such as invalid sugar cane)."),
            ]);
        } else {
            $info->setCustomName(C::colorize("&r&eChunk Collector"));
            $info->setLore([
                C::colorize("&r&7Collects mob drops and XP from"),
                C::colorize("&r&7this chunk while it remains placed."),
            ]);
        }
        $inv->setItem(47, $info);

        $sellAll = VanillaItems::GOLD_INGOT();
        $sellAll->setCustomName(C::colorize("&r&aSell All"));
        $sellAll->setLore([
            C::colorize("&r&7Sell every sellable item stored"),
            C::colorize("&r&7inside this chest."),
        ]);
        $inv->setItem(49, $sellAll);

        $xpItem = VanillaItems::EXPERIENCE_BOTTLE();
        $xpItem->setCustomName(C::colorize("&r&bStored XP"));
        $xpItem->setLore([
            C::colorize("&r&7Currently holding &f" . number_format(Loader::getCollectionChestManager()->getStoredXp($this->position)) . " XP"),
            C::colorize("&r&eClick to claim it."),
        ]);
        $inv->setItem(50, $xpItem);

        if ($this->page * self::ITEMS_PER_PAGE < count(Loader::getCollectionChestManager()->getDisplayEntries($this->position))) {
            $next = VanillaItems::PAPER();
            $next->setCustomName(C::colorize("&r&eNext Page"));
            $inv->setItem(53, $next);
        }
    }

    private function getPageEntries(): array
    {
        return array_slice(
            Loader::getCollectionChestManager()->getDisplayEntries($this->position),
            ($this->page - 1) * self::ITEMS_PER_PAGE,
            self::ITEMS_PER_PAGE,
            false
        );
    }

    private function getPositionKey(): string
    {
        return self::positionKey($this->position);
    }

    private static function positionKey(Position $position): string
    {
        return $position->getWorld()->getFolderName() . ":" . $position->getFloorX() . ":" . $position->getFloorY() . ":" . $position->getFloorZ();
    }

    private function isHarvesterChest(): bool
    {
        $entry = Loader::getCollectionChestManager()->getChestAt($this->position);
        if ($entry === null) {
            return false;
        }

        return ((string) ($entry["chest_type"] ?? CollectionChestManager::CHEST_TYPE_COLLECTION)) === CollectionChestManager::CHEST_TYPE_HARVESTER;
    }
}
