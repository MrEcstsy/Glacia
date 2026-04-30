<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\scoreboard;

use ecstsy\Glacia\player\PlayerManager;
use pocketmine\player\Player;
use Yanoox\ScoreBoardAPI;

final class ScoreboardHelper {

    private const SETTING_KEY = "scoreboard";
    private const MAX_LINES = 15;

    /** @var array<string, string> */
    private static array $titles = [];

    public static function initScoreboard(Player $player): void
    {
        if (!self::isEnabled($player)) {
            self::hideScoreboard($player);
            return;
        }

        $rendered = ScoreboardProfileManager::renderForPlayer($player);
        if (ScoreBoardAPI::hasScore($player)) {
            ScoreBoardAPI::removeScore($player);
        }

        ScoreBoardAPI::sendScore($player, $rendered["title"]);
        self::$titles[strtolower($player->getName())] = $rendered["title"];
        self::applyLines($player, $rendered["lines"]);
    }

    public static function refreshScoreboard(Player $player): void
    {
        if (!$player->isConnected()) {
            return;
        }

        if (!self::isEnabled($player)) {
            self::hideScoreboard($player);
            return;
        }

        if (!ScoreBoardAPI::hasScore($player)) {
            self::initScoreboard($player);
            return;
        }

        $rendered = ScoreboardProfileManager::renderForPlayer($player);
        $key = strtolower($player->getName());
        if ((self::$titles[$key] ?? null) !== $rendered["title"]) {
            self::initScoreboard($player);
            return;
        }

        self::applyLines($player, $rendered["lines"]);
    }

    public static function updatePlayerLines(Player $player): void
    {
        self::refreshScoreboard($player);
    }

    public static function updateBalanceLine(Player $player): void
    {
        self::refreshScoreboard($player);
    }

    public static function updateRankLine(Player $player): void
    {
        self::refreshScoreboard($player);
    }

    public static function updateKDRLine(Player $player): void
    {
        self::refreshScoreboard($player);
    }

    public static function updatePowerLine(Player $player): void
    {
        self::refreshScoreboard($player);
    }

    public static function updateChatLine(Player $player): void
    {
        self::refreshScoreboard($player);
    }

    public static function updateFactionLines(Player $player): void
    {
        self::refreshScoreboard($player);
    }

    public static function setVisible(Player $player, bool $visible): void
    {
        if ($visible) {
            self::initScoreboard($player);
            return;
        }

        self::hideScoreboard($player);
    }

    public static function hideScoreboard(Player $player): void
    {
        if (ScoreBoardAPI::hasScore($player)) {
            ScoreBoardAPI::removeScore($player);
        }
        unset(self::$titles[strtolower($player->getName())]);
    }

    private static function isEnabled(Player $player): bool
    {
        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return true;
        }

        $setting = $session->getSetting(self::SETTING_KEY);
        return $setting === null ? true : (bool) $setting;
    }

    /**
     * @param string[] $lines
     */
    private static function applyLines(Player $player, array $lines): void
    {
        $lines = array_slice(array_values($lines), 0, self::MAX_LINES);

        for ($line = 1; $line <= self::MAX_LINES; $line++) {
            $text = $lines[$line - 1] ?? null;
            if ($text === null) {
                self::removeLineIfPresent($player, $line);
                continue;
            }

            self::setLine($player, $line, $text);
        }
    }

    private static function removeLineIfPresent(Player $player, int $line): void
    {
        try {
            if (ScoreBoardAPI::getLineScore($player, $line) !== "") {
                ScoreBoardAPI::removeLine($player, $line);
            }
        } catch (\Throwable) {
        }
    }

    private static function setLine(Player $player, int $line, string $text): void
    {
        if (!ScoreBoardAPI::hasScore($player)) {
            return;
        }

        try {
            $old = ScoreBoardAPI::getLineScore($player, $line);
            if ($old === $text) {
                return;
            }

            if ($old === "") {
                ScoreBoardAPI::setScoreLine($player, $line, $text);
                return;
            }

            ScoreBoardAPI::editLineScore($player, $line, $old, $text);
        } catch (\Throwable) {
            ScoreBoardAPI::setScoreLine($player, $line, $text);
        }
    }
}
