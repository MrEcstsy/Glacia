<?php

declare(strict_types=1);

namespace ecstsy\Glacia\player;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\Glacia\utils\StorageUtils;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class PlayerManager {
    use SingletonTrait;

    /** @var GlaciaPlayer[] */
    private array $sessions = [];

    public function __construct(public Loader $plugin)
    {
        self::setInstance($this);
        $this->loadSessions();
    }

    /**
     * @internal
     */
    private function loadSessions(): void {
        Loader::getDatabase()->executeSelect(QueryStmts::PLAYERS_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $this->sessions[$row['uuid']] = new GlaciaPlayer(
                    Uuid::fromString($row['uuid']),
                    $row['username'],
                    $row['balance'],
                    $row['cooldowns'],
                    $row['faction_id'],
                    $row['faction_role'],
                    $row['power'],
                    $row['rank'],
                    $row['settings'],
                    $row['buffs'],
                    $row['sell_multiplier'],
                    $row['collection'],
                    $row['kills'],
                    $row['deaths'],
                    (int)($row['slot_credits'] ?? 0),
                    (int)($row['item_flip_credits'] ?? 0),
                    $row['title'] ?? null
                );
            }
        });
    }

    public function createSession(Player $player): GlaciaPlayer {
        $defaultSettings = StorageUtils::encodeJsonString([
            'chest_inventories' => true,
            'broadcasts' => true,
            'notifications' => true,
            'loot_announcer' => true,
            'quick_claim' => true,
            'max_homes' => 3,
            'show_coordinates' => true,
        ], "default player settings") ?? "{}";
        $defaultBuffs = StorageUtils::encodeJsonString([], "default player buffs") ?? "[]";
        $defaultCollection = StorageUtils::encodeJsonString([], "default player collection") ?? "[]";

        $args = [
            "uuid" => $player->getUniqueId()->toString(),
            "username" => $player->getName(),
            "balance" => 950,
            "cooldowns" => "{}",
            "faction_id" => null,
            'faction_role' => null,
            "power" => 100,
            "rank" => "frostborn",
            "settings" => $defaultSettings,
            "buffs" => $defaultBuffs,
            "sell_multiplier" => 1,
            "collection" => $defaultCollection,
            "kills" => 0,
            "deaths" => 0,
            "slot_credits" => 0,
            "item_flip_credits" => 0,
            "title" => null
        ];

        Loader::getDatabase()->executeInsert(QueryStmts::PLAYERS_CREATE, $args);

        $this->sessions[$player->getUniqueId()->toString()] = new GlaciaPlayer(
            $player->getUniqueId(),
            $args["username"],
            $args["balance"],
            $args["cooldowns"],
            $args["faction_id"],
            $args["faction_role"],
            $args["power"],
            $args["rank"],
            $args["settings"],
            $args["buffs"],
            $args["sell_multiplier"],
            $args["collection"],
            $args["kills"],
            $args["deaths"],
            $args["slot_credits"],
            $args["item_flip_credits"],
            $args["title"]
        );

        return $this->sessions[$player->getUniqueId()->toString()];
    }

    public function getSession(Player $player): ?GlaciaPlayer {
        return $this->getSessionByUuid($player->getUniqueId());
    }

    public function getSessionByName(string $name) : ?GlaciaPlayer
    {
        foreach ($this->sessions as $session) {
            if (strtolower($session->getUsername()) === strtolower($name)) {
                return $session;
            }
        }
        return null;
    }

    public function getSessionByPrefix(string $name): ?GlaciaPlayer
    {
        $name = strtolower(trim($name));
        if ($name === "") {
            return null;
        }

        $found = null;
        $delta = PHP_INT_MAX;

        foreach ($this->sessions as $session) {
            $username = strtolower($session->getUsername());
            if ($username === $name) {
                return $session;
            }

            if (str_starts_with($username, $name)) {
                $currentDelta = strlen($username) - strlen($name);
                if ($currentDelta < $delta) {
                    $found = $session;
                    $delta = $currentDelta;
                }
            }
        }

        return $found;
    }

    public function getSessionByUuid(UuidInterface $uuid) : ?GlaciaPlayer
    {
        return $this->sessions[$uuid->toString()] ?? null;
    }

    public function destroySession(GlaciaPlayer $session) : void
    {
        Loader::getDatabase()->executeChange(QueryStmts::PLAYERS_DELETE, ["uuid", $session->getUuid()->toString()]);
        unset($this->sessions[$session->getUuid()->toString()]);
    }

    public function getSessions() : array
    {
        return $this->sessions;
    }

}
