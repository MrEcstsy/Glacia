<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\scoreboard;

use ecstsy\Glacia\player\PlayerManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Yanoox\ScoreBoardAPI;

final class ScoreboardHelper {

    private const SETTING_KEY = "scoreboard";

    private const LINE_DATE = 1;
    private const LINE_PLAYER_HEADER = 3;
    private const LINE_RANK = 4;
    private const LINE_BALANCE = 5;
    private const LINE_KDR = 6;
    private const LINE_POWER = 7;
    private const LINE_BREAK = 8;
    private const LINE_FACTION_HEADER = 9;
    private const LINE_FACTION_MEMBERS = 10;
    private const LINE_FACTION_CLAIMS = 11;
    private const LINE_FACTION_POWER = 12;
    private const LINE_STORE = 14;

    public static function initScoreboard(Player $player): void
    {
        if (!self::isEnabled($player)) {
            self::hideScoreboard($player);
            return;
        }

        ScoreBoardAPI::sendScore($player, C::colorize("&r&l&bGLACIA &r&fFACTIONS"));
        self::refreshScoreboard($player);
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

        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return;
        }

        self::setLine($player, self::LINE_DATE, self::pad("&r&7     " . date("M d, Y")));
        self::setLine($player, 2, "  ");
        self::setLine($player, 13, " ");
        self::setLine($player, self::LINE_STORE, self::pad("&r&f    store.etherealpe.com"));

        self::updatePlayerLines($player);
        self::updateFactionLines($player);
    }

    public static function updatePlayerLines(Player $player): void
    {
        if (!ScoreBoardAPI::hasScore($player)) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return;
        }

        $rank = $session->getRankObject()?->getDisplayName() ?? ucfirst($session->getRank());

        self::setLine($player, self::LINE_PLAYER_HEADER, self::pad("&r&l&b" . strtoupper($player->getName())));
        self::setLine($player, self::LINE_RANK, self::pad("&r&b&l> &r&fRank&7: &b" . $rank));
        self::setLine($player, self::LINE_BALANCE, self::pad("&r&b&l> &r&fBalance&7: &a$" . self::formatScoreboardMoney($session->getBalance())));
        self::setLine($player, self::LINE_KDR, self::pad("&r&b&l> &r&fKDR&7: &b" . self::formatKdr($session->getKills(), $session->getDeaths(), $session->getKDRRatio())));
        self::setLine($player, self::LINE_POWER, self::pad("&r&b&l> &r&fPower&7: &b" . number_format($session->getPower())));
        self::setLine($player, self::LINE_BREAK, "    ");
    }

    public static function updateBalanceLine(Player $player): void
    {
        if (!ScoreBoardAPI::hasScore($player)) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return;
        }

        self::setLine($player, self::LINE_BALANCE, self::pad("&r&b&l> &r&fBalance&7: &a$" . self::formatScoreboardMoney($session->getBalance())));
    }

    public static function updateRankLine(Player $player): void
    {
        if (!ScoreBoardAPI::hasScore($player)) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return;
        }

        $rank = $session->getRankObject()?->getDisplayName() ?? ucfirst($session->getRank());
        self::setLine($player, self::LINE_RANK, self::pad("&r&b&l> &r&fRank&7: &b" . $rank));
    }

    public static function updateKDRLine(Player $player): void
    {
        if (!ScoreBoardAPI::hasScore($player)) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return;
        }

        self::setLine($player, self::LINE_KDR, self::pad("&r&b&l> &r&fKDR&7: &b" . self::formatKdr($session->getKills(), $session->getDeaths(), $session->getKDRRatio())));
    }

    public static function updatePowerLine(Player $player): void
    {
        if (!ScoreBoardAPI::hasScore($player)) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return;
        }

        self::setLine($player, self::LINE_POWER, self::pad("&r&b&l> &r&fPower&7: &b" . number_format($session->getPower())));
    }

    public static function updateChatLine(Player $player): void
    {
        if (!ScoreBoardAPI::hasScore($player)) {
            return;
        }

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
    }

    public static function updateFactionLines(Player $player): void
    {
        if (!ScoreBoardAPI::hasScore($player)) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return;
        }

        $faction = $session->getFaction();
        if ($faction === null) {
            self::setLine($player, self::LINE_FACTION_HEADER, self::pad("&r&l&bNO FACTION"));
            self::setLine($player, self::LINE_FACTION_MEMBERS, self::pad("&r&b&l> &r&fMembers&7: &b0/0"));
            self::setLine($player, self::LINE_FACTION_CLAIMS, self::pad("&r&b&l> &r&fClaims&7: &b0/0"));
            self::setLine($player, self::LINE_FACTION_POWER, self::pad("&r&b&l> &r&fPower&7: &b0/0"));
            return;
        }

        $role = strtoupper($session->getFactionRole() ?? "recruit");
        self::setLine($player, self::LINE_FACTION_HEADER, self::pad("&r&l&b" . strtoupper($faction->getName())));
        self::setLine($player, self::LINE_FACTION_MEMBERS, self::pad("&r&b&l> &r&fMembers&7: &b" . count($faction->getOnlineMembers()) . "/" . count($faction->getMembers())));
        self::setLine($player, self::LINE_FACTION_CLAIMS, self::pad("&r&b&l> &r&fClaims&7: &b" . $faction->getClaimCount() . "/" . $faction->getMaxClaims()));
        self::setLine($player, self::LINE_FACTION_POWER, self::pad("&r&b&l> &r&fPower&7: &b" . $faction->getFactionPower() . "/" . $faction->getMaxPower()));
    }

    private static function formatKdr(int $kills, int $deaths, float $ratio): string
    {
        return $kills . "&7/&b" . $deaths . " &7(" . number_format($ratio, 2) . ")";
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

    private static function formatScoreboardMoney(float $amount): string
    {
        if ($amount < 10_000_000) {
            return number_format($amount, 2);
        }

        $units = [
            1_000_000_000 => "B",
            1_000_000 => "M",
        ];

        foreach ($units as $value => $suffix) {
            if ($amount >= $value) {
                $formatted = round($amount / $value, 1);
                $text = number_format($formatted, 1, ".", "");
                $text = str_ends_with($text, ".0") ? substr($text, 0, -2) : $text;
                return $text . $suffix;
            }
        }

        return number_format($amount, 2);
    }

    private static function pad(string $text): string
    {
        return C::colorize("  " . $text . "  ");
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
