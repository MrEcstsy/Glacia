<?php

declare(strict_types=1);

namespace ecstsy\MartianUtilities\utils;

use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;
use pocketmine\world\Position;

final class GeneralUtils {

    private static array $configCache = [];
    
    /**
     * Gets a configuration file from a plugin's data folder.
     *
     * @param Plugin $plugin
     * @param string $fileName
     * @return Config|null
     */
    public static function getConfiguration(Plugin $plugin, string $fileName): ?Config {
        $pluginFolder = $plugin->getDataFolder();
        $filePath = $pluginFolder . $fileName;

        if (isset(self::$configCache[$filePath])) {
            return self::$configCache[$filePath];
        }


        if (!file_exists($filePath)) {
            $plugin->getLogger()->warning("Configuration file '$filePath' not found.");
            return null;
        }

        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'yml':
            case 'yaml':
                $config = new Config($filePath, Config::YAML);
                break;
            case 'json':
                $config = new Config($filePath, Config::JSON);
                break;
            default:
                $plugin->getLogger()->warning("Unsupported configuration file format for '$filePath'.");
                return null;
        }

        self::$configCache[$filePath] = $config;
        return $config;
    }

    public static function addParticleToPosition(Position $position, string $particleName): void {
        $world = $position->getWorld();
    
        foreach ($world->getPlayers() as $player) {
            if ($player->isOnline() && $player->getPosition()->distance($position) <= 10) { 
                $packet = new SpawnParticleEffectPacket();
                $packet->position = $position->asVector3();
                $packet->particleName = $particleName;
    
                $player->getNetworkSession()->sendDataPacket($packet);
            }
        }
    }

    /**
     * Converts a given time duration from seconds into a more human-readable format.
     * The time is represented in weeks, days, hours, minutes, and seconds.
     * 
     * @param int $seconds The total number of seconds to convert.
     * @return string A string representation of the time, formatted as a comma-separated
     *                list with units (e.g., '1w, 2d, 3h, 4m, 5s').
     */
    public static function translateTime(int $seconds): string
    {
        $timeUnits = [
            'w' => 60 * 60 * 24 * 7,
            'd' => 60 * 60 * 24,
            'h' => 60 * 60,
            'm' => 60,
            's' => 1,
        ];
        
        $parts = [];

        foreach ($timeUnits as $unit => $value) {
            if ($seconds >= $value) {
                $amount = floor($seconds / $value);
                $seconds %= $value;
                $parts[] = $amount . $unit;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Parses a human-readable time duration string into a number of seconds.
     *
     * The string should be a comma-separated list of time units, where each unit
     * consists of a number followed by a letter indicating the unit. The supported
     * units are:
     *
     * * `w` for weeks
     * * `d` for days
     * * `h` for hours
     * * `m` for minutes
     * * `s` for seconds
     *
     * For example, the string "1w, 2d, 3h, 4m, 5s" would be parsed to 12345 seconds.
     *
     * If the string does not contain any of the supported time units, but is
     * a valid number, it will be treated as a number of seconds.
     *
     * @param string $input The time duration string to parse.
     * @return int The total number of seconds represented by the input string.
     */
    public static function parseTimeString(string $input): int
    {
        $units = [
            'w' => 604800, 
            'd' => 86400,  
            'h' => 3600,   
            'm' => 60,
            's' => 1
        ];

        $pattern = '/(\d+)([wdhms])/i';
        preg_match_all($pattern, strtolower($input), $matches, PREG_SET_ORDER);

        $seconds = 0;
        foreach ($matches as $match) {
            $value = (int)$match[1];
            $unit = $match[2];
            if (isset($units[$unit])) {
                $seconds += $value * $units[$unit];
            }
        }

        if ($seconds === 0 && is_numeric($input)) {
            $seconds = (int)$input;
        }

        return $seconds;
    }

    /**
     * @param int $integer
     * @return string
     */
    public static function getRomanNumeral(int $integer): string
    {
        $romanString = "";
        while ($integer > 0) {
            $romanNumeralConversionTable = [
                'M' => 1000,
                'CM' => 900,
                'D' => 500,
                'CD' => 400,
                'C' => 100,
                'XC' => 90,
                'L' => 50,
                'XL' => 40,
                'X' => 10,
                'IX' => 9,
                'V' => 5,
                'IV' => 4,
                'I' => 1
            ];
            foreach ($romanNumeralConversionTable as $rom => $arb) {
                if ($integer >= $arb) {
                    $integer -= $arb;
                    $romanString .= $rom;
                    break;
                }
            }
        }
        return $romanString;
    }

    /**
     * @param Item $item
     * @return bool
     */
    public static function hasTag(Item $item, string $name, string $value = "true"): bool {
        $namedTag = $item->getNamedTag();
        if ($namedTag instanceof CompoundTag) {
            $tag = $namedTag->getTag($name);
            return $tag instanceof StringTag && $tag->getValue() === $value;
        }
        return false;
    }

    /**
     * Parses a random number string in the format "{min-max}".
     * Returns a random integer between min and max.
     */
    public static function parseRandomNumber(int|string $input): int {
        if (is_int($input)) {
            return $input;
        }
        
        if (preg_match('/\{(\d+)-(\d+)\}/', $input, $matches)) {
            $min = (int)$matches[1];
            $max = (int)$matches[2];
            return mt_rand($min, $max);
        }
        
        return (int)$input;
    }  

    public static function parseDynamicMessage(string $message): string {
        $pattern = "/<random_word>(.*?)<\/random_word>/";
        preg_match($pattern, $message, $matches);

        if (isset($matches[1])) {
            $wordCandidates = explode(",", $matches[1]);
            $randomIndex = mt_rand(0, count($wordCandidates) - 1);
            $word = $wordCandidates[$randomIndex];

            return str_replace("<random_word>" . $matches[1] . "</random_word>", $word, $message);
        }

        return $message;
    }
}