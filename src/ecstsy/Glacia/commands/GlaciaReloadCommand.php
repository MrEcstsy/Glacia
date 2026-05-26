<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\args\RawStringArgument;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\commands\CommandRateLimiter;
use ecstsy\Glacia\server\blackmarket\BlackMarketAuctionManager;
use ecstsy\Glacia\server\adminbazaar\AdminBazaarManager;
use ecstsy\Glacia\server\economy\SellHandler;
use ecstsy\Glacia\server\items\genbuckets\GenBucketManager;
use ecstsy\Glacia\server\items\ItemDefinitionOverrideManager;
use ecstsy\Glacia\server\items\GlaciaItems;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\EnchantDescriptionOverrides;
use ecstsy\Glacia\server\enchantments\essence\EnchantEssenceManager;
use ecstsy\Glacia\network\PersonaTolerantSkinAdapter;
use ecstsy\Glacia\server\factions\FactionNameBlocklist;
use ecstsy\Glacia\server\factions\contribution\FactionContributionManager;
use ecstsy\Glacia\server\fishing\FishingManager;
use ecstsy\Glacia\server\floatingtext\FloatingTextManager;
use ecstsy\Glacia\server\fragments\FragmentManager;
use ecstsy\Glacia\server\farming\FarmLevelRequirementManager;
use ecstsy\Glacia\server\levels\PlayerLevelManager;
use ecstsy\Glacia\server\lootboxes\CcProgressManager;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\Glacia\server\redstone\RedstoneManager;
use ecstsy\Glacia\server\regions\RegionManager;
use ecstsy\Glacia\server\spawners\SpawnerWorthManager;
use ecstsy\Glacia\server\worldborder\WorldBorderManager;
use ecstsy\Glacia\server\world\theme\DimensionThemeManager;
use ecstsy\Glacia\server\staff\StaffModeManager;
use ecstsy\Glacia\server\scoreboard\ScoreboardProfileManager;
use ecstsy\Glacia\server\scoreboard\ScoreboardHelper;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\server\zones\ZoneMobManager;
use ecstsy\Glacia\server\titles\TitleManager;
use ecstsy\Glacia\utils\AuditFileLogger;
use ecstsy\Glacia\server\guide\GuideManager;
use ecstsy\Glacia\utils\ConfigDoctor;
use ecstsy\Glacia\utils\ConfigResourceCatalog;
use ecstsy\Glacia\utils\ConfigSchemaUpdater;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\convert\LegacySkinAdapter;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\utils\TextFormat as C;

final class GlaciaReloadCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("target", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $loader = Loader::getInstance();
        $action = strtolower((string) ($args["action"] ?? "reload"));
        $target = strtolower((string) ($args["target"] ?? "all"));

        $targets = $this->getTargets();
        if (!isset($targets[$target])) {
            $sender->sendMessage(C::colorize("&r&cUnknown target. Use: all, pvp, enchants, items, shop, sell, kits, warlords, blackmarket, adminbazaar, ccsystem, essences, fragments, enchanter, auctionhouse, quests, spawners, misc, messages, announcer, zonemobs, worldevents, bosses, mobitems, factionupgrades, factionblocklist, pets, runes, levels, zones, regions, capture, fund, envoy, genbuckets, challenges, slotbot, combat, fishing, titles, itemoverrides, lootboxes, etherealcrates, dps, cevalues, enchantdesc, scoreboards, floatingtexts, dimensions, redstone, voting, tebex, config, guide"));
            return;
        }

        $files = $targets[$target];

        switch ($action) {
            case "reload":
                $this->reloadFiles($files);
                $sender->sendMessage(C::colorize("&r&aReloaded &f{$target}&a configuration(s)."));
                return;

            case "restore":
            case "resource":
                $restored = $this->restoreFiles($files);
                $this->reloadFiles($files);
                $sender->sendMessage(C::colorize("&r&aCreated/restored missing &f{$restored}&a resource file(s) and reloaded &f{$target}&a. Existing files were left untouched."));
                return;

            case "doctor":
            case "repair":
                $result = ConfigDoctor::repairAndValidate($loader, $files);
                $this->reloadFiles($files);
                $sender->sendMessage(C::colorize(
                    "&r&aConfig doctor finished for &f{$target}&a. " .
                    "&7created:&f " . count($result["created"]) . " " .
                    "&7validated:&f " . count($result["validated"]) . " " .
                    "&7issues:&f " . count($result["issues"])
                ));
                if ($result["created"] !== []) {
                    $sender->sendMessage(C::colorize("&r&7Created missing: &f" . implode(", ", array_slice($result["created"], 0, 8))));
                }
                if ($result["issues"] !== []) {
                    $sender->sendMessage(C::colorize("&r&cStill failing: &f" . implode(", ", array_slice($result["issues"], 0, 8))));
                }
                return;
        }

        $sender->sendMessage(C::colorize("&r&cUsage: /greload <reload|restore|doctor> [all|pvp|enchants|items|shop|sell|kits|warlords|blackmarket|adminbazaar|ccsystem|essences|fragments|enchanter|auctionhouse|quests|spawners|misc|messages|announcer|zonemobs|worldevents|bosses|mobitems|factionupgrades|factionblocklist|pets|runes|levels|zones|regions|capture|fund|envoy|genbuckets|challenges|slotbot|combat|fishing|titles|itemoverrides|lootboxes|etherealcrates|dps|cevalues|enchantdesc|scoreboards|floatingtexts|dimensions|redstone|voting|tebex|config|guide]"));
    }

    private function restoreFiles(array $files): int {
        $loader = Loader::getInstance();
        $restored = 0;

        foreach ($files as $file) {
            if (!in_array($file, $this->getRestorableFiles(), true)) {
                continue;
            }

            // Safety: never overwrite staff-edited configs via /greload restore.
            $relative = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $file);
            $targetPath = $loader->getDataFolder() . $relative;
            if (is_file($targetPath)) {
                continue;
            }
            $loader->saveResource($file, false);
            $restored++;
        }

        return $restored;
    }

    private function reloadFiles(array $files): void {
        $loader = Loader::getInstance();

        foreach ($files as $file) {
            if (ConfigSchemaUpdater::isTracked($file)) {
                ConfigSchemaUpdater::ensureMerged($loader, $file);
            }
            GeneralUtils::reloadConfiguration($loader, $file);
        }

        if (in_array("sell.yml", $files, true)) {
            SellHandler::loadFromConfig();
        }

        if (in_array("blackmarket.yml", $files, true)) {
            BlackMarketAuctionManager::getInstance()->reload();
        }

        if (in_array("admin_bazaar.yml", $files, true)) {
            AdminBazaarManager::loadFromConfig();
        }

        if (in_array("cc_system.yml", $files, true)) {
            CcProgressManager::loadFromConfig();
        }

        if (in_array("essences.yml", $files, true)) {
            EnchantEssenceManager::loadFromConfig();
        }

        if (in_array("fragments.yml", $files, true)) {
            FragmentManager::loadFromConfig();
        }

        if (in_array("config.yml", $files, true)) {
            Loader::getJackpotInstance()->reloadSettingsFromConfig();
            Loader::getTebexManager()->loadFromConfig();
            Loader::getVoteManager()->loadFromConfig();
            Loader::getDeathInventoryLogManager()->loadFromConfig();
            Loader::getChallengeManager()->loadFromConfig();
            Loader::getWorldManagerService()->loadFromConfig();
            Loader::getWorldManagerService()->ensureConfiguredWorlds();
            AuditFileLogger::loadFromConfig();
            \ecstsy\Glacia\server\staff\ModerationManager::getInstance()->loadFromConfig();
        }

        if (in_array("quests.yml", $files, true)) {
            QuestInstance::loadFromConfig();
            Loader::getQuestBoardManager()->loadFromConfig();
        }

        if (in_array("spawners.yml", $files, true)) {
            SpawnerWorthManager::loadFromConfig();
            Loader::getFactionManager()->recalculateSpawnerWorths();
        }

        if (in_array("messages.yml", $files, true)) {
            \ecstsy\Glacia\utils\lang\MessageTemplates::load();
        }

        if (in_array("guide.yml", $files, true)) {
            GuideManager::load();
        }

        if (in_array("faction_upgrades.yml", $files, true)) {
            Loader::getFactionUpgradeManager()->applyAllEffects();
        }

        if (in_array("faction_name_blocklist.yml", $files, true)) {
            FactionNameBlocklist::load();
        }

        if (in_array("fund.yml", $files, true)) {
            Loader::getGlobalFundManager()->reloadConfig();
        }

        if (in_array("envoy.yml", $files, true)) {
            Loader::getEnvoyManager()->reload();
        }

        if (in_array("config.yml", $files, true)) {
            RedstoneManager::getInstance()->reloadConfig();
        }

        if (in_array("redstone.yml", $files, true)) {
            RedstoneManager::getInstance()->reloadConfig();
        }

        if (in_array("voting.yml", $files, true)) {
            Loader::getVoteManager()->loadFromConfig();
        }

        if (in_array("tebex.yml", $files, true)) {
            Loader::getTebexManager()->loadFromConfig();
        }

        if (in_array("genbuckets.yml", $files, true)) {
            GenBucketManager::loadFromConfig();
        }

        if (in_array("challenges.yml", $files, true)) {
            Loader::getChallengeManager()->loadFromConfig();
        }

        if (in_array("runes.yml", $files, true)) {
            RuneManager::loadFromConfig();
        }

        if (in_array("ce_values.yml", $files, true)) {
            EnchantTuningManager::loadFromConfig();
        }

        if (in_array("enchant_descriptions.yml", $files, true)) {
            EnchantDescriptionOverrides::reload();
        }

        if (in_array("misc.yml", $files, true)) {
            \ecstsy\Glacia\server\cannon\CannonRulesConfig::reload();
            WorldBorderManager::loadFromConfig();
            StaffModeManager::loadFromConfig();
            \ecstsy\Glacia\server\staff\anticheat\AntiCheatManager::getInstance()->loadFromConfig();
            FactionContributionManager::loadFromConfig();
            $misc = GeneralUtils::getConfiguration($loader, "misc.yml");
            if ((bool) ($misc?->get("persona-friendly-skins", true))) {
                TypeConverter::getInstance()->setSkinAdapter(new PersonaTolerantSkinAdapter());
            } else {
                TypeConverter::getInstance()->setSkinAdapter(new LegacySkinAdapter());
            }
            CommandRateLimiter::loadFromMisc($misc);
        }

        if (in_array("levels.yml", $files, true)) {
            PlayerLevelManager::loadFromConfig();
            FarmLevelRequirementManager::loadFromConfig();
        }

        if (in_array("zones.yml", $files, true)) {
            ZoneManager::loadFromConfig();
        }

        if (in_array("regions.yml", $files, true)) {
            RegionManager::getInstance()->loadFromConfig();
        }

        if (in_array("dimension_themes.yml", $files, true)) {
            DimensionThemeManager::loadFromConfig();
        }

        if (in_array("zone_mobs.yml", $files, true)) {
            ZoneMobManager::loadFromConfig();
        }

        if (in_array("world_events.yml", $files, true)) {
            \ecstsy\Glacia\server\world\events\WorldEventManager::loadFromConfig();
        }

        if (in_array("bosses.yml", $files, true)) {
            \ecstsy\Glacia\server\boss\BossConfigManager::loadFromConfig();
        }

        if (in_array("scoreboards.yml", $files, true)) {
            ScoreboardProfileManager::loadFromConfig();
        }

        if (in_array("floating_texts.yml", $files, true)) {
            FloatingTextManager::loadFromConfig();
        }

        if ($this->shouldRebuildItemDefinitions($files)) {
            $this->rebuildItemDefinitions();
        }

        if (in_array("capturepoints.yml", $files, true)) {
            Loader::getCaptureManager()->load();
        }

        if (in_array("fishing.yml", $files, true)) {
            FishingManager::loadFromConfig();
        }

        if (array_filter($files, static fn(string $file): bool => str_starts_with($file, "lootboxes/")) !== []) {
            LootboxRepository::reload();
        }
        if (array_filter($files, static fn(string $file): bool => str_starts_with($file, "etherealcrates/")) !== []) {
            EtherealCrateRepository::reload();
        }

        if (
            in_array("scoreboards.yml", $files, true) ||
            in_array("dimension_themes.yml", $files, true) ||
            in_array("zones.yml", $files, true)
        ) {
            foreach ($loader->getServer()->getOnlinePlayers() as $player) {
                ScoreboardHelper::refreshScoreboard($player);
            }
        }
    }

    private function shouldRebuildItemDefinitions(array $files): bool
    {
        return array_intersect($files, [
            "titles.yml",
            "item_overrides.yml",
            "items.yml",
            "custom_mob_items.yml",
            "admin_items.yml",
            "cc_system.yml",
            "essences.yml",
            "fragments.yml",
        ]) !== [];
    }

    private function rebuildItemDefinitions(): void
    {
        GlaciaItems::init();
        TitleManager::loadFromConfig();
        CcProgressManager::registerItemDefinitions();
        EnchantEssenceManager::registerItemDefinitions();
        FragmentManager::registerItemDefinitions();
        ItemDefinitionOverrideManager::apply();
    }

    public function getPermission(): string {
        return "glacia.admin";
    }

    /**
     * @return array<string, string[]>
     */
    private function getTargets(): array
    {
        $allFiles = ConfigResourceCatalog::getBundledConfigFiles();
        $lootboxes = LootboxRepository::getBundledResourceFiles();
        $etherealCrates = EtherealCrateRepository::getBundledResourceFiles();

        return [
            "all" => $allFiles,
            "pvp" => [
                "ce_values.yml",
                "enchant_descriptions.yml",
                "essences.yml",
                "runes.yml",
                "pets.yml",
                "combat.yml",
                "misc.yml",
            ],
            "enchants" => [
                "ce_values.yml",
                "enchant_descriptions.yml",
                "essences.yml",
                "runes.yml",
            ],
            "items" => ["items.yml"],
            "shop" => ["shop.yml"],
            "sell" => ["sell.yml"],
            "kits" => ["kits.yml"],
            "warlords" => ["warlords.yml"],
            "blackmarket" => ["blackmarket.yml"],
            "adminbazaar" => ["admin_bazaar.yml"],
            "ccsystem" => ["cc_system.yml"],
            "essences" => ["essences.yml"],
            "fragments" => ["fragments.yml"],
            "enchanter" => ["enchanter.yml"],
            "auctionhouse" => ["auctionhouse.yml"],
            "quests" => ["quests.yml"],
            "spawners" => ["spawners.yml"],
            "misc" => ["misc.yml"],
            "messages" => ["messages.yml"],
            "announcer" => ["announcer.yml"],
            "zonemobs" => ["zone_mobs.yml"],
            "worldevents" => ["world_events.yml"],
            "bosses" => ["bosses.yml"],
            "mobitems" => ["custom_mob_items.yml"],
            "adminitems" => ["admin_items.yml"],
            "factionupgrades" => ["faction_upgrades.yml"],
            "factionblocklist" => ["faction_name_blocklist.yml"],
            "pets" => ["pets.yml"],
            "runes" => ["runes.yml"],
            "cevalues" => ["ce_values.yml"],
            "enchantdesc" => ["enchant_descriptions.yml"],
            "levels" => ["levels.yml"],
            "zones" => ["zones.yml"],
            "regions" => ["regions.yml"],
            "capture" => ["capturepoints.yml"],
            "fund" => ["fund.yml"],
            "envoy" => ["envoy.yml"],
            "genbuckets" => ["genbuckets.yml"],
            "challenges" => ["challenges.yml"],
            "slotbot" => ["slotbot.yml"],
            "combat" => ["combat.yml"],
            "fishing" => ["fishing.yml"],
            "titles" => ["titles.yml"],
            "itemoverrides" => ["item_overrides.yml"],
            "scoreboards" => ["scoreboards.yml"],
            "floatingtexts" => ["floating_texts.yml"],
            "dimensions" => ["dimension_themes.yml"],
            "redstone" => ["redstone.yml"],
            "voting" => ["voting.yml"],
            "tebex" => ["tebex.yml"],
            "lootboxes" => $lootboxes,
            "etherealcrates" => $etherealCrates,
            "dps" => ["dps/Simple.yml", "dps/Unique.yml", "dps/Elite.yml", "dps/Exotic.yml", "dps/Legendary.yml", "dps/Godly.yml"],
            "config" => ["config.yml"],
            "guide" => ["guide.yml"],
        ];
    }

    /**
     * @return string[]
     */
    private function getRestorableFiles(): array
    {
        return ConfigResourceCatalog::getBundledConfigFiles();
    }
}
