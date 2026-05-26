<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\fishing;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\contribution\FactionContributionManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\world\events\WorldEventManager;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Water;
use pocketmine\item\FishingRod;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\WaterParticle;

final class FishingManager{
    private const ROD_LEVEL_TAG = "fishingRodLevel";
    private const ROD_XP_TAG = "fishingRodXp";
    private const ROD_MAX_SLOTS = 3;

    /** @var array<string, mixed> */
    private static array $settings = [];
    /** @var array<int, array<string, mixed>> */
    private static array $lootTable = [];
    /** @var array<string, array<string, int|bool>> */
    private static array $sessions = [];
    /** @var array<string, int> */
    private static array $recentCastTicks = [];
    /** @var \WeakMap<Player, GlaciaFishingHook>|null */
    private static ?\WeakMap $playerHooks = null;
    private static bool $booted = false;
    private static int $serverTick = 0;

    /**
     * @return \WeakMap<Player, GlaciaFishingHook>
     */
    private static function playerHooks(): \WeakMap{
        if(self::$playerHooks === null){
            self::$playerHooks = new \WeakMap();
        }

        return self::$playerHooks;
    }

    public static function usesVanillaHookEntity(): bool{
        return (bool) (self::$settings["use-vanilla-hook-entity"] ?? true);
    }

    public static function getPlayerHook(Player $player): ?GlaciaFishingHook{
        $map = self::playerHooks();
        if(!$map->offsetExists($player)){
            return null;
        }

        return $map[$player];
    }

    public static function registerHook(Player $player, GlaciaFishingHook $hook): void{
        $old = self::getPlayerHook($player);
        if($old !== null && !$old->isFlaggedForDespawn() && $old->getId() !== $hook->getId()){
            $old->flagForDespawn();
        }

        self::playerHooks()[$player] = $hook;
    }

    public static function clearHookFor(Player $player, GlaciaFishingHook $hook): void{
        $current = self::getPlayerHook($player);
        if($current !== null && $current->getId() === $hook->getId()){
            unset(self::playerHooks()[$player]);
        }
    }

    public static function despawnPlayerHook(Player $player): void{
        $hook = self::getPlayerHook($player);
        if($hook !== null && !$hook->isFlaggedForDespawn()){
            $hook->flagForDespawn();
        }
    }

    public static function grantCatchFromHook(Player $player): void{
        self::deliverGlaciaCatch($player);
    }

    public static function notifyHookReelEmpty(Player $player): void{
        $msg = (string) (self::$settings["messages"]["reel-empty"] ?? "&r&7You reel in the line.");
        $player->sendActionBarMessage(C::colorize($msg));
    }

    public static function boot(): void{
        self::loadFromConfig();
        if(self::$booted){
            return;
        }

        self::$booted = true;
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new class extends Task{
            public function onRun(): void{
                FishingManager::tick();
            }
        }, 1);
    }

    public static function loadFromConfig(): void{
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "fishing.yml");
        self::$settings = (array) ($config?->get("settings", []) ?? []);
        self::$lootTable = array_values(array_filter(
            (array) ($config?->get("loot-table", []) ?? []),
            static fn(mixed $entry): bool => is_array($entry)
        ));
    }

    public static function handleRodUse(Player $player): void{
        if(!self::isEnabled() || !$player->isAlive() || !$player->isConnected()){
            return;
        }

        $item = $player->getInventory()->getItemInHand();
        if($item->getTypeId() !== ItemTypeIds::FISHING_ROD){
            return;
        }

        if($item instanceof FishingRod){
            self::prepareFishingRod($player, $item);
        }

        $uuid = $player->getUniqueId()->toString();

        if(self::usesVanillaHookEntity()){
            unset(self::$sessions[$uuid]);

            $hook = self::getPlayerHook($player);
            if($hook !== null){
                if($hook->isFlaggedForDespawn()){
                    self::clearHookFor($player, $hook);
                }else{
                    $hook->reelLine();
                    return;
                }
            }

            if((self::$recentCastTicks[$uuid] ?? -99) >= (self::$serverTick - 1)){
                return;
            }
            self::$recentCastTicks[$uuid] = self::$serverTick;

            if(!self::canStartFishingCast($player)){
                $msg = (string) (self::$settings["messages"]["invalid-location"] ?? "&r&cYou need nearby water to fish.");
                $player->sendMessage(C::colorize($msg));
                return;
            }

            if(!GlaciaFishingHook::cast($player)){
                return;
            }

            $cast = (string) (self::$settings["messages"]["cast"] ?? "&r&bYou cast your line...");
            $player->sendActionBarMessage(C::colorize($cast));
            return;
        }

        if((self::$recentCastTicks[$uuid] ?? -99) >= (self::$serverTick - 1)){
            return;
        }
        self::$recentCastTicks[$uuid] = self::$serverTick;

        if(!self::canFishHere($player)){
            $msg = (string) (self::$settings["messages"]["invalid-location"] ?? "&r&cYou need nearby water to fish.");
            $player->sendMessage(C::colorize($msg));
            return;
        }

        $state = self::$sessions[$uuid] ?? null;
        if(is_array($state) && (($state["biting"] ?? false) === true)){
            self::reelCatch($player, $uuid, $state);
            return;
        }

        $cooldownTicks = max(0, (int) (self::$settings["cast-cooldown-ticks"] ?? 8));
        $nextCastTick = (int) ($state["next-cast-tick"] ?? 0);
        if(self::$serverTick < $nextCastTick){
            return;
        }

        $minBite = max(2, (int) (self::$settings["min-bite-seconds"] ?? 3));
        $maxBite = max($minBite, (int) (self::$settings["max-bite-seconds"] ?? 9));
        $biteDelay = self::scaleTicksForRodRune($player, mt_rand($minBite * 20, $maxBite * 20));
        $biteWindow = max(1, (int) (self::$settings["bite-window-seconds"] ?? 3)) * 20;

        $range = max(3, min(20, (int) (self::$settings["cast-range"] ?? 14)));
        $bob = self::findBobPosition($player, $range);

        self::$sessions[$uuid] = [
            "biting" => false,
            "bite-at" => self::$serverTick + $biteDelay,
            "bite-expires-at" => self::$serverTick + $biteDelay + $biteWindow,
            "next-cast-tick" => self::$serverTick + $cooldownTicks,
            "bob" => $bob !== null ? ["x" => $bob->x, "y" => $bob->y, "z" => $bob->z] : null,
        ];

        if ((bool) (self::$settings["show-cast-fx"] ?? true) && $bob !== null) {
            self::playCastLineEffects($player, $bob);
        }

        $cast = (string) (self::$settings["messages"]["cast"] ?? "&r&bYou cast your line...");
        $player->sendActionBarMessage(C::colorize($cast));
    }

    public static function clearPlayer(Player $player): void{
        $uuid = $player->getUniqueId()->toString();
        unset(self::$sessions[$uuid], self::$recentCastTicks[$uuid]);
        self::despawnPlayerHook($player);
    }

    public static function tick(): void{
        self::$serverTick++;
        foreach(Loader::getInstance()->getServer()->getOnlinePlayers() as $player){
            $uuid = $player->getUniqueId()->toString();
            $state = self::$sessions[$uuid] ?? null;
            if(!is_array($state)){
                continue;
            }

            $biteAt = (int) ($state["bite-at"] ?? 0);
            $biteExpiresAt = (int) ($state["bite-expires-at"] ?? 0);
            $isBiting = (($state["biting"] ?? false) === true);

            if(!$isBiting && self::$serverTick >= $biteAt){
                $state["biting"] = true;
                self::$sessions[$uuid] = $state;
                $biteMsg = (string) (self::$settings["messages"]["bite"] ?? "&r&eA fish is biting! Use your rod again.");
                $player->sendActionBarMessage(C::colorize($biteMsg));
                if ((bool) (self::$settings["show-cast-fx"] ?? true)) {
                    self::playBiteEffects($player, $state);
                }
                continue;
            }

            if($isBiting && self::$serverTick > $biteExpiresAt){
                unset(self::$sessions[$uuid]);
                $miss = (string) (self::$settings["messages"]["missed"] ?? "&r&7The fish got away.");
                $player->sendActionBarMessage(C::colorize($miss));
                continue;
            }

            if(!$isBiting && self::$serverTick < $biteAt
                && (bool) (self::$settings["show-cast-fx"] ?? true)
                && (bool) (self::$settings["show-bob-pulse"] ?? true)
                && ($state["bob"] ?? null) !== null
            ){
                $interval = max(6, min(40, (int) (self::$settings["bob-pulse-interval-ticks"] ?? 12)));
                if(self::$serverTick % $interval === 0){
                    self::pulseBobWaiting($player, $state);
                }
            }
        }
    }

    /**
     * @param array<string, int|bool> $state
     */
    private static function reelCatch(Player $player, string $uuid, array $state): void{
        if(self::$serverTick > (int) ($state["bite-expires-at"] ?? 0)){
            unset(self::$sessions[$uuid]);
            return;
        }

        self::deliverGlaciaCatch($player);
        unset(self::$sessions[$uuid]);
    }

    private static function deliverGlaciaCatch(Player $player): void{
        $entry = self::rollCatchEntry($player);
        if($entry === null){
            $player->sendMessage(C::colorize("&r&cFishing loot table is empty."));
            return;
        }

        $item = RewardResolver::resolveItem($entry, $player);
        if($item === null){
            return;
        }

        WorldEventManager::handleFishingCatchBroadcast($player, $entry, $item);

        $session = Loader::getPlayerManager()->getSession($player);
        foreach($player->getInventory()->addItem($item) as $leftover){
            $session?->addItemToCollection($leftover);
        }

        $name = C::clean($item->getName());
        $count = $item->getCount();
        $caught = (string) (self::$settings["messages"]["caught"] ?? "&r&aYou caught &f{item} &7x&f{count}&a.");
        $caught = str_replace(["{item}", "{count}"], [$name, (string) $count], $caught);
        $player->sendMessage(C::colorize($caught));
        Loader::getQuestBoardManager()->recordProgress($player, "fishes_caught", 1);

        $rarity = self::resolveCatchRarity((array) $entry);
        FactionContributionManager::addFishingContribution($player, $rarity);
        if ($rarity === "rare" || $rarity === "epic") {
            FactionContributionManager::recordTaskProgress($player, "rare_fish_caught", 1);
        }

        $hand = $player->getInventory()->getItemInHand();
        if($hand instanceof FishingRod){
            self::grantRodExperience($player, $hand, $rarity);
        }

        $bonusChance = max(0.0, FactionContributionManager::getBuffMultiplierForPlayer($player, "fishing-luck-multiplier", 1.0) - 1.0);
        if ($bonusChance > 0 && mt_rand(1, 10000) <= (int) round(min(1.0, $bonusChance) * 10000)) {
            foreach($player->getInventory()->addItem(clone $item) as $leftover){
                $session?->addItemToCollection($leftover);
            }
            $player->sendMessage(C::colorize("&r&bFaction luck bonus! &fExtra catch duplicated."));
        }

        if((bool) (self::$settings["show-cast-fx"] ?? true)){
            self::playCatchEffects($player);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function rollCatchEntry(Player $player): ?array{
        if(self::$lootTable === []){
            return null;
        }

        $table = WorldEventManager::decorateFishingLootTable($player, self::$lootTable);
        if ($table === []) {
            return null;
        }

        $rodLevel = self::getHeldRodLevel($player);
        if($rodLevel <= 0){
            $entry = RewardResolver::rollEntry($table);
            return is_array($entry) ? $entry : null;
        }

        $leveledTable = [];
        $bonusPerLevel = max(0.0, (float) (self::getRodLevelingSettings()["rare-weight-bonus-per-level"] ?? 0.06));
        $commonReductionPerLevel = max(0.0, (float) (self::getRodLevelingSettings()["common-weight-reduction-per-level"] ?? 0.04));
        foreach($table as $entry){
            if(!is_array($entry)){
                continue;
            }

            $rarity = self::resolveCatchRarity($entry);
            $modified = $entry;
            $baseChance = (float) ($entry["chance"] ?? 0);
            $multiplier = match($rarity){
                "epic" => 1.0 + ($bonusPerLevel * 1.85 * $rodLevel),
                "rare" => 1.0 + ($bonusPerLevel * 1.2 * $rodLevel),
                "uncommon" => 1.0 + ($bonusPerLevel * 0.45 * $rodLevel),
                default => max(0.25, 1.0 - ($commonReductionPerLevel * $rodLevel)),
            };
            $modified["chance"] = max(1, (int) round(max(1.0, $baseChance) * $multiplier));
            $leveledTable[] = $modified;
        }

        $entry = RewardResolver::rollEntry($leveledTable);
        return is_array($entry) ? $entry : null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveCatchRarity(array $entry): string
    {
        $explicit = strtolower((string) ($entry["rarity"] ?? ""));
        if (in_array($explicit, ["common", "uncommon", "rare", "epic"], true)) {
            return $explicit;
        }
        $chance = (float) ($entry["chance"] ?? 0);
        return match (true) {
            $chance <= 2.0 => "epic",
            $chance <= 8.0 => "rare",
            $chance <= 18.0 => "uncommon",
            default => "common",
        };
    }

    private static function findBobPosition(Player $player, int $range): ?Vector3{
        $start = $player->getEyePos();
        $dir = $player->getDirectionVector()->normalize();
        $world = $player->getWorld();
        $minHorizontal = max(0.0, (float) (self::$settings["min-horizontal-cast-to-water"] ?? 1.75));
        for($i = 1; $i <= ($range * 2); $i++){
            $step = $i * 0.5;
            $pos = $start->addVector(new Vector3($dir->x * $step, $dir->y * $step, $dir->z * $step));
            if(!($world->getBlock($pos->floor()) instanceof Water)){
                continue;
            }

            $horizontal = hypot($pos->x - $start->x, $pos->z - $start->z);
            if($horizontal < $minHorizontal){
                continue;
            }

            return new Vector3($pos->x, $pos->y + 0.08, $pos->z);
        }

        return null;
    }

    private static function playCastLineEffects(Player $player, Vector3 $bob): void{
        $world = $player->getWorld();
        $eye = $player->getEyePos();
        $dx = $bob->x - $eye->x;
        $dy = $bob->y - $eye->y;
        $dz = $bob->z - $eye->z;
        $dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
        if($dist < 0.01){
            return;
        }

        $steps = (int) max(8, min(28, floor($dist / 0.45)));
        $water = VanillaBlocks::WATER();
        for($s = 1; $s <= $steps; $s++){
            $t = $s / $steps;
            $point = new Vector3($eye->x + $dx * $t, $eye->y + $dy * $t, $eye->z + $dz * $t);
            $world->addParticle($point, new BlockBreakParticle($water));
            if($s % 2 === 0){
                $world->addParticle($point->add(0, 0.05, 0), new WaterParticle());
            }
        }

        for($i = 0; $i < 5; $i++){
            $world->addParticle(
                $bob->add((mt_rand(-10, 10) / 60.0), mt_rand(0, 4) / 60.0, (mt_rand(-10, 10) / 60.0)),
                new BlockBreakParticle($water)
            );
        }
        PlayerUtils::playSound($player, "bucket.empty_water", 0.4, 1.15, 10);
    }

    /**
     * @param array<string, int|bool|array<string, float>|null> $state
     */
    private static function playBiteEffects(Player $player, array $state): void{
        $bob = $state["bob"] ?? null;
        if(!is_array($bob) || !isset($bob["x"], $bob["y"], $bob["z"])){
            PlayerUtils::playSound($player, "note.pling", 0.55, 1.45, 12);
            return;
        }

        $world = $player->getWorld();
        $center = new Vector3((float) $bob["x"], (float) $bob["y"], (float) $bob["z"]);
        for($i = 0; $i < 16; $i++){
            $angle = deg2rad($i * 36);
            $world->addParticle(
                $center->addVector(new Vector3(cos($angle) * 0.35, 0.12, sin($angle) * 0.35)),
                new BlockBreakParticle(VanillaBlocks::WATER())
            );
        }
        for($i = 0; $i < 8; $i++){
            $world->addParticle(
                $center->addVector(new Vector3(mt_rand(-12, 12) / 40.0, 0.08 + (mt_rand(0, 10) / 80.0), mt_rand(-12, 12) / 40.0)),
                new BubbleParticle()
            );
        }
        $world->addParticle($center->addVector(new Vector3(0, 0.12, 0)), new WaterParticle());

        PlayerUtils::playSound($player, "note.pling", 0.55, 1.45, 12);
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function pulseBobWaiting(Player $player, array $state): void{
        $bob = $state["bob"] ?? null;
        if(!is_array($bob) || !isset($bob["x"], $bob["y"], $bob["z"])){
            return;
        }

        $world = $player->getWorld();
        $v = new Vector3((float) $bob["x"], (float) $bob["y"], (float) $bob["z"]);
        $world->addParticle($v, new BubbleParticle());
        $world->addParticle($v->addVector(new Vector3(0, 0.05, 0)), new BubbleParticle());
        $world->addParticle($v->addVector(new Vector3(0, 0.1, 0)), new WaterParticle());
        $world->addParticle($v->addVector(new Vector3(0.12, 0.02, -0.08)), new WaterParticle());
    }

    private static function playCatchEffects(Player $player): void{
        $world = $player->getWorld();
        $pos = $player->getPosition()->add(0, 1.2, 0);
        for($i = 0; $i < 8; $i++){
            $world->addParticle(
                $pos->add((mt_rand(-10, 10) / 10.0), (mt_rand(0, 8) / 10.0), (mt_rand(-10, 10) / 10.0)),
                new HappyVillagerParticle()
            );
        }

        PlayerUtils::playSound($player, "random.levelup", 0.35, 1.1, 8);
    }

    private static function canFishHere(Player $player): bool{
        if(!self::canStartFishingCast($player)){
            return false;
        }

        $requireWater = (bool) (self::$settings["require-water"] ?? true);
        if(!$requireWater){
            return true;
        }

        $range = max(3, min(20, (int) (self::$settings["cast-range"] ?? 14)));
        return self::findBobPosition($player, $range) !== null;
    }

    private static function canStartFishingCast(Player $player): bool{
        if((bool) (self::$settings["warzone-only"] ?? false)){
            $warzone = Loader::getRegionManager()->getRegionByName("Warzone");
            if($warzone === null || Loader::getRegionManager()->getRegionAt($player->getPosition()) !== $warzone){
                return false;
            }
        }

        $allowedWorlds = array_map(
            static fn(mixed $world): string => strtolower((string) $world),
            (array) (self::$settings["allowed-worlds"] ?? [])
        );
        if($allowedWorlds !== [] && !in_array(strtolower($player->getWorld()->getFolderName()), $allowedWorlds, true)){
            return false;
        }

        return true;
    }

    private static function isEnabled(): bool{
        return (bool) (self::$settings["enabled"] ?? true);
    }

    /**
     * Tidehook (and any future rod runes using the same multiplier): shorter wait until bite.
     */
    public static function scaleTicksForRodRune(Player $player, int $baseTicks): int{
        $m = RuneManager::getRodSpawnRateMultiplier($player);
        $m *= self::getRodBiteSpeedMultiplier($player);

        return max(1, (int) floor($baseTicks / $m));
    }

    /**
     * @return array<string, mixed>
     */
    private static function getRodLevelingSettings(): array{
        $settings = self::$settings["rod-leveling"] ?? [];
        return is_array($settings) ? $settings : [];
    }

    private static function getHeldRodLevel(Player $player): int{
        $item = $player->getInventory()->getItemInHand();
        return $item instanceof FishingRod ? self::getFishingRodLevel($item) : 0;
    }

    private static function getRodBiteSpeedMultiplier(Player $player): float{
        $settings = self::getRodLevelingSettings();
        if(!(bool) ($settings["enabled"] ?? true)){
            return 1.0;
        }

        $perLevel = max(0.0, (float) ($settings["bite-speed-bonus-per-level"] ?? 0.04));
        return 1.0 + ($perLevel * self::getHeldRodLevel($player));
    }

    private static function prepareFishingRod(Player $player, FishingRod $rod): void{
        self::ensureFishingRodState($rod);
        $player->getInventory()->setItemInHand($rod);
    }

    private static function ensureFishingRodState(FishingRod $rod): void{
        $root = self::getRodGlaciaRoot($rod);
        $changed = false;

        if(!$root->getTag(self::ROD_LEVEL_TAG)){
            $root->setInt(self::ROD_LEVEL_TAG, 1);
            $changed = true;
        }
        if(!$root->getTag(self::ROD_XP_TAG)){
            $root->setInt(self::ROD_XP_TAG, 0);
            $changed = true;
        }
        if(!$root->getTag("maxEnchantSlots")){
            $root->setInt("maxEnchantSlots", self::ROD_MAX_SLOTS);
            $changed = true;
        }

        if($changed){
            $rod->getNamedTag()->setTag("Glacia", $root);
        }

        self::refreshFishingRodLore($rod);
    }

    private static function grantRodExperience(Player $player, FishingRod $rod, string $rarity): void{
        $settings = self::getRodLevelingSettings();
        if(!(bool) ($settings["enabled"] ?? true)){
            return;
        }

        self::ensureFishingRodState($rod);
        $root = self::getRodGlaciaRoot($rod);
        $level = max(1, $root->getInt(self::ROD_LEVEL_TAG, 1));
        $maxLevel = max($level, (int) ($settings["max-level"] ?? 25));
        $xpPerCatch = $settings["xp-per-catch"] ?? [];
        if(!is_array($xpPerCatch)){
            $xpPerCatch = [];
        }
        $xpGain = max(1, (int) (($xpPerCatch[$rarity] ?? null) ?? match($rarity){
            "epic" => 20,
            "rare" => 12,
            "uncommon" => 8,
            default => 5,
        }));
        $xpGain = max(1, (int) ceil($xpGain * Loader::getZoneBoosterManager()->getFishingMultiplierAt($player)));

        if($level >= $maxLevel){
            $root->setInt(self::ROD_XP_TAG, self::getRequiredXpForLevel($maxLevel));
            $rod->getNamedTag()->setTag("Glacia", $root);
            self::refreshFishingRodLore($rod);
            $player->getInventory()->setItemInHand($rod);
            return;
        }

        $xp = $root->getInt(self::ROD_XP_TAG, 0) + $xpGain;
        while($level < $maxLevel){
            $required = self::getRequiredXpForLevel($level);
            if($xp < $required){
                break;
            }

            $xp -= $required;
            $level++;
        }

        if($level >= $maxLevel){
            $level = $maxLevel;
            $xp = self::getRequiredXpForLevel($maxLevel);
        }

        $root->setInt(self::ROD_LEVEL_TAG, $level);
        $root->setInt(self::ROD_XP_TAG, $xp);
        $root->setInt("maxEnchantSlots", self::ROD_MAX_SLOTS);
        $rod->getNamedTag()->setTag("Glacia", $root);
        self::refreshFishingRodLore($rod);
        $player->getInventory()->setItemInHand($rod);

        $xpMessage = (string) ($settings["xp-message"] ?? "&r&bRod XP &f+{xp} &7({percent}%)");
        $required = self::getRequiredXpForLevel($level);
        $percent = $level >= $maxLevel ? 100 : (int) round(min(1.0, $xp / max(1, $required)) * 100);
        $player->sendActionBarMessage(C::colorize(str_replace(
            ["{xp}", "{percent}", "{level}"],
            [(string) $xpGain, (string) $percent, (string) $level],
            $xpMessage
        )));
    }

    private static function getFishingRodLevel(Item $item): int{
        $root = $item->getNamedTag()->getCompoundTag("Glacia");
        return max(1, $root?->getInt(self::ROD_LEVEL_TAG, 1) ?? 1);
    }

    private static function getRequiredXpForLevel(int $level): int{
        $settings = self::getRodLevelingSettings();
        $base = max(1, (int) ($settings["base-xp"] ?? 35));
        $perLevel = max(0, (int) ($settings["xp-per-level"] ?? 18));
        return $base + max(0, $level - 1) * $perLevel;
    }

    private static function getRodGlaciaRoot(Item $item): CompoundTag{
        return $item->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();
    }

    private static function refreshFishingRodLore(FishingRod $rod): void{
        $settings = self::getRodLevelingSettings();
        $level = self::getFishingRodLevel($rod);
        $maxLevel = max($level, (int) ($settings["max-level"] ?? 25));
        $root = self::getRodGlaciaRoot($rod);
        $xp = max(0, $root->getInt(self::ROD_XP_TAG, 0));
        $required = self::getRequiredXpForLevel($level);
        $percent = $level >= $maxLevel ? 100 : (int) round(min(1.0, $xp / max(1, $required)) * 100);

        $barLength = max(8, (int) ($settings["progress-bar-length"] ?? 12));
        $filled = $level >= $maxLevel ? $barLength : (int) round(($percent / 100) * $barLength);
        $filled = max(0, min($barLength, $filled));
        $bar = "&a" . str_repeat("█", $filled) . "&7" . str_repeat("█", $barLength - $filled);

        $filteredLore = [];
        foreach($rod->getLore() as $line){
            $clean = trim(C::clean($line));
            if($clean === ""
                || $clean === "LEVEL"
                || str_contains($clean, "A leveling fishing rod.")
                || str_contains($clean, "Catch fish to increase its power.")
                || str_contains($clean, "Catch better fish as this rod grows.")
                || str_contains($clean, "Reel in catches to power it up.")
                || preg_match('/\d+%$/', $clean) === 1
                || preg_match('/^Level \d+\/\d+$/i', $clean) === 1
            ){
                continue;
            }
            $filteredLore[] = $line;
        }

        $filteredLore[] = C::colorize("&r&7A leveling fishing rod.");
        $filteredLore[] = C::colorize("&r&7Catch fish to increase its power.");
        $filteredLore[] = C::colorize("&r");
        $filteredLore[] = C::colorize("&r&f&lLEVEL");
        $filteredLore[] = C::colorize("&r&bLevel &f{$level}&7/&f{$maxLevel}");
        $filteredLore[] = C::colorize("&r{$bar} &f{$percent}%");
        $rod->setLore($filteredLore);
    }
}
