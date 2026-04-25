<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\args\RawStringArgument;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\blackmarket\BlackMarketAuctionManager;
use ecstsy\Glacia\server\economy\SellHandler;
use ecstsy\Glacia\server\items\genbuckets\GenBucketManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\levels\PlayerLevelManager;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\Glacia\server\spawners\SpawnerWorthManager;
use ecstsy\Glacia\server\worldborder\WorldBorderManager;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\server\zones\ZoneMobManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class GlaciaReloadCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("target", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $action = strtolower((string) ($args["action"] ?? "reload"));
        $target = strtolower((string) ($args["target"] ?? "all"));

        $targets = $this->getTargets();
        if (!isset($targets[$target])) {
            $sender->sendMessage(C::colorize("&r&cUnknown target. Use: all, items, shop, sell, kits, warlords, blackmarket, enchanter, auctionhouse, quests, spawners, misc, messages, announcer, zonemobs, factionupgrades, pets, runes, levels, zones, capture, fund, envoy, genbuckets, slotbot, combat, lootboxes, dps, config"));
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
                $sender->sendMessage(C::colorize("&r&aRestored &f{$restored}&a resource file(s) and reloaded &f{$target}&a."));
                return;
        }

        $sender->sendMessage(C::colorize("&r&cUsage: /greload <reload|restore> [all|items|shop|sell|kits|warlords|blackmarket|enchanter|auctionhouse|quests|spawners|misc|messages|announcer|zonemobs|factionupgrades|pets|runes|levels|zones|capture|fund|envoy|genbuckets|slotbot|combat|lootboxes|dps|config]"));
    }

    private function restoreFiles(array $files): int {
        $loader = Loader::getInstance();
        $restored = 0;

        foreach ($files as $file) {
            if (!in_array($file, $this->getRestorableFiles(), true)) {
                continue;
            }

            $loader->saveResource($file, true);
            $restored++;
        }

        return $restored;
    }

    private function reloadFiles(array $files): void {
        $loader = Loader::getInstance();

        foreach ($files as $file) {
            GeneralUtils::reloadConfiguration($loader, $file);
        }

        if (in_array("sell.yml", $files, true)) {
            SellHandler::loadFromConfig();
        }

        if (in_array("blackmarket.yml", $files, true)) {
            BlackMarketAuctionManager::getInstance()->reload();
        }

        if (in_array("config.yml", $files, true)) {
            Loader::getJackpotInstance()->reloadSettingsFromConfig();
        }

        if (in_array("quests.yml", $files, true)) {
            QuestInstance::loadFromConfig();
        }

        if (in_array("spawners.yml", $files, true)) {
            SpawnerWorthManager::loadFromConfig();
            Loader::getFactionManager()->recalculateSpawnerWorths();
        }

        if (in_array("messages.yml", $files, true)) {
            \ecstsy\Glacia\utils\lang\MessageTemplates::load();
        }

        if (in_array("faction_upgrades.yml", $files, true)) {
            Loader::getFactionUpgradeManager()->applyAllEffects();
        }

        if (in_array("fund.yml", $files, true)) {
            Loader::getGlobalFundManager()->reloadConfig();
        }

        if (in_array("envoy.yml", $files, true)) {
            Loader::getEnvoyManager()->reload();
        }

        if (in_array("genbuckets.yml", $files, true)) {
            GenBucketManager::loadFromConfig();
        }

        if (in_array("runes.yml", $files, true)) {
            RuneManager::loadFromConfig();
        }

        if (in_array("misc.yml", $files, true)) {
            WorldBorderManager::loadFromConfig();
        }

        if (in_array("levels.yml", $files, true)) {
            PlayerLevelManager::loadFromConfig();
        }

        if (in_array("zones.yml", $files, true)) {
            ZoneManager::loadFromConfig();
        }

        if (in_array("zone_mobs.yml", $files, true)) {
            ZoneMobManager::loadFromConfig();
        }

        if (in_array("capturepoints.yml", $files, true)) {
            Loader::getCaptureManager()->load();
        }

        if (array_filter($files, static fn(string $file): bool => str_starts_with($file, "lootboxes/")) !== []) {
            LootboxRepository::reload();
        }
    }

    public function getPermission(): string {
        return "glacia.admin";
    }

    /**
     * @return array<string, string[]>
     */
    private function getTargets(): array
    {
        $lootboxes = LootboxRepository::getBundledResourceFiles();

        return [
            "all" => array_merge(["items.yml", "shop.yml", "sell.yml", "kits.yml", "warlords.yml", "blackmarket.yml", "enchanter.yml", "auctionhouse.yml", "quests.yml", "spawners.yml", "misc.yml", "messages.yml", "announcer.yml", "zone_mobs.yml", "faction_upgrades.yml", "pets.yml", "runes.yml", "levels.yml", "zones.yml", "capturepoints.yml", "fund.yml", "envoy.yml", "genbuckets.yml", "slotbot.yml", "combat.yml"], $lootboxes, ["dps/Simple.yml", "dps/Unique.yml", "dps/Elite.yml", "dps/Exotic.yml", "dps/Legendary.yml", "dps/Godly.yml", "config.yml"]),
            "items" => ["items.yml"],
            "shop" => ["shop.yml"],
            "sell" => ["sell.yml"],
            "kits" => ["kits.yml"],
            "warlords" => ["warlords.yml"],
            "blackmarket" => ["blackmarket.yml"],
            "enchanter" => ["enchanter.yml"],
            "auctionhouse" => ["auctionhouse.yml"],
            "quests" => ["quests.yml"],
            "spawners" => ["spawners.yml"],
            "misc" => ["misc.yml"],
            "messages" => ["messages.yml"],
            "announcer" => ["announcer.yml"],
            "zonemobs" => ["zone_mobs.yml"],
            "factionupgrades" => ["faction_upgrades.yml"],
            "pets" => ["pets.yml"],
            "runes" => ["runes.yml"],
            "levels" => ["levels.yml"],
            "zones" => ["zones.yml"],
            "capture" => ["capturepoints.yml"],
            "fund" => ["fund.yml"],
            "envoy" => ["envoy.yml"],
            "genbuckets" => ["genbuckets.yml"],
            "slotbot" => ["slotbot.yml"],
            "combat" => ["combat.yml"],
            "lootboxes" => $lootboxes,
            "dps" => ["dps/Simple.yml", "dps/Unique.yml", "dps/Elite.yml", "dps/Exotic.yml", "dps/Legendary.yml", "dps/Godly.yml"],
            "config" => ["config.yml"],
        ];
    }

    /**
     * @return string[]
     */
    private function getRestorableFiles(): array
    {
        return array_merge(
            [
                "items.yml",
                "shop.yml",
                "sell.yml",
                "kits.yml",
                "warlords.yml",
                "blackmarket.yml",
                "enchanter.yml",
                "auctionhouse.yml",
                "quests.yml",
                "spawners.yml",
                "misc.yml",
                "messages.yml",
                "announcer.yml",
                "zone_mobs.yml",
                "faction_upgrades.yml",
                "pets.yml",
                "runes.yml",
                "levels.yml",
                "zones.yml",
                "capturepoints.yml",
                "fund.yml",
                "envoy.yml",
                "genbuckets.yml",
                "slotbot.yml",
                "combat.yml",
                "dps/Simple.yml",
                "dps/Unique.yml",
                "dps/Elite.yml",
                "dps/Exotic.yml",
                "dps/Legendary.yml",
                "dps/Godly.yml",
                "config.yml",
            ],
            LootboxRepository::getBundledResourceFiles()
        );
    }
}
