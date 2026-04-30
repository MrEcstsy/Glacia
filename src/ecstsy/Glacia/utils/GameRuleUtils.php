<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\player\GlaciaPlayer;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\FloatGameRule;
use pocketmine\network\mcpe\protocol\types\IntGameRule;
use pocketmine\player\Player;

/**
 * Per-player client game rules via {@see GameRulesChangedPacket}.
 * Bedrock rule for the F3-style coordinate readout: {@see https://wiki.bedrock.dev/documentation/game-rules.html} {@code showcoordinates}
 */
final class GameRuleUtils
{
    public const RULE_SHOW_COORDINATES = "showcoordinates";

    /**
     * @param mixed $value bool, int, or float
     */
    public static function modifyGameRule(Player $player, string $rule, mixed $value, bool $isEditable = false): void
    {
        if (is_bool($value)) {
            $gameRule = new BoolGameRule($value, $isEditable);
        } elseif (is_int($value)) {
            $gameRule = new IntGameRule($value, $isEditable);
        } elseif (is_float($value)) {
            $gameRule = new FloatGameRule($value, $isEditable);
        } else {
            return;
        }

        $player->getNetworkSession()->sendDataPacket(GameRulesChangedPacket::create([
            $rule => $gameRule,
        ]));
    }

    public static function syncShowCoordinatesFromSession(?GlaciaPlayer $session, Player $player): void
    {
        $enabled = true;
        if ($session !== null) {
            $v = $session->getSetting("show_coordinates");
            $enabled = $v === null ? true : (bool) $v;
        }
        self::modifyGameRule($player, self::RULE_SHOW_COORDINATES, $enabled, true);
    }
}
