<?php
declare(strict_types=1);

namespace ecstsy\Glacia\server\ranks;

use pocketmine\utils\SingletonTrait;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\permission\PermissionAttachment;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;

final class RankManager {
    use SingletonTrait;

    /** @var Rank[] */
    private array $ranks = [];

    private array $attachments = [];

    public function __construct() {
        self::setInstance($this);
        $this->load();
    }

    private function load(): void {
        Loader::getDatabase()->executeSelect(QueryStmts::PLAYER_RANK_SELECT, [], function(array $rows): void {
            foreach ($rows as $r) {
                $this->ranks[$r["rank_key"]] = new Rank(
                    $r["rank_key"],
                    $r["display_name"],
                    $r["parent_rank"],
                    $r["chat_format"],
                    $r["nametag_format"],
                    []
                );
            }
        });

        Loader::getDatabase()->executeSelect(QueryStmts::PLAYER_PERMISSIONS_SELECT, [], function(array $rows): void {
            foreach ($rows as $r) {
                $this->ranks[$r["rank_key"]]?->addOwnPermission($r["permission"]);
            }
        });
    }

    public function getRank(string $key): ?Rank {
        return $this->ranks[$key] ?? null;
    }

    /** @return Rank[] */
    public function getAllRanks(): array {
        return $this->ranks;
    }

    public function createRank(string $key, string $displayName, ?string $parent, string $chatFmt, string $nametagFmt): Rank {
        Loader::getDatabase()->executeInsert(QueryStmts::PLAYER_RANK_CREATE, [
            "rank_key" => $key,
            "display_name" => $displayName,
            "parent_rank" => $parent,
            "chat_format" => $chatFmt,
            "nametag_format" => $nametagFmt
        ]);
        $rank = new Rank($key, $displayName, $parent, $chatFmt, $nametagFmt,[]);
        return $this->ranks[$key] = $rank;
    }

    public function addPermissionToRank(string $key, string $perm): void {
        Loader::getDatabase()->executeInsert(QueryStmts::PLAYER_PERMISSIONS_CREATE, [
            "rank_key" => $key,
            "permission" => $perm
        ]);
        $this->ranks[$key]?->addOwnPermission($perm);
    }

    public function getAttachment(Player $player): PermissionAttachment {
        $nickname = strtolower($player->getName());

        if (!(isset($this->attachments[$nickname]))) {
            throw new \RuntimeException("No PermissionAttachment for player {$nickname}");
        }

        return $this->attachments[$nickname];
    }

    public function setAttachment(Player $player, PermissionAttachment $attachment): void {
        $nickname = strtolower($player->getName());
        $this->attachments[$nickname] = $attachment;
    }

    public function updatePermission(Player $player): void {
        $attachment = $this->getAttachment($player);
        $session = Loader::getPlayerManager()->getSession($player);
        $rank = $session?->getRankObject();

        if ($rank === null) return;

        $attachment->clearPermissions();

        foreach ($rank->getPermissions() as $permission) {
            if ($permission === "*") {
                foreach (PermissionManager::getInstance()->getPermissions() as $perm) {
                    $attachment->setPermission($perm->getName(), true);
                }
            }
            break;
        }
        $attachment->setPermission($permission, true);
    }     
}
