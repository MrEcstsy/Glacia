<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\chat;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\FactionNameBlocklist;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PrivateMessageManager
{
    private const OUTGOING_FORMAT = "&8[&bGlacia&8] &fYou whispered to %s&f: &7\"%s&7\"";
    private const INCOMING_FORMAT = "&8[&bGlacia&8] %s &fwhispered to you&f: &7\"%s&7\"";

    /** @var array<string, string> sender UUID => last contact UUID */
    private static array $lastContact = [];

    public static function send(Player $sender, Player $target, string $message): void
    {
        $message = trim($message);
        if ($message === "") {
            return;
        }

        $violation = FactionNameBlocklist::firstGenericTextViolation($message, "message");
        if ($violation !== null) {
            $sender->sendMessage(C::colorize("&r&c" . $violation));
            return;
        }

        $senderName = self::getPmDisplayName($sender);
        $targetName = self::getPmDisplayName($target);

        $sender->sendMessage(C::colorize(sprintf(self::OUTGOING_FORMAT, $targetName, $message)));
        $target->sendMessage(C::colorize(sprintf(self::INCOMING_FORMAT, $senderName, $message)));

        $senderUuid = $sender->getUniqueId()->toString();
        $targetUuid = $target->getUniqueId()->toString();
        self::$lastContact[$senderUuid] = $targetUuid;
        self::$lastContact[$targetUuid] = $senderUuid;
    }

    public static function getLastContact(Player $player): ?Player
    {
        $uuid = self::$lastContact[$player->getUniqueId()->toString()] ?? null;
        if ($uuid === null) {
            return null;
        }

        foreach ($player->getServer()->getOnlinePlayers() as $online) {
            if ($online->getUniqueId()->toString() === $uuid) {
                return $online;
            }
        }

        return null;
    }

    private static function getNameColor(Player $player): string
    {
        $session = Loader::getPlayerManager()->getSession($player);
        $format = $session?->getRankObject()?->getChatFormat() ?? "";
        if ($format === "") {
            return "&f";
        }

        $namePos = strpos($format, "{name}");
        $prefix = $namePos === false ? $format : substr($format, 0, $namePos);
        if (!is_string($prefix) || $prefix === "") {
            return "&f";
        }

        if (preg_match_all('/&[0-9a-fr]/i', $prefix, $matches) > 0) {
            $codes = $matches[0];
            if ($codes !== []) {
                return (string) end($codes);
            }
        }

        return "&f";
    }

    private static function getPmDisplayName(Player $player): string
    {
        return self::getNameColor($player) . $player->getName();
    }
}
