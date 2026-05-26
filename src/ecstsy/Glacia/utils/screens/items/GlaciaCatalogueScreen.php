<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\items;

use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class GlaciaCatalogueScreen extends BaseScreen
{
    private const ITEMS_PER_PAGE = 45;
    private const HIDDEN_ITEM_IDS = [
        "zone_barrier_bucket",
        "enchantment_book",
    ];

    private InvMenu $menu;
    /** @var string[] */
    private array $items = [];

    public function __construct(
        private readonly Player $viewer,
        private readonly string $category,
        private int $page = 1
    ) {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            if ($slot === 45) {
                $player->removeCurrentWindow();
                $transaction->then(static function() use ($player): void {
                    self::openCategoryMenu($player);
                });
                return;
            }

            if ($slot === 48 && $this->page > 1) {
                $this->page--;
                $this->build();
                return;
            }

            if ($slot === 49) {
                $this->build();
                return;
            }

            if ($slot === 50 && $this->page < $this->getMaxPage()) {
                $this->page++;
                $this->build();
                return;
            }

            if ($slot === 53) {
                $player->removeCurrentWindow();
            }
        }));

        $this->build();
    }

    public static function openCategoryMenu(Player $player): void
    {
        $categories = self::getCatalogueCategories();
        if ($categories === []) {
            $player->sendMessage(C::colorize("&r&cNo Glacia item categories are available right now."));
            return;
        }

        $form = new SimpleForm(function(Player $player, $data) use ($categories): void {
            if (!is_int($data)) {
                return;
            }

            $keys = array_keys($categories);
            $category = $keys[$data] ?? null;
            if ($category === null) {
                return;
            }

            (new self($player, (string) $category))->display($player);
        });

        $form->setTitle(C::colorize("&r&8Glacia Item Catalogue"));
        $form->setContent(C::colorize("&r&7Browse public previews of Glacia items, lootboxes, crates, and more."));
        foreach ($categories as $key => $display) {
            $count = count(GiveItemMenuScreen::getCategoryItems($key, $player));
            $form->addButton(C::colorize("&r&f{$display}\n&r&8{$count} item(s)"));
        }
        $player->sendForm($form);
    }

    public static function resolveCategory(string $lookup, ?Player $player = null): ?string
    {
        $normalizedLookup = self::normalizeIdentifier($lookup);
        foreach (self::getCatalogueCategories() as $key => $display) {
            if (self::normalizeIdentifier($key) === $normalizedLookup || self::normalizeIdentifier($display) === $normalizedLookup) {
                return $key;
            }
        }

        return null;
    }

    public function display(Player $player): void
    {
        $this->menu->send($player);
    }

    public function getMenu(): InvMenu
    {
        return $this->menu;
    }

    private function build(): void
    {
        $this->items = array_values(array_filter(
            GiveItemMenuScreen::getCategoryItems($this->category, $this->viewer),
            fn(string $itemId): bool => $this->shouldDisplayItem($itemId)
        ));
        $maxPage = $this->getMaxPage();
        $this->page = max(1, min($maxPage, $this->page));

        $inventory = $this->menu->getInventory();
        $inventory->clearAll();

        $this->menu->setName(C::colorize("&r&8" . GiveItemMenuScreen::getCategoryDisplay($this->category) . " &7({$this->page}/{$maxPage})"));

        $pageItems = array_slice($this->items, ($this->page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE);
        foreach ($pageItems as $slot => $itemId) {
            $inventory->setItem($slot, $this->buildPreviewItem($itemId));
        }

        for ($slot = 45; $slot <= 53; $slot++) {
            $inventory->setItem($slot, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" "));
        }

        $categories = VanillaItems::BOOK();
        $categories->setCustomName(C::colorize("&r&eCategories"));
        $categories->setLore([C::colorize("&r&7Return to the catalogue category list.")]);
        $inventory->setItem(45, $categories);

        if ($this->page > 1) {
            $previous = VanillaItems::PAPER();
            $previous->setCustomName(C::colorize("&r&ePrevious Page"));
            $inventory->setItem(48, $previous);
        }

        $info = VanillaItems::COMPASS();
        $info->setCustomName(C::colorize("&r&d" . GiveItemMenuScreen::getCategoryDisplay($this->category)));
        $info->setLore([
            C::colorize("&r&7Showing &f" . count($this->items) . " &r&7registered preview item(s)."),
            C::colorize("&r&7Tap an item to inspect its lore client-side."),
        ]);
        $inventory->setItem(49, $info);

        if ($this->page < $maxPage) {
            $next = VanillaItems::PAPER();
            $next->setCustomName(C::colorize("&r&eNext Page"));
            $inventory->setItem(50, $next);
        }

        $close = VanillaBlocks::BARRIER()->asItem();
        $close->setCustomName(C::colorize("&r&cClose"));
        $inventory->setItem(53, $close);
    }

    private function buildPreviewItem(string $itemId): \pocketmine\item\Item
    {
        $item = GiveItemMenuScreen::buildMenuItem($itemId, $this->viewer) ?? VanillaBlocks::BARRIER()->asItem();
        $item->setCount(1);

        $lore = $item->getLore();
        $lore[] = "";
        $lore[] = C::colorize("&r&8Catalogue ID: &f" . GiveItemMenuScreen::getMenuItemSubtitle($itemId));
        if (GiveItemMenuScreen::requiresParameters($itemId)) {
            $lore[] = C::colorize("&r&8Preview uses default values.");
        }
        $item->setLore($lore);

        return $item;
    }

    private function getMaxPage(): int
    {
        return max(1, (int) ceil(max(1, count($this->items)) / self::ITEMS_PER_PAGE));
    }

    private static function normalizeIdentifier(string $value): string
    {
        return strtolower(str_replace([" ", "_", "-"], "", trim($value)));
    }

    private function shouldDisplayItem(string $itemId): bool
    {
        $normalized = strtolower(trim($itemId));
        if (in_array($normalized, self::HIDDEN_ITEM_IDS, true)) {
            return false;
        }

        return GiveItemMenuScreen::buildMenuItem($itemId, $this->viewer) !== null;
    }

    /**
     * @return array<string, string>
     */
    private static function getCatalogueCategories(): array
    {
        return array_filter(
            GiveItemMenuScreen::getCategoryDisplays(),
            static fn(string $key): bool => $key !== "__recent__",
            ARRAY_FILTER_USE_KEY
        );
    }
}
