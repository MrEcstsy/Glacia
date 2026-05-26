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
use SQLite3;

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
                $uuid = (string) ($row["uuid"] ?? "");
                if ($uuid === "" || isset($this->sessions[$uuid])) {
                    continue;
                }

                $this->sessions[$uuid] = $this->hydrateSessionRow($row);
            }
        });
    }

    public function ensureSessionLoaded(Player $player): GlaciaPlayer
    {
        $existing = $this->getSession($player);
        if ($existing instanceof GlaciaPlayer) {
            return $existing;
        }

        $loaded = $this->loadSessionFromDatabase($player->getUniqueId()->toString());
        if ($loaded instanceof GlaciaPlayer) {
            $this->sessions[$player->getUniqueId()->toString()] = $loaded;
            return $loaded;
        }

        return $this->createSession($player);
    }

    public function createSession(Player $player): GlaciaPlayer {
        $defaultSettings = StorageUtils::encodeJsonString([
            'chest_inventories' => true,
            'broadcasts' => true,
            'notifications' => true,
            'loot_announcer' => true,
            'quick_claim' => true,
            'max_homes' => 5,
            'show_coordinates' => true,
            'guard_inspect_use_form' => false,
            'night_vision' => false,
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
            "playtime_seconds" => 0,
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
            $args["playtime_seconds"],
            $args["title"]
        );

        return $this->sessions[$player->getUniqueId()->toString()];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateSessionRow(array $row): GlaciaPlayer
    {
        return new GlaciaPlayer(
            Uuid::fromString((string) $row["uuid"]),
            (string) ($row["username"] ?? "Unknown"),
            (float) ($row["balance"] ?? 950),
            (string) ($row["cooldowns"] ?? "{}"),
            isset($row["faction_id"]) ? (string) $row["faction_id"] : null,
            isset($row["faction_role"]) ? (string) $row["faction_role"] : null,
            (int) ($row["power"] ?? 100),
            (string) ($row["rank"] ?? "frostborn"),
            (string) ($row["settings"] ?? "{}"),
            (string) ($row["buffs"] ?? "[]"),
            (float) ($row["sell_multiplier"] ?? 1.0),
            (string) ($row["collection"] ?? "[]"),
            (int) ($row["kills"] ?? 0),
            (int) ($row["deaths"] ?? 0),
            (int) ($row["slot_credits"] ?? 0),
            (int) ($row["item_flip_credits"] ?? 0),
            (int) ($row["playtime_seconds"] ?? 0),
            isset($row["title"]) ? (string) $row["title"] : null
        );
    }

    private function loadSessionFromDatabase(string $uuid): ?GlaciaPlayer
    {
        $databasePath = Loader::getInstance()->getDataFolder() . "sqlite.sql";
        if (!is_file($databasePath)) {
            return null;
        }

        try {
            $db = new SQLite3($databasePath, SQLITE3_OPEN_READONLY);
            $stmt = $db->prepare("SELECT * FROM glacia_players WHERE uuid = :uuid LIMIT 1;");
            if ($stmt === false) {
                $db->close();
                return null;
            }

            $stmt->bindValue(":uuid", $uuid, SQLITE3_TEXT);
            $result = $stmt->execute();
            if ($result === false) {
                $db->close();
                return null;
            }

            $row = $result->fetchArray(SQLITE3_ASSOC);
            $result->finalize();
            $db->close();
            if (!is_array($row)) {
                return null;
            }

            return $this->hydrateSessionRow($row);
        } catch (\Throwable) {
            return null;
        }
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

    public function resetAllPersistentData(bool $preserveRanks = true): void
    {
        foreach ($this->sessions as $session) {
            $session->resetPersistentData($preserveRanks, false);
        }
    }

}
