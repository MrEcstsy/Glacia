<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

/**
 * Reads integer NBT without throwing when the tag is the wrong type (e.g. YAML stored as string).
 */
final class NbtLenient
{
    public static function readInt(
        ?CompoundTag $tag,
        string $key,
        int $default,
        int $min = PHP_INT_MIN,
        int $max = PHP_INT_MAX,
        ?Player $notifyParseFailure = null
    ): int {
        if ($tag === null) {
            return self::clamp($default, $min, $max);
        }
        $child = $tag->getTag($key);
        if ($child === null) {
            return self::clamp($default, $min, $max);
        }
        $parsed = self::tryParseIntFromTag($child);
        if ($parsed === null) {
            if ($notifyParseFailure !== null) {
                $notifyParseFailure->sendMessage(C::colorize(
                    "&r&cInvalid item data&r&7: field &f" . $key . "&7 is not a valid number. Using &f" . $default . "&7 until the item is fixed or replaced."
                ));
            }
            return self::clamp($default, $min, $max);
        }

        return self::clamp($parsed, $min, $max);
    }

    private static function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private static function tryParseIntFromTag(Tag $tag): ?int
    {
        if ($tag instanceof ByteTag || $tag instanceof ShortTag || $tag instanceof IntTag || $tag instanceof LongTag) {
            return $tag->getValue();
        }
        if ($tag instanceof FloatTag || $tag instanceof DoubleTag) {
            return (int) round($tag->getValue());
        }
        if ($tag instanceof StringTag) {
            $validated = filter_var(trim($tag->getValue()), FILTER_VALIDATE_INT);

            return $validated === false ? null : $validated;
        }

        return null;
    }
}
