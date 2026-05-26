<?php

declare(strict_types=1);

namespace ecstsy\Glacia\network;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\StorageUtils;
use pocketmine\entity\InvalidSkinException;
use pocketmine\entity\Skin;
use pocketmine\network\mcpe\convert\LegacySkinAdapter;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;

/**
 * PocketMine's {@see LegacySkinAdapter} replaces Bedrock "persona" (Character Creator) skins with random RGBA noise,
 * which looks broken to players. This adapter keeps normal behaviour for classic skins, and for persona:
 * - uses the baked skin bitmap if it is already a supported size (same limits as {@see Skin::ACCEPTED_SKIN_SIZES});
 * - otherwise uses a small neutral fallback instead of noise.
 *
 * Very large persona geometry JSON is not forwarded (empty string) when using a baked bitmap to limit overhead.
 */
final class PersonaTolerantSkinAdapter extends LegacySkinAdapter
{
    private const FALLBACK_PNG_RESOURCE = "skins/invalid_default.png";
    private const FALLBACK_RAW_RESOURCE = "skins/invalid_default.rgba";

    public function fromSkinData(SkinData $data): Skin
    {
        if (!$data->isPersona()) {
            return parent::fromSkinData($data);
        }

        $skinImage = $data->getSkinImage();
        $raw = $skinImage->getData();
        if ($raw !== '' && in_array(strlen($raw), Skin::ACCEPTED_SKIN_SIZES, true)) {
            try {
                $capeData = $data->isPersonaCapeOnClassic() ? '' : $data->getCapeImage()->getData();
                if ($capeData !== '' && strlen($capeData) !== 8192) {
                    $capeData = '';
                }

                $geometryName = 'geometry.humanoid.custom';
                $patch = StorageUtils::decodeJsonString($data->getResourcePatch(), "persona resource patch");
                if (is_array($patch) && isset($patch['geometry']['default']) && is_string($patch['geometry']['default']) && $patch['geometry']['default'] !== '') {
                    $geometryName = $patch['geometry']['default'];
                }

                $skinId = $data->getSkinId();
                if ($skinId === '') {
                    $skinId = 'Persona_Custom';
                }

                return new Skin($skinId, $raw, $capeData, $geometryName, '');
            } catch (InvalidSkinException) {
                // fall through to fallback
            }
        }

        try {
            return new Skin('Persona_Fallback', self::loadFallbackTexture(), '', 'geometry.humanoid.custom', '');
        } catch (InvalidSkinException) {
            return parent::fromSkinData($data);
        }
    }

    private static function loadFallbackTexture(): string
    {
        $pngTexture = self::loadFallbackTextureFromPng();
        if ($pngTexture !== '') {
            return $pngTexture;
        }

        $resourcePath = Loader::getInstance()->getResourcePath(self::FALLBACK_RAW_RESOURCE);
        $raw = @file_get_contents($resourcePath);
        if (is_string($raw) && in_array(strlen($raw), Skin::ACCEPTED_SKIN_SIZES, true)) {
            return $raw;
        }

        return self::neutralHumanoidTexture();
    }

    private static function loadFallbackTextureFromPng(): string
    {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagecolorat')) {
            return '';
        }

        $resourcePath = Loader::getInstance()->getResourcePath(self::FALLBACK_PNG_RESOURCE);
        if (!is_file($resourcePath)) {
            return '';
        }

        try {
            $image = @imagecreatefrompng($resourcePath);
            if ($image === false) {
                return '';
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $expectedLength = $width * $height * 4;
            if (!in_array($expectedLength, Skin::ACCEPTED_SKIN_SIZES, true)) {
                imagedestroy($image);
                return '';
            }

            $out = '';
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $argb = imagecolorat($image, $x, $y);
                    $alpha = ($argb >> 24) & 0x7F;
                    $red = ($argb >> 16) & 0xFF;
                    $green = ($argb >> 8) & 0xFF;
                    $blue = $argb & 0xFF;
                    $out .= chr($red) . chr($green) . chr($blue) . chr((int) round((127 - $alpha) * 255 / 127));
                }
            }
            imagedestroy($image);

            return $out;
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Valid 64x32 RGBA: simple two-tone humanoid-ish fill (not random noise).
     */
    private static function neutralHumanoidTexture(): string
    {
        $skin = "\x9A\x7B\x5A\xFF";
        $shirt = "\x2B\x55\xAA\xFF";
        $w = 64;
        $h = 32;
        $out = '';
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $torso = $x >= 20 && $x < 44 && $y >= 8 && $y < 20;
                $out .= $torso ? $shirt : $skin;
            }
        }

        return $out;
    }
}
