<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;

use function file_exists;

final class SplitConfigHelper
{
    /**
     * Files migrated out of config.yml and handled separately at runtime.
     *
     * @return string[]
     */
    public static function getManagedFiles(): array
    {
        return [
            "redstone.yml",
            "voting.yml",
            "tebex.yml",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadRootSection(Plugin $plugin, string $fileName, string $legacyKey, string $legacyFile = "config.yml"): array
    {
        self::ensureDedicatedFile($plugin, $fileName, $legacyKey, $legacyFile);

        GeneralUtils::reloadConfiguration($plugin, $fileName);
        $config = GeneralUtils::getConfiguration($plugin, $fileName);
        $data = $config?->getAll() ?? [];
        if ($data !== []) {
            return $data;
        }

        $legacy = GeneralUtils::getConfiguration($plugin, $legacyFile);
        return GeneralUtils::getNestedArray($legacy, $legacyKey, []);
    }

    private static function ensureDedicatedFile(Plugin $plugin, string $fileName, string $legacyKey, string $legacyFile): void
    {
        $filePath = $plugin->getDataFolder() . $fileName;
        if (file_exists($filePath)) {
            return;
        }

        $legacy = GeneralUtils::getConfiguration($plugin, $legacyFile);
        $legacyData = GeneralUtils::getNestedArray($legacy, $legacyKey, []);
        if ($legacyData !== []) {
            $config = new Config($filePath, Config::YAML);
            $config->setAll($legacyData);
            $config->save();
            GeneralUtils::forgetConfiguration($plugin, $fileName);
            $plugin->getLogger()->notice("Migrated legacy config section '{$legacyKey}' from {$legacyFile} to {$fileName}.");
            return;
        }

        GeneralUtils::syncResourceFile($plugin, $fileName, false);
    }
}
