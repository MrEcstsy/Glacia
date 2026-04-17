<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\args\RawStringArgument;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\blackmarket\BlackMarketAuctionManager;
use ecstsy\Glacia\server\economy\SellHandler;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class GlaciaReloadCommand extends BaseCommand {

    private const TARGETS = [
        "all" => ["items.yml", "shop.yml", "sell.yml", "blackmarket.yml", "dps/Simple.yml", "dps/Unique.yml", "dps/Elite.yml", "dps/Exotic.yml", "dps/Legendary.yml", "dps/Godly.yml", "config.yml"],
        "items" => ["items.yml"],
        "shop" => ["shop.yml"],
        "sell" => ["sell.yml"],
        "blackmarket" => ["blackmarket.yml"],
        "dps" => ["dps/Simple.yml", "dps/Unique.yml", "dps/Elite.yml", "dps/Exotic.yml", "dps/Legendary.yml", "dps/Godly.yml"],
        "config" => ["config.yml"],
    ];

    private const RESTORABLE = [
        "items.yml",
        "shop.yml",
        "sell.yml",
        "blackmarket.yml",
        "dps/Simple.yml",
        "dps/Unique.yml",
        "dps/Elite.yml",
        "dps/Exotic.yml",
        "dps/Legendary.yml",
        "dps/Godly.yml",
    ];

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("target", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $action = strtolower((string) ($args["action"] ?? "reload"));
        $target = strtolower((string) ($args["target"] ?? "all"));

        if (!isset(self::TARGETS[$target])) {
            $sender->sendMessage(C::colorize("&r&cUnknown target. Use: all, items, shop, sell, blackmarket, dps, config"));
            return;
        }

        $files = self::TARGETS[$target];

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

        $sender->sendMessage(C::colorize("&r&cUsage: /greload <reload|restore> [all|items|shop|sell|blackmarket|dps|config]"));
    }

    private function restoreFiles(array $files): int {
        $loader = Loader::getInstance();
        $restored = 0;

        foreach ($files as $file) {
            if (!in_array($file, self::RESTORABLE, true)) {
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
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}
