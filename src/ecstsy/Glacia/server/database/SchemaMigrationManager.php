<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\database;

use ecstsy\Glacia\Loader;
use SQLite3;
use Throwable;

final class SchemaMigrationManager
{
    /**
     * Keep this list additive.
     * When you add a new column to an existing table, add it here too.
     *
     * @var array<string, array<string, string>>
     */
    private const COLUMN_MIGRATIONS = [
        "glacia_players" => [
            "username" => "VARCHAR(16)",
            "balance" => "REAL DEFAULT 950",
            "cooldowns" => "TEXT",
            "faction_id" => "VARCHAR(32) DEFAULT NULL",
            "faction_role" => "VARCHAR(16) DEFAULT NULL",
            "power" => "INT DEFAULT 100",
            "rank" => "VARCHAR(32) NOT NULL DEFAULT 'frostborn'",
            "settings" => "TEXT",
            "buffs" => "TEXT",
            "sell_multiplier" => "REAL DEFAULT 1",
            "collection" => "TEXT",
            "kills" => "INT DEFAULT 0",
            "deaths" => "INT DEFAULT 0",
            "slot_credits" => "INT DEFAULT 0",
            "item_flip_credits" => "INT DEFAULT 0",
            "title" => "TEXT DEFAULT NULL",
            "playtime_seconds" => "INT NOT NULL DEFAULT 0",
        ],
        "glacia_factions" => [
            "max_members" => "INT DEFAULT 5",
            "base_power_cap" => "INT DEFAULT 2500",
        ],
        "glacia_faction_upgrade_bank" => [
            "bank_balance" => "REAL NOT NULL DEFAULT 0",
            "upgrade_data" => "TEXT NOT NULL DEFAULT '{}'",
        ],
        "glacia_faction_roles" => [
            "view_audit_log" => "INTEGER DEFAULT 0",
        ],
        "glacia_combat_loggers" => [
            "dead_on_login" => "INT DEFAULT 0",
            "offhand" => "TEXT DEFAULT ''",
        ],
        "glacia_ranks" => [
            "parent_rank" => "VARCHAR(32) DEFAULT NULL",
            "chat_format" => "TEXT NOT NULL DEFAULT ''",
            "nametag_format" => "TEXT NOT NULL DEFAULT ''",
        ],
        "glacia_jackpot" => [
            "draw_at" => "INT NOT NULL DEFAULT 0",
            "tickets_json" => "TEXT NOT NULL DEFAULT '{}'",
        ],
    ];

    public static function migrate(Loader $plugin): void
    {
        if(!class_exists(SQLite3::class)){
            $plugin->getLogger()->warning("SQLite3 extension is unavailable, skipping schema migrations.");
            return;
        }

        $databasePath = $plugin->getDataFolder() . "sqlite.sql";
        if(!is_file($databasePath)){
            return;
        }

        try{
            $db = new SQLite3($databasePath);

            foreach(self::COLUMN_MIGRATIONS as $table => $columns){
                if(!self::tableExists($db, $table)){
                    continue;
                }

                $existingColumns = self::getColumns($db, $table);
                foreach($columns as $column => $definition){
                    if(isset($existingColumns[$column])){
                        continue;
                    }

                    $escapedTable = self::escapeIdentifier($table);
                    $escapedColumn = self::escapeIdentifier($column);
                    $ok = $db->exec("ALTER TABLE {$escapedTable} ADD COLUMN {$escapedColumn} {$definition};");
                    if($ok === false){
                        $plugin->getLogger()->warning("Failed to migrate table '{$table}': could not add column '{$column}'.");
                        continue;
                    }

                    $plugin->getLogger()->info("Migrated table '{$table}': added missing column '{$column}'.");
                }
            }

            $db->close();
        }catch(Throwable $e){
            $plugin->getLogger()->error("Failed to run schema migrations: " . $e->getMessage());
        }
    }

    private static function tableExists(SQLite3 $db, string $table): bool
    {
        $result = $db->querySingle(
            "SELECT name FROM sqlite_master WHERE type='table' AND name = '" . SQLite3::escapeString($table) . "';",
            true
        );

        return is_array($result) && isset($result["name"]);
    }

    /**
     * @return array<string, true>
     */
    private static function getColumns(SQLite3 $db, string $table): array
    {
        $columns = [];
        $result = $db->query("PRAGMA table_info(" . self::escapeIdentifier($table) . ");");
        if($result === false){
            return $columns;
        }

        while(($row = $result->fetchArray(SQLITE3_ASSOC)) !== false){
            if(isset($row["name"])){
                $columns[(string) $row["name"]] = true;
            }
        }

        return $columns;
    }

    private static function escapeIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
