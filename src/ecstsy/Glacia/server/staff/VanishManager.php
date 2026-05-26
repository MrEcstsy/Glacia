<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff;

use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

use function array_key_exists;

final class VanishManager
{
    /** @var array<string, array{invisible:bool,silent:bool,name_tag:string,name_tag_always_visible:bool}> */
    private static array $snapshots = [];

    public static function isVanished(Player $player): bool
    {
        return array_key_exists($player->getUniqueId()->toString(), self::$snapshots);
    }

    public static function canViewerSeeVanished(?Player $viewer): bool
    {
        return $viewer !== null && $viewer->hasPermission("glacia.staff.see-hidden");
    }

    public static function toggle(Player $player, bool $broadcastJoinOnReveal = true): bool
    {
        if (self::isVanished($player)) {
            self::disable($player, $broadcastJoinOnReveal);
            return false;
        }

        self::enable($player);
        return true;
    }

    public static function enable(Player $player): void
    {
        if (self::isVanished($player)) {
            return;
        }

        $uuid = $player->getUniqueId()->toString();
        self::$snapshots[$uuid] = [
            "invisible" => $player->isInvisible(),
            "silent" => $player->isSilent(),
            "name_tag" => $player->getNameTag(),
            "name_tag_always_visible" => $player->isNameTagAlwaysVisible(),
        ];

        $player->setInvisible(true);
        $player->setSilent(true);
        $player->setNameTag("");
        $player->setNameTagAlwaysVisible(false);
        $player->getXpManager()->setCanAttractXpOrbs(false);

        foreach (Server::getInstance()->getOnlinePlayers() as $viewer) {
            if ($viewer === $player) {
                continue;
            }

            if (self::canViewerSeeVanished($viewer)) {
                $viewer->showPlayer($player);
                continue;
            }

            $viewer->hidePlayer($player);
            $viewer->getNetworkSession()->sendDataPacket(
                PlayerListPacket::remove([
                    PlayerListEntry::createRemovalEntry($player->getUniqueId()),
                ])
            );
        }
    }

    public static function disable(Player $player, bool $broadcastJoinOnReveal = true): void
    {
        $uuid = $player->getUniqueId()->toString();
        $snapshot = self::$snapshots[$uuid] ?? null;
        if ($snapshot === null) {
            return;
        }

        unset(self::$snapshots[$uuid]);

        $player->setInvisible($snapshot["invisible"]);
        $player->setSilent($snapshot["silent"]);
        $player->setNameTag($snapshot["name_tag"]);
        $player->setNameTagAlwaysVisible($snapshot["name_tag_always_visible"]);
        $player->getXpManager()->setCanAttractXpOrbs(true);

        foreach (Server::getInstance()->getOnlinePlayers() as $viewer) {
            if ($viewer === $player) {
                continue;
            }

            $viewer->showPlayer($player);
            $session = $viewer->getNetworkSession();
            $session->sendDataPacket(
                PlayerListPacket::add([
                    PlayerListEntry::createAdditionEntry(
                        $player->getUniqueId(),
                        $player->getId(),
                        $player->getDisplayName(),
                        $session->getTypeConverter()->getSkinAdapter()->toSkinData($player->getSkin()),
                        $player->getXuid()
                    ),
                ])
            );
        }

        if ($broadcastJoinOnReveal) {
            Server::getInstance()->broadcastMessage(C::colorize("&r&8[&a+&8] &r&8" . $player->getName()));
        }
    }

    public static function handleQuit(Player $player): void
    {
        $uuid = $player->getUniqueId()->toString();
        $snapshot = self::$snapshots[$uuid] ?? null;
        if ($snapshot === null) {
            return;
        }

        unset(self::$snapshots[$uuid]);

        $player->setInvisible($snapshot["invisible"]);
        $player->setSilent($snapshot["silent"]);
        $player->setNameTag($snapshot["name_tag"]);
        $player->setNameTagAlwaysVisible($snapshot["name_tag_always_visible"]);
        $player->getXpManager()->setCanAttractXpOrbs(true);
    }

    public static function syncJoin(Player $joiner): void
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            if ($online === $joiner || !self::isVanished($online)) {
                continue;
            }

            if (!self::canViewerSeeVanished($joiner)) {
                $joiner->hidePlayer($online);
                $joiner->getNetworkSession()->sendDataPacket(
                    PlayerListPacket::remove([
                        PlayerListEntry::createRemovalEntry($online->getUniqueId()),
                    ])
                );
            }
        }
    }

    /**
     * @param PlayerListEntry[] $entries
     * @return PlayerListEntry[]
     */
    public static function filterPlayerListAddEntries(array $entries, ?Player $viewer): array
    {
        if (self::canViewerSeeVanished($viewer)) {
            return $entries;
        }

        $filtered = [];
        foreach ($entries as $entry) {
            $player = Server::getInstance()->getPlayerByUUID($entry->uuid);
            if ($player !== null && self::isVanished($player)) {
                continue;
            }
            $filtered[] = $entry;
        }

        return $filtered;
    }
}
