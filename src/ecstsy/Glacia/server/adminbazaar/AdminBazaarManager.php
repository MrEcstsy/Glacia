<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\adminbazaar;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AdminBazaarManager
{
    /** @var array<string, mixed> */
    private static array $config = [];
    /** @var array<string, mixed>|null */
    private static ?array $activeListing = null;
    private static int $nextRefreshAt = 0;
    private static bool $stateLoaded = false;

    public static function loadFromConfig() : void
    {
        self::$config = GeneralUtils::getConfiguration(Loader::getInstance(), "admin_bazaar.yml")?->getAll() ?? [];

        if (!self::$stateLoaded) {
            self::loadState();
        }

        if (!self::isEnabled()) {
            self::$activeListing = null;
            self::$nextRefreshAt = 0;
            self::saveState();
            return;
        }

        if (self::$nextRefreshAt <= 0) {
            if ((bool) (self::getSettings()["start-immediately"] ?? true)) {
                self::rollNextListing(true);
            } else {
                self::$nextRefreshAt = time() + self::getRotationSeconds();
                self::saveState();
            }
        }
    }

    public static function isEnabled() : bool
    {
        return (bool) (self::getSettings()["enabled"] ?? true);
    }

    public static function tick() : void
    {
        if (!self::isEnabled()) {
            return;
        }

        if (!self::$stateLoaded) {
            self::loadState();
        }

        $now = time();
        if (self::$activeListing !== null && (int) (self::$activeListing["ends_at"] ?? 0) <= $now) {
            self::$activeListing = null;
            self::saveState();
        }

        if (self::$nextRefreshAt <= 0) {
            self::$nextRefreshAt = $now + self::getRotationSeconds();
            self::saveState();
            return;
        }

        if ($now >= self::$nextRefreshAt) {
            self::rollNextListing(true);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getActiveListing() : ?array
    {
        if (self::$activeListing === null) {
            return null;
        }

        return self::$activeListing;
    }

    public static function getTimeUntilRefresh() : int
    {
        return max(0, self::$nextRefreshAt - time());
    }

    public static function getTimeUntilExpiry() : int
    {
        if (self::$activeListing === null) {
            return 0;
        }

        return max(0, ((int) (self::$activeListing["ends_at"] ?? 0)) - time());
    }

    public static function rollNextListing(bool $announce = true, ?string $forcedKey = null) : bool
    {
        $pool = self::getEnabledListings();
        if ($pool === []) {
            self::$activeListing = null;
            self::$nextRefreshAt = time() + self::getRotationSeconds();
            self::saveState();
            return false;
        }

        $entryKey = $forcedKey !== null && isset($pool[$forcedKey]) ? $forcedKey : self::rollPoolKey($pool);
        if ($entryKey === null) {
            return false;
        }

        $entry = $pool[$entryKey];
        $reward = RewardResolver::resolveItem(self::buildRewardEntry($entry), null);
        if (!$reward instanceof Item) {
            Loader::getInstance()->getLogger()->warning("[AdminBazaar] Failed to resolve listing reward for '{$entryKey}'.");
            return false;
        }

        $now = time();
        self::$activeListing = [
            "id" => strtolower($entryKey) . ":" . $now,
            "key" => strtolower($entryKey),
            "name" => (string) ($entry["display-name"] ?? $reward->getName()),
            "item" => clone $reward,
            "item_data" => ItemUtils::encodeItem($reward),
            "price" => [
                "currency" => strtolower((string) (($entry["price"]["currency"] ?? null) ?? "money")),
                "amount" => max(1, GeneralUtils::parseRandomNumber(($entry["price"]["amount"] ?? 1))),
            ],
            "permission" => trim((string) ($entry["permission"] ?? "")),
            "purchase_once" => (bool) ($entry["purchase-once"] ?? true),
            "started_at" => $now,
            "ends_at" => $now + self::getAvailabilitySeconds(),
        ];
        self::$nextRefreshAt = $now + self::getRotationSeconds();
        self::saveState();

        if ($announce) {
            self::broadcast(
                "new-listing",
                [
                    "item" => C::clean((string) self::$activeListing["name"]),
                    "price" => self::formatPrice(
                        (int) self::$activeListing["price"]["amount"],
                        (string) self::$activeListing["price"]["currency"]
                    ),
                    "duration" => GeneralUtils::translateTime(self::getAvailabilitySeconds()),
                ]
            );
        }

        return true;
    }

    public static function purchase(Player $player) : bool
    {
        $listing = self::$activeListing;
        if ($listing === null || self::getTimeUntilExpiry() <= 0) {
            $player->sendMessage(GlaciaUtils::format("adminbazaar.error.no_active_listing"));
            return false;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        if (!$session instanceof GlaciaPlayer) {
            $player->sendMessage(GlaciaUtils::format("adminbazaar.error.session_load"));
            return false;
        }

        if (!self::canAccessListing($player, $listing)) {
            $player->sendMessage(GlaciaUtils::format("adminbazaar.error.no_access"));
            return false;
        }

        if ((bool) ($listing["purchase_once"] ?? true) && self::hasPurchased($session, (string) $listing["id"])) {
            $player->sendMessage(GlaciaUtils::format("adminbazaar.error.already_purchased"));
            return false;
        }

        $currency = (string) (($listing["price"]["currency"] ?? "money"));
        $amount = (int) (($listing["price"]["amount"] ?? 0));
        if ($amount <= 0) {
            $player->sendMessage(GlaciaUtils::format("adminbazaar.error.misconfigured"));
            return false;
        }

        if (!self::charge($session, $currency, $amount)) {
            $player->sendMessage(GlaciaUtils::format("adminbazaar.error.not_enough_currency", [
                "price" => self::formatPrice($amount, $currency),
            ]));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $item = self::decodeListingItem($listing);
        if ($item === null) {
            self::refund($session, $currency, $amount);
            $player->sendMessage(GlaciaUtils::format("adminbazaar.error.claim_failed"));
            return false;
        }

        $leftovers = $player->getInventory()->addItem(clone $item);
        if ($leftovers !== [] && (bool) (self::getSettings()["send-to-collection-if-full"] ?? true)) {
            foreach ($leftovers as $leftover) {
                $session->addItemToCollection($leftover);
            }
        } else {
            foreach ($leftovers as $leftover) {
                $player->getWorld()->dropItem($player->getPosition(), $leftover);
            }
        }

        if ((bool) ($listing["purchase_once"] ?? true)) {
            self::markPurchased($session, (string) $listing["id"]);
        }
        $player->sendMessage(GlaciaUtils::format("adminbazaar.success.purchased", [
            "item" => C::clean((string) $listing["name"]),
        ]));
        PlayerUtils::playSound($player, "random.levelup");

        self::broadcast(
            "purchased",
            [
                "player" => $player->getName(),
                "item" => C::clean((string) $listing["name"]),
            ]
        );
        Loader::getQuestBoardManager()->recordProgress($player, "admin_bazaar_purchases", 1, [
            "listing" => strtolower((string) ($listing["key"] ?? "")),
        ]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getVoucherDefinition() : array
    {
        $voucher = (array) (self::$config["admin-coins"]["voucher"] ?? []);
        return [
            "material" => (string) ($voucher["material"] ?? "sunflower"),
            "name" => (string) ($voucher["name"] ?? "&r&l&6Admin Coin Voucher"),
            "lore" => is_array($voucher["lore"] ?? null) ? $voucher["lore"] : [
                "&r&7Right-click to redeem this voucher",
                "&r&7for &f{amount} Admin Coins&7.",
            ],
        ];
    }

    public static function getStatusLines() : array
    {
        $lines = [];
        $listing = self::$activeListing;
        if ($listing !== null && self::getTimeUntilExpiry() > 0) {
            $lines[] = GlaciaUtils::format("adminbazaar.status.active", [
                "item" => C::clean((string) $listing["name"]),
            ]);
            $lines[] = GlaciaUtils::format("adminbazaar.status.price", [
                "price" => self::formatPrice((int) $listing["price"]["amount"], (string) $listing["price"]["currency"]),
            ]);
            $lines[] = GlaciaUtils::format("adminbazaar.status.ends_in", [
                "time" => GeneralUtils::translateTime(self::getTimeUntilExpiry()),
            ]);
        } else {
            $lines[] = GlaciaUtils::format("adminbazaar.status.none");
        }

        $lines[] = GlaciaUtils::format("adminbazaar.status.next_rotation", [
            "time" => GeneralUtils::translateTime(self::getTimeUntilRefresh()),
        ]);
        return $lines;
    }

    private static function canAccessListing(Player $player, array $listing) : bool
    {
        $globalPermission = trim((string) (self::getSettings()["required-permission"] ?? ""));
        if ($globalPermission !== "" && !$player->hasPermission($globalPermission)) {
            return false;
        }

        $listingPermission = trim((string) ($listing["permission"] ?? ""));
        return $listingPermission === "" || $player->hasPermission($listingPermission);
    }

    private static function charge(GlaciaPlayer $session, string $currency, int $amount) : bool
    {
        return match (strtolower($currency)) {
            "money", "balance" => self::chargeMoney($session, $amount),
            "admin_coin", "admin_coins", "admincoins" => $session->removeAdminCoins($amount),
            "itemflip", "item_flip", "item_flip_credits" => $session->removeItemFlipCredits($amount),
            "slot", "slot_credits", "slotcredits" => $session->removeSlotCredits($amount),
            default => false,
        };
    }

    private static function refund(GlaciaPlayer $session, string $currency, int $amount) : void
    {
        match (strtolower($currency)) {
            "money", "balance" => $session->addBalance($amount),
            "admin_coin", "admin_coins", "admincoins" => $session->addAdminCoins($amount),
            "itemflip", "item_flip", "item_flip_credits" => $session->addItemFlipCredits($amount),
            "slot", "slot_credits", "slotcredits" => $session->addSlotCredits($amount),
            default => null,
        };
    }

    public static function formatPrice(int $amount, string $currency) : string
    {
        return match (strtolower($currency)) {
            "money", "balance" => "$" . number_format($amount),
            "admin_coin", "admin_coins", "admincoins" => number_format($amount) . " Admin Coins",
            "itemflip", "item_flip", "item_flip_credits" => number_format($amount) . " Item Flip Credits",
            "slot", "slot_credits", "slotcredits" => number_format($amount) . " Slot Credits",
            default => number_format($amount) . " " . ucfirst(str_replace("_", " ", $currency)),
        };
    }

    private static function chargeMoney(GlaciaPlayer $session, int $amount) : bool
    {
        if ($session->getBalance() < $amount) {
            return false;
        }

        $session->removeBalance($amount);
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getSettings() : array
    {
        return is_array(self::$config["settings"] ?? null) ? self::$config["settings"] : [];
    }

    private static function getRotationSeconds() : int
    {
        return max(60, (int) (self::getSettings()["rotation-seconds"] ?? 86400));
    }

    private static function getAvailabilitySeconds() : int
    {
        return max(60, (int) (self::getSettings()["availability-seconds"] ?? 21600));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function getEnabledListings() : array
    {
        $listings = (array) (self::$config["listings"] ?? []);
        $enabled = [];
        foreach ($listings as $key => $entry) {
            if (!is_array($entry) || !((bool) ($entry["enabled"] ?? true))) {
                continue;
            }
            $enabled[strtolower((string) $key)] = $entry;
        }
        return $enabled;
    }

    private static function rollPoolKey(array $pool) : ?string
    {
        $total = 0;
        $weighted = [];
        foreach ($pool as $key => $entry) {
            $chance = max(1, (int) ($entry["chance"] ?? 100));
            $weighted[$key] = $chance;
            $total += $chance;
        }

        if ($total <= 0) {
            return null;
        }

        $roll = mt_rand(1, $total);
        $running = 0;
        foreach ($weighted as $key => $chance) {
            $running += $chance;
            if ($roll <= $running) {
                return (string) $key;
            }
        }

        return array_key_first($pool);
    }

    /**
     * Lets Admin Bazaar listings define item modifiers either inside `reward:`
     * or alongside the listing itself.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function buildRewardEntry(array $entry): array
    {
        $reward = is_array($entry["reward"] ?? null) ? (array) $entry["reward"] : [];

        foreach ([
            "type",
            "id",
            "material",
            "item",
            "identifier",
            "name",
            "display",
            "count",
            "amount",
            "lore",
            "enchantments",
            "color",
            "rune",
            "applied_rune",
            "preapplied_rune",
            "bound_rune",
            "rune_id",
            "essence",
            "essence_id",
            "essences",
            "protected",
            "whitescrolled",
            "white_scrolled",
            "white_scroll",
            "holy_white",
            "holy_whitescroll",
            "holy_white_scroll",
            "holywhitescroll",
            "holy_white_max",
            "holy_white_used",
            "holy_white_active",
            "holy_water",
            "holywater",
            "soul_conduit",
            "soul_conduit_type",
            "conduit",
            "conduit_type",
            "soul_identifier",
            "soul_id",
            "conduit_identifier",
            "conduit_id",
            "bind_id",
            "soul_gem_id",
            "soul_group",
            "conduit_group",
            "heroic",
            "is_heroic",
            "heroic_armor",
            "transmogged",
            "pretransmogged",
            "apply_transmog",
            "transmogged_key",
            "enchanted",
            "success",
            "destroy",
            "souls",
            "uses",
            "pet",
            "pet_id",
            "multiplier",
            "duration",
            "zone",
            "zone_id",
            "orb_type",
            "max",
            "slots",
            "new",
            "bonus",
            "bonus_success",
            "percent",
            "credits",
            "tracker",
            "tracker_type",
            "trackers",
            "title",
            "title_id",
            "disguise",
            "mob",
            "type_id",
            "kit",
            "gkit",
            "warlord",
        ] as $key) {
            if (array_key_exists($key, $entry) && !array_key_exists($key, $reward)) {
                $reward[$key] = $entry[$key];
            }
        }

        if (!isset($reward["name"]) && isset($entry["display-name"])) {
            $reward["name"] = $entry["display-name"];
        }

        return $reward;
    }

    private static function getStatePath() : string
    {
        return Loader::getInstance()->getDataFolder() . "admin_bazaar_state.json";
    }

    private static function loadState() : void
    {
        self::$stateLoaded = true;
        $payload = StorageUtils::readJsonFile(self::getStatePath(), "admin bazaar state");
        if ($payload === null) {
            return;
        }

        self::$nextRefreshAt = max(0, (int) ($payload["next_refresh_at"] ?? 0));
        $listing = $payload["active_listing"] ?? null;
        if (!is_array($listing)) {
            self::$activeListing = null;
            return;
        }

        $decoded = self::decodeListingItem($listing);
        if ($decoded === null) {
            self::$activeListing = null;
            return;
        }

        $listing["item"] = $decoded;
        self::$activeListing = $listing;
    }

    private static function saveState() : void
    {
        $listing = self::$activeListing;
        if ($listing !== null && isset($listing["item"]) && $listing["item"] instanceof Item) {
            $listing["item_data"] = ItemUtils::encodeItem($listing["item"]);
            unset($listing["item"]);
        }

        StorageUtils::writeJsonFile(
            self::getStatePath(),
            [
                "next_refresh_at" => self::$nextRefreshAt,
                "active_listing" => $listing,
            ],
            "admin bazaar state"
        );
    }

    private static function decodeListingItem(array $listing) : ?Item
    {
        if (isset($listing["item"]) && $listing["item"] instanceof Item) {
            return clone $listing["item"];
        }

        $encoded = $listing["item_data"] ?? null;
        if (!is_string($encoded) || trim($encoded) === "") {
            return null;
        }

        return ItemUtils::decodeItem($encoded);
    }

    private static function broadcast(string $key, array $replacements) : void
    {
        $message = MessageTemplates::render(
            "adminbazaar.broadcast." . str_replace("-", "_", $key),
            $replacements,
            (string) ((self::getSettings()["broadcasts"][$key] ?? "") ?? "")
        );
        if ($message === "") {
            return;
        }

        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $online) {
            $online->sendMessage(C::colorize($message));
        }
    }

    private static function hasPurchased(GlaciaPlayer $session, string $listingId) : bool
    {
        $purchases = $session->getSetting("admin_bazaar_purchases");
        return is_array($purchases) && isset($purchases[$listingId]);
    }

    private static function markPurchased(GlaciaPlayer $session, string $listingId) : void
    {
        $purchases = $session->getSetting("admin_bazaar_purchases");
        if (!is_array($purchases)) {
            $purchases = [];
        }

        $purchases[$listingId] = time();
        $session->setSetting("admin_bazaar_purchases", $purchases);
    }
}
