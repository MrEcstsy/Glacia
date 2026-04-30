<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\Loader;

final class StorageUtils {

    /**
     * @return array<mixed>|null
     */
    public static function decodeJsonString(string $raw, string $context): ?array {
        if (trim($raw) === "") {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Loader::getInstance()->getLogger()->warning("[Storage] Failed to decode {$context}: " . $e->getMessage());
            return null;
        }

        if (!is_array($decoded)) {
            Loader::getInstance()->getLogger()->warning("[Storage] Ignoring non-array payload for {$context}.");
            return null;
        }

        return $decoded;
    }

    public static function encodeJsonString(mixed $payload, string $context, int $flags = 0): ?string {
        try {
            return json_encode($payload, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Loader::getInstance()->getLogger()->warning("[Storage] Failed to encode {$context}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<mixed>|null
     */
    public static function readJsonFile(string $path, string $context): ?array {
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === "") {
            return null;
        }

        return self::decodeJsonString($raw, $context);
    }

    public static function writeJsonFile(string $path, mixed $payload, string $context): bool {
        $json = self::encodeJsonString($payload, $context, JSON_PRETTY_PRINT);
        if ($json === null) {
            return false;
        }

        $tempPath = $path . ".tmp";
        if (@file_put_contents($tempPath, $json, LOCK_EX) === false) {
            Loader::getInstance()->getLogger()->warning("[Storage] Failed to write temp file for {$context}.");
            return false;
        }

        if (is_file($path) && !@unlink($path)) {
            @unlink($tempPath);
            Loader::getInstance()->getLogger()->warning("[Storage] Failed to replace existing file for {$context}.");
            return false;
        }

        if (!@rename($tempPath, $path)) {
            @unlink($tempPath);
            Loader::getInstance()->getLogger()->warning("[Storage] Failed to finalize write for {$context}.");
            return false;
        }

        return true;
    }
}
