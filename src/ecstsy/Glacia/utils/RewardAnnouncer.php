<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\Loader;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class RewardAnnouncer {

    /**
     * @param Item[] $rewards
     */
    public static function announce(Player $opener, string $sourceType, string $sourceName, array $rewards): void
    {
        $rewards = array_values(array_filter($rewards, static fn(Item $reward): bool => self::shouldAnnounce($reward)));
        if ($rewards === []) {
            return;
        }

        $header = strtolower($sourceType) === "slotbot"
            ? "&r&3&l(!) &r&b" . $opener->getName() . " spun the &fSlotbot&b and received"
            : "&r&3&l(!) &r&b" . $opener->getName() . " opened a &r" . self::cleanSourceName($sourceName) . "&r&b and received";
        $message = C::colorize($header);
        foreach ($rewards as $reward) {
            $message .= "\n" . C::colorize("&r&5* &r&f" . $reward->getCount() . "x &r" . self::getItemName($reward));
        }

        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $player) {
            $session = Loader::getPlayerManager()->getSession($player);
            if ((bool) ($session?->getSetting("notifications") ?? true)) {
                $player->sendMessage($message);
            }
        }
    }

    private static function getItemName(Item $item): string
    {
        $customName = $item->getCustomName();
        if ($customName !== "") {
            return $customName;
        }

        return C::colorize("&f" . $item->getName());
    }

    private static function cleanSourceName(string $sourceName): string
    {
        return preg_replace('/&[0-9a-fk-oru]|§[0-9a-fk-oru]/i', '', $sourceName) === "Lootbox"
            ? C::colorize("&fReward")
            : preg_replace('/(?:&[0-9a-fk-oru]|§[0-9a-fk-oru])*Lootbox(?:&[0-9a-fk-oru]|§[0-9a-fk-oru])*:\s*/i', '', $sourceName);
    }
    private static function shouldAnnounce(Item $item): bool
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return true;
        }

        $value = strtolower(trim($glacia->getString("announce", "1")));
        return !in_array($value, ["0", "false", "no", "off"], true);
    }
}
