<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\audit\FactionAuditFeed;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\guardian\FactionGuardianManager;
use ecstsy\Glacia\server\scoreboard\ScoreboardHelper;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use SQLite3;
use function array_key_exists;
use function bin2hex;
use function is_file;
use function random_bytes;
use function time;

final class SeasonResetCommand extends BaseCommand
{
    /** @var array<string, array{token:string, expires:int}> */
    private static array $confirmations = [];

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("confirm", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$this->canUse($sender)) {
            $sender->sendMessage(C::colorize("&r&cThis command is restricted to server operators."));
            return;
        }

        $key = strtolower($sender->getName());
        $provided = strtolower(trim((string) ($args["confirm"] ?? "")));
        if (!$this->hasValidToken($key, $provided)) {
            $token = $this->issueToken($key);
            $sender->sendMessage(C::colorize("&r&cThis will reset Glacia seasonal player data for everyone."));
            $sender->sendMessage(C::colorize("&r&7Ranks are preserved. Inventories/world player files are not touched."));
            $sender->sendMessage(C::colorize("&r&7Re-run within 60s: &f/seasonreset {$token}"));
            return;
        }

        unset(self::$confirmations[$key]);
        $this->runSeasonReset($sender);
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }

    private function canUse(CommandSender $sender): bool
    {
        return $sender instanceof ConsoleCommandSender || ($sender instanceof Player && Server::getInstance()->isOp($sender->getName()));
    }

    private function hasValidToken(string $key, string $provided): bool
    {
        if ($provided === "" || !array_key_exists($key, self::$confirmations)) {
            return false;
        }

        $entry = self::$confirmations[$key];
        return $entry["expires"] >= time() && hash_equals($entry["token"], $provided);
    }

    private function issueToken(string $key): string
    {
        $token = bin2hex(random_bytes(3));
        self::$confirmations[$key] = [
            "token" => strtolower($token),
            "expires" => time() + 60,
        ];
        return $token;
    }

    private function runSeasonReset(CommandSender $sender): void
    {
        Loader::getDatabase()->waitAll();

        $databasePath = Loader::getInstance()->getDataFolder() . "sqlite.sql";
        if (!is_file($databasePath)) {
            $sender->sendMessage(C::colorize("&r&cSeason reset failed: Glacia database file was not found."));
            return;
        }

        $db = null;
        try {
            $db = new SQLite3($databasePath);
            $db->exec("PRAGMA foreign_keys = OFF;");
            $db->exec("BEGIN IMMEDIATE TRANSACTION;");

            foreach (self::getSeasonResetStatements() as $statement) {
                if ($db->exec($statement) !== true) {
                    throw new \RuntimeException($db->lastErrorMsg());
                }
            }

            if ($db->exec("COMMIT;") !== true) {
                throw new \RuntimeException($db->lastErrorMsg());
            }
        } catch (\Throwable $e) {
            if ($db instanceof SQLite3) {
                @$db->exec("ROLLBACK;");
                $db->close();
            }
            $sender->sendMessage(C::colorize("&r&cSeason reset failed and was rolled back."));
            $sender->sendMessage(C::colorize("&r&8Reason: &7" . $e->getMessage()));
            return;
        }

        if ($db instanceof SQLite3) {
            $db->close();
        }

        Loader::getPlayerManager()->resetAllPersistentData(true);
        Loader::getHomeManager()->resetSeasonState();
        Loader::getFactionRoleManager()->resetSeasonState();
        Loader::getRelationManager()->resetSeasonState();
        Loader::getFactionUpgradeManager()->resetSeasonState();
        ClaimManager::getInstance()->resetSeasonState();
        Loader::getFactionManager()->resetSeasonState();
        Loader::getCollectionChestManager()->resetSeasonState();
        FactionGuardianManager::resetSeasonState();
        FactionAuditFeed::resetSeasonState();
        Loader::getCoinFlipInstance()->resetSeasonState();
        Loader::getJackpotInstance()->resetSeasonState();
        Loader::getAuctionHouseManager()->resetSeasonState();
        Loader::getPlayerVaultManager()->resetSeasonState();
        Loader::getBountyManager()->resetSeasonState();
        Loader::getQuestBoardManager()->resetSeasonState();
        Loader::getPassiveRunePouchManager()->resetSeasonState();
        AntiCheatManager::getInstance()->resetSeasonState();

        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $online) {
            ScoreboardHelper::refreshScoreboard($online);
        }
        GlaciaUtils::refreshAllOnlineNametags();

        $sender->sendMessage(C::colorize("&r&aSeason reset complete."));
        $sender->sendMessage(C::colorize("&r&7Player/faction/quest/vault/AH progress was reset, while ranks were preserved."));
    }

    /**
     * @return string[]
     */
    private static function getSeasonResetStatements(): array
    {
        return [
            "UPDATE glacia_players
                SET balance = 950,
                    cooldowns = '{}',
                    faction_id = NULL,
                    faction_role = NULL,
                    power = 100,
                    settings = '{\"chest_inventories\":true,\"broadcasts\":true,\"notifications\":true,\"loot_announcer\":true,\"quick_claim\":true,\"max_homes\":5,\"show_coordinates\":true,\"guard_inspect_use_form\":false}',
                    buffs = '[]',
                    sell_multiplier = 1,
                    collection = '[]',
                    kills = 0,
                    deaths = 0,
                    slot_credits = 0,
                    item_flip_credits = 0,
                    title = NULL,
                    playtime_seconds = 0;",
            "DELETE FROM homes;",
            "DELETE FROM glacia_factions;",
            "DELETE FROM glacia_faction_homes;",
            "DELETE FROM glacia_faction_roles;",
            "DELETE FROM glacia_faction_members;",
            "DELETE FROM glacia_faction_claims;",
            "DELETE FROM glacia_faction_relations;",
            "DELETE FROM glacia_faction_upgrade_bank;",
            "DELETE FROM CoinFlips;",
            "UPDATE glacia_jackpot SET pot = 0, last_draw = 0, draw_at = 0, tickets_json = '{}' WHERE id = 1;",
            "INSERT OR IGNORE INTO glacia_jackpot (id, pot, last_draw, draw_at, tickets_json) VALUES (1, 0, 0, 0, '{}');",
            "DELETE FROM glacia_jackpot_stats;",
            "DELETE FROM glacia_bounties;",
            "DELETE FROM glacia_combat_loggers;",
            "DELETE FROM glacia_auction_house_listings;",
            "DELETE FROM glacia_auction_house_collection;",
            "DELETE FROM glacia_player_vaults;",
            "DELETE FROM glacia_player_quests;",
            "DELETE FROM glacia_quest_shop_state;",
        ];
    }
}
