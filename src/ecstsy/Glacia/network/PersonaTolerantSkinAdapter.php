<?php

declare(strict_types=1);

namespace ecstsy\Glacia\network;

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
            return new Skin('Persona_Fallback', self::neutralHumanoidTexture(), '', 'geometry.humanoid.custom', '');
        } catch (InvalidSkinException) {
            return parent::fromSkinData($data);
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
