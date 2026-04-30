<?php

declare(strict_types=1);

namespace ecstsy\Glacia\player;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\Faction;
use ecstsy\Glacia\server\ranks\Rank;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\server\scoreboard\ScoreboardHelper;
use ecstsy\Glacia\utils\ChatTypes;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat as C;
use pocketmine\player\Player;
use pocketmine\Server;
use Ramsey\Uuid\UuidInterface;
use JsonException;

final class GlaciaPlayer {

    public const MAX_BALANCE = 9_000_000_000.0;

    private bool $isConnected = false;
    private bool $adminMode = false;
    private string $chat = ChatTypes::ALL;
    private int $gapplesEaten = 0;
    private int $lastGappleAte = 0;

    public function __construct(
        private UuidInterface $uuid,
        private string $username,
        private float $balance,
        private string $cooldowns,
        private ?string $faction_id,
        private ?string $faction_role,
        private int $power,
        private string $rank,
        private string $settings,
        private string $buffs,
        private float $sellMultiplier,
        private string $collection,
        private int $kills,
        private int $deaths,
        private int $slotCredits,
        private int $itemFlipCredits,
        private ?string $title,
    )
    {
        
    }

    public function isConnected(): bool {
        return $this->isConnected;
    }

    public function setConnected(bool $connected): void {
        $this->isConnected = $connected;
    }

    public function getUuid(): UuidInterface {
        return $this->uuid;
    }

    public function getPlayer(): ?Player     {
        return Server::getInstance()->getPlayerByUUID($this->uuid);
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function isInAdminMode(): bool
    {
        return $this->adminMode;
    }

    public function setInAdminMode(bool $value): void
    {
        $this->adminMode = $value;
    }

    public function getCurrentChat(): string
    {
        return $this->chat;
    }

    public function setCurrentChat(string $chat): void
    {
        $this->chat = $chat;
        $this->updateDb();
        $player = $this->getPlayer();
        if ($player !== null) {
            ScoreboardHelper::updateChatLine($player);
        }
    }

    public function getBalance(): float {
        return $this->balance;
    }

    public function getRemainingBalanceCapacity(): float
    {
        return max(0.0, self::MAX_BALANCE - $this->balance);
    }

    public function canReceiveBalance(float $amount): bool
    {
        return $amount > 0 && $this->getRemainingBalanceCapacity() >= $amount;
    }

    public function addBalance(float $amount): void {
        if ($amount <= 0) {
            return;
        }

        $player = $this->getPlayer();
        $amount = round($amount, 2);

        $remainingAmount = self::MAX_BALANCE - $this->balance;
        $amountToAdd = min($amount, $remainingAmount);

        if ($amountToAdd <= 0) {
            if ($player !== null) {
                $player->sendMessage(C::colorize("&r&cYou have reached the maximum amount of money."));
            }
            return;
        }

        $this->balance = min(self::MAX_BALANCE, round($this->balance + $amountToAdd, 2));
        $this->updateDb();
        if ($player !== null) {
            ScoreboardHelper::updateBalanceLine($player);
        }
    }

    public function removeBalance(float $amount): void {
        if ($amount <= 0) {
            return;
        }

        $amount = round($amount, 2);
        $this->balance = max(0.0, round($this->balance - $amount, 2));
        $this->updateDb();
        $player = $this->getPlayer();
        if ($player !== null) {
            ScoreboardHelper::updateBalanceLine($player);
        }
    }

    public function setBalance(float $amount): void {
        $this->balance = min(self::MAX_BALANCE, max(0.0, round($amount, 2)));
        $this->updateDb();
        $player = $this->getPlayer();
        if ($player !== null) {
            ScoreboardHelper::updateBalanceLine($player);
        }
    }

    public function getFactionId(): ?string {
        return $this->faction_id;
    }

    public function setFactionId(?string $factioId): void {
        $this->faction_id = $factioId;
        $this->updateDb();
        $player = $this->getPlayer();
        if ($player !== null) {
            ScoreboardHelper::updateFactionLines($player);
        }
    }

    public function getFactionRole(): ?string {
        if ($this->faction_role === null) {
            return null;
        }

        return $this->faction_role;
    }

    public function setFactionRole(?string $factionRole): void {
        $this->faction_role = $factionRole ?? null;
        $this->updateDb();
        $player = $this->getPlayer();
        if ($player !== null) {
            ScoreboardHelper::updateFactionLines($player);
        }
    }

    public function getFaction(): ?Faction {
        if ($this->faction_id === null) {
            return null;
        }
        return Loader::getFactionManager()->getFaction($this->faction_id);
    }

    public function getRank(): string {
        return $this->rank;
    }

    public function getRankObject(): ?Rank {
        return RankManager::getInstance()->getRank($this->rank);
    }

    public function setRank(string $rank): void {
        $this->rank = $rank;
        $this->updateDb();
        
        $player = $this->getPlayer();
        if ($player !== null) {
            GlaciaUtils::refreshNametagsForTarget($player);
            ScoreboardHelper::updateRankLine($player);
        }
    }

    public function joinFaction(?string $factionId, ?string $role = "recruit"): void {
        $this->faction_id = $factionId;
        $this->faction_role = $role;
        $this->updateDb();
        
        $player = $this->getPlayer();
        if ($player !== null) {
            GlaciaUtils::refreshNametagsForTarget($player);
            ScoreboardHelper::updateFactionLines($player);
        }
    }

    public function getPower(): int {
        return $this->power;
    }

    public function setPower(int $power): void {
        $this->power = $power;
        $this->updateDb();
        $player = $this->getPlayer();
        if ($player !== null) {
            ScoreboardHelper::updatePowerLine($player);
            ScoreboardHelper::updateFactionLines($player);
        }

        $this->getFaction()?->refreshScoreboards();
    }

    /**
     * Get all settings and their values for the player
     *
     * @return array Associative array of settings and their values
     */
    public function getAllSettings(): array
    {
        return $this->decodeJsonMap($this->settings, "settings");
    }

    /**
     * Get a specific setting value by key
     *
     * @param string $key
     * @return mixed|null The value of the setting if found, or null if the key doesn't exist
     */
    public function getSetting(string $key): mixed
    {
        $decodedSettings = $this->decodeJsonMap($this->settings, "settings");
        if ($key === "notifications" && !array_key_exists("notifications", $decodedSettings)) {
            return $decodedSettings["loot_announcer"] ?? true;
        }

        if ($key === "loot_announcer" && !array_key_exists("loot_announcer", $decodedSettings)) {
            return $decodedSettings["notifications"] ?? true;
        }

        if ($key === "show_coordinates" && !array_key_exists("show_coordinates", $decodedSettings)) {
            return true;
        }

        return $decodedSettings[$key] ?? null;
    }

    /**
     * Set a specific setting value by key
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setSetting(string $key, mixed $value): void
    {
        $decodedSettings = $this->decodeJsonMap($this->settings, "settings");
        $decodedSettings[$key] = $value;
        $this->settings = $this->encodeJsonMap($decodedSettings, "settings", $this->settings);
        $this->updateDb();
    }

    /**
     * Toggle a setting
     *
     * @param string $key
     * @return void
     */
    public function toggleSetting(string $key): void
    {
        $settings = $this->decodeJsonMap($this->settings, "settings");
        $settings[$key] = !((bool) $this->getSetting($key));
        $this->settings = $this->encodeJsonMap($settings, "settings", $this->settings);
        $this->updateDb();
    }

    /**
     * @return string[]
     */
    public function getExtraPermissions(): array
    {
        $permissions = $this->getSetting("extra_permissions");
        if (!is_array($permissions)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => strtolower(trim((string) $value)),
            $permissions
        ))));
    }

    public function hasExtraPermission(string $permission): bool
    {
        return in_array(strtolower(trim($permission)), $this->getExtraPermissions(), true);
    }

    public function addExtraPermission(string $permission): void
    {
        $permission = strtolower(trim($permission));
        if ($permission === "") {
            return;
        }

        $permissions = $this->getExtraPermissions();
        if (!in_array($permission, $permissions, true)) {
            $permissions[] = $permission;
            $this->setSetting("extra_permissions", $permissions);
        }
    }

    public function removeExtraPermission(string $permission): void
    {
        $permission = strtolower(trim($permission));
        $permissions = array_values(array_filter(
            $this->getExtraPermissions(),
            static fn(string $value): bool => $value !== $permission
        ));
        $this->setSetting("extra_permissions", $permissions);
    }

    public function clearExtraPermissions(): void
    {
        $this->setSetting("extra_permissions", []);
    }

    /**
     * @return string[]
     */
    public function getUnlockedTitles(): array
    {
        $titles = $this->getSetting("unlocked_titles");
        if (!is_array($titles)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => strtolower(trim((string) $value)),
            $titles
        ))));
    }

    public function hasUnlockedTitle(string $titleId): bool
    {
        return in_array(strtolower(trim($titleId)), $this->getUnlockedTitles(), true);
    }

    public function unlockTitle(string $titleId): void
    {
        $titleId = strtolower(trim($titleId));
        if ($titleId === "") {
            return;
        }

        $titles = $this->getUnlockedTitles();
        if (!in_array($titleId, $titles, true)) {
            $titles[] = $titleId;
            $this->setSetting("unlocked_titles", $titles);
        }
    }

    public function getSellMultiplier(): float {
        return $this->sellMultiplier;
    }

    public function setSellMultiplier(float $multiplier): void {
        $this->sellMultiplier = max(0.0, $multiplier); // prevent negative multipliers
        $this->updateDb();
    }

    public function getLevel(): int
    {
        return max(1, (int) ($this->getSetting("player_level") ?? 1));
    }

    public function getLevelXp(): int
    {
        return max(0, (int) ($this->getSetting("player_level_xp") ?? 0));
    }

    public function setLevelData(int $level, int $xp): void
    {
        $settings = $this->decodeJsonMap($this->settings, "settings");
        $settings["player_level"] = max(1, $level);
        $settings["player_level_xp"] = max(0, $xp);
        $this->settings = $this->encodeJsonMap($settings, "settings", $this->settings);
        $this->updateDb();
    }

     /**
     * Adds a cooldown with the given name and duration to the player. This will overwrite any existing cooldown with the same name.
     * @param string $cooldownName The name of the cooldown to add.
     * @param int $duration The duration of the cooldown in seconds.
     */
    public function addCooldown(string $cooldownName, int $duration): void
    {
        $cooldowns = $this->decodeCooldowns();
        $cooldowns[$cooldownName] = time() + max(0, $duration);
        $this->cooldowns = $this->encodeJsonMap($cooldowns, "cooldowns", $this->cooldowns);
        $this->updateDb();
    }

    /**
     * Gets the remaining cooldown time for the specified cooldown name, or null if the cooldown doesn't exist.
     * @param string $cooldownName The name of the cooldown to get the remaining time for.
     * @return int|null The remaining cooldown time in seconds, or null if the cooldown doesn't exist.
     */
    public function getCooldown(string $cooldownName): ?int
    {
        $cooldowns = $this->decodeCooldowns();
        if (isset($cooldowns[$cooldownName])) {
            return max(0, ((int) $cooldowns[$cooldownName]) - time());
        }

        return null;
    }

    public function isOnCooldown(string $name): bool {
        $remaining = $this->getCooldown($name);
        return $remaining !== null && $remaining > 0;
    }

    public function clearCooldown(string $cooldownName): void
    {
        $cooldowns = $this->decodeCooldowns();
        unset($cooldowns[$cooldownName]);
        $this->cooldowns = $this->encodeJsonMap($cooldowns, "cooldowns", $this->cooldowns);
        $this->updateDb();
    }

    public static function createCollectionItem(string $category, array $storedItems): Item {
        $encodedItems = array_map(fn($item) => is_string($item) ? $item : ItemUtils::encodeItem($item), $storedItems);

        $displayItem = match ($category) {
            "buycraft" => VanillaBlocks::CHEST()->asItem()->setCustomName(C::colorize("&r&l&b* Buycraft Purchase *")),
            "crate" => VanillaBlocks::CHEST()->asItem()->setCustomName(C::colorize("&r&l&d* Crate Reward *")),
            "blackmarket" => VanillaBlocks::CHEST()->asItem()->setCustomName(C::colorize("&r&l&e* Blackmarket Purchase *")),
            "overflow" => VanillaBlocks::CHEST()->asItem()->setCustomName(C::colorize("&r&l&c* Full Inventory | Stored *")),
            default => VanillaBlocks::CHEST()->asItem()->setCustomName(C::colorize("&r&l&f* Stored Item *"))
        };
    
        $lore = [
            C::colorize("&r&7Category: &f" . ucfirst($category)),
            C::colorize("&r&8Contains:")
        ];
    
        foreach ($encodedItems as $storedItemData) {
            $item = ItemUtils::decodeItem($storedItemData);
            $lore[] = C::colorize("&r&7- &f" . $item->getName() . " x" . $item->getCount());
        }
    
        $lore[] = "";
        $lore[] = C::colorize("&r&l&6Click to claim");
    
        $displayItem->setLore($lore);
    
        return $displayItem;
    }
    
    public function addItemToCollection(Item $item): void
    {
        $collection = $this->getItemsFromCollection();
        
        $itemData = ItemUtils::encodeItem($item);

        if ($itemData === false) {
            $this->getPlayer()?->sendMessage(C::colorize("&r&cFailed to serialize item."));
            return;
        }
        
        if (in_array($itemData, $collection, true)) {
            $this->getPlayer()?->sendMessage(C::colorize("&r&cThat item is already in your collection."));
            return;
        }

        $collection[] = $itemData;
        $this->collection = $this->encodeJsonMap($collection, "collection", $this->collection);
        $this->updateDb();

        $this->getPlayer()?->sendMessage(C::colorize("&r&aItem added to your collection."));
    }

    public function removeItemFromCollection(Item $item): void
    {
        $collection = $this->getItemsFromCollection();
        $itemData = ItemUtils::encodeItem($item);
        
        if (($key = array_search($itemData, $collection, true)) !== false) {
            unset($collection[$key]);
            $this->collection = $this->encodeJsonMap(array_values($collection), "collection", $this->collection);
            $this->updateDb();

            $this->getPlayer()?->sendMessage(C::colorize("&r&aItem removed from your collection."));
        } else {
            $this->getPlayer()?->sendMessage(C::colorize("&r&cThat item was not found in your collection."));
        }
    }

    public function getItemsFromCollection(): array
    {
        $collection = $this->decodeJsonMap($this->collection, "collection");
        return is_array($collection) ? $collection : [];
    }

    public function getGoldenApplesEaten(): int
    {
        return $this->gapplesEaten;
    }

    public function setGoldenApplesEaten(int $count): void
    {
        $this->gapplesEaten = $count;
    }

    public function getLastGoldenAppleAte(): int
    {
        return $this->lastGappleAte;
    }

    public function setLastGoldenAppleAte(int $timestamp): void
    {
        $this->lastGappleAte = $timestamp;
    }

    public function resetGoldenAppleStats(): void
    {
        $this->gapplesEaten = 0;
        $this->lastGappleAte = 0;
    }

    public function getKills(): int {
        return $this->kills;
    }

    public function setKills(int $kills): void {
        $this->kills = $kills;
        $this->updateDb();
        $player = $this->getPlayer();
        if ($player !== null) {
            ScoreboardHelper::updateKDRLine($player);
        }
    }

    public function getDeaths(): int {
        return $this->deaths;
    }

    public function getKDRRatio(): float
    {
        if ($this->deaths <= 0) {
            return (float) $this->kills;
        }

        return $this->kills / $this->deaths;
    }

    public function setDeaths(int $deaths): void {
        $this->deaths = $deaths;
        $this->updateDb();
        $player = $this->getPlayer();
        if ($player !== null) {
            ScoreboardHelper::updateKDRLine($player);
        }
    }

    public function getSlotCredits(): int
    {
        return $this->slotCredits;
    }

    public function addSlotCredits(int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->slotCredits += $amount;
        $this->updateDb();
    }

    public function removeSlotCredits(int $amount): bool
    {
        if ($amount <= 0) {
            return true;
        }

        if ($this->slotCredits < $amount) {
            return false;
        }

        $this->slotCredits -= $amount;
        $this->updateDb();
        return true;
    }

    public function setSlotCredits(int $amount): void
    {
        $this->slotCredits = max(0, $amount);
        $this->updateDb();
    }

    public function getItemFlipCredits(): int
    {
        return $this->itemFlipCredits;
    }

    public function getAdminCoins(): int
    {
        return max(0, (int) ($this->getSetting("admin_coins") ?? 0));
    }

    public function addAdminCoins(int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->setAdminCoins($this->getAdminCoins() + $amount);
    }

    public function removeAdminCoins(int $amount): bool
    {
        if ($amount <= 0) {
            return true;
        }

        $current = $this->getAdminCoins();
        if ($current < $amount) {
            return false;
        }

        $this->setAdminCoins($current - $amount);
        return true;
    }

    public function setAdminCoins(int $amount): void
    {
        $this->setSetting("admin_coins", max(0, $amount));
    }

    public function addItemFlipCredits(int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->itemFlipCredits += $amount;
        $this->updateDb();
    }

    public function removeItemFlipCredits(int $amount): bool
    {
        if ($amount <= 0) {
            return true;
        }

        if ($this->itemFlipCredits < $amount) {
            return false;
        }

        $this->itemFlipCredits -= $amount;
        $this->updateDb();
        return true;
    }

    public function setItemFlipCredits(int $amount): void
    {
        $this->itemFlipCredits = max(0, $amount);
        $this->updateDb();
    }

    public function getTitle(): ?string {
        return $this->title;
    }

    public function setTitle(?string $title): void {
        $this->title = $title;
        $this->updateDb();
    }

    public function removeTitle(): void {
        $this->title = null;
        $this->updateDb();
    }

    public function updateDb(): void {
        Loader::getDatabase()->executeChange(QueryStmts::PLAYERS_UPDATE, [
            'uuid' => $this->uuid->toString(),
            'username' => $this->username,
            'balance' => $this->balance,
            'cooldowns' => $this->cooldowns,
            'faction_id' => $this->faction_id,
            'faction_role' => $this->faction_role,
            'power' => $this->power,
            'rank' => $this->rank,
            'settings' => $this->settings,
            'buffs' => $this->buffs,
            'sell_multiplier' => $this->sellMultiplier,
            'collection' => $this->collection,
            'kills' => $this->kills,
            'deaths' => $this->deaths,
            'slot_credits' => $this->slotCredits,
            'item_flip_credits' => $this->itemFlipCredits,
            'title' => $this->title
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function decodeCooldowns(): array
    {
        $decoded = $this->decodeJsonMap($this->cooldowns, "cooldowns");

        $uuid = $this->getUuid()->toString();
        if (isset($decoded[$uuid]) && is_array($decoded[$uuid])) {
            $decoded = $decoded[$uuid];
        }

        $cooldowns = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_numeric($value)) {
                $cooldowns[$key] = (int) $value;
            }
        }

        return $cooldowns;
    }

    /**
     * @return array<mixed>
     */
    private function decodeJsonMap(string $json, string $context): array
    {
        if (trim($json) === "") {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Loader::getInstance()->getLogger()->warning("[PlayerData] Failed to decode {$context} for {$this->username}: " . $e->getMessage());
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<mixed> $payload
     */
    private function encodeJsonMap(array $payload, string $context, string $fallback): string
    {
        try {
            return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Loader::getInstance()->getLogger()->warning("[PlayerData] Failed to encode {$context} for {$this->username}: " . $e->getMessage());
            return $fallback;
        }
    }
}
