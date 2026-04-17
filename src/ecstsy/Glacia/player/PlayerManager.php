<?php

declare(strict_types=1);

namespace ecstsy\Glacia\player;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class PlayerManager {
    use SingletonTrait;

    /** @var GlaciaPlayer[] */
    private array $sessions;

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
                    $row['deaths']
                );
            }
        });
    }

    public function createSession(Player $player): GlaciaPlayer {
        $args = [
            "uuid" => $player->getUniqueId()->toString(),
            "username" => $player->getName(),
            "balance" => 950,
            "cooldowns" => "{}",
            "faction_id" => null,
            'faction_role' => null,
            "power" => 100,
            "rank" => "frostborn",
            "settings" => json_encode([
                'chest_inventories' => true, 'broadcasts' => true, 'loot_announcer' => true, 'quick_claim' => true
            ]),
            "buffs" => json_encode([]),
            "sell_multiplier" => 1,
            "collection" => "",
            "kills" => 0,
            "deaths" => 0
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
            $args["deaths"]
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