-- #!sqlite
-- # { glacia_players
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_players (
    uuid VARCHAR(36) PRIMARY KEY,
    username VARCHAR(16),
    balance REAL DEFAULT 950,
    cooldowns TEXT,
    faction_id VARCHAR(32) DEFAULT NULL,
    faction_role VARCHAR(16) DEFAULT NULL,
    power int DEFAULT 100,
    rank VARCHAR(32) NOT NULL DEFAULT 'frostborn',
    settings TEXT,
    buffs TEXT,
    sell_multiplier REAL DEFAULT 1,
    collection TEXT,
    kills INT default 0,
    deaths INT default 0,
    slot_credits INT default 0,
    item_flip_credits INT default 0,
    title TEXT DEFAULT NULL,
    playtime_seconds INT NOT NULL DEFAULT 0
);
-- # }

-- # { select
SELECT * 
FROM glacia_players;
-- # }

-- # { baltop
-- #    :limit int
-- #    :offset int
SELECT username, balance
FROM glacia_players
WHERE username IS NOT NULL AND username != ''
ORDER BY balance DESC, username ASC
LIMIT :limit OFFSET :offset;
-- # }

-- # { baltop_count
SELECT COUNT(*) AS count
FROM glacia_players
WHERE username IS NOT NULL AND username != '';
-- # }

-- # { create
-- #    :uuid string
-- #    :username string
-- #    :balance float
-- #    :cooldowns string
-- #    :faction_id ?string
-- #    :faction_role ?string
-- #    :power int
-- #    :rank string
-- #    :settings string
-- #    :buffs string
-- #    :sell_multiplier float
-- #    :collection string
-- #    :kills int
-- #    :deaths int
-- #    :slot_credits int
-- #    :item_flip_credits int
-- #    :title ?string
-- #    :playtime_seconds int
INSERT OR IGNORE INTO glacia_players (uuid, username, balance, cooldowns, faction_id, faction_role, power, rank, settings, buffs, sell_multiplier, collection, kills, deaths, slot_credits, item_flip_credits, title, playtime_seconds)
VALUES (:uuid, :username, :balance, :cooldowns, :faction_id, :faction_role, :power, :rank, :settings, :buffs, :sell_multiplier, :collection, :kills, :deaths, :slot_credits, :item_flip_credits, :title, :playtime_seconds);
-- # }

-- # { update
-- #   :uuid string
-- #   :username string
-- #   :balance float
-- #   :cooldowns string
-- #   :faction_id ?string
-- #   :faction_role ?string
-- #   :power int
-- #   :rank string
-- #   :settings string
-- #   :buffs string
-- #   :sell_multiplier float
-- #   :collection string
-- #   :kills int
-- #   :deaths int
-- #   :slot_credits int
-- #   :item_flip_credits int
-- #   :title ?string
-- #   :playtime_seconds int
UPDATE glacia_players
SET username = :username, 
    balance = :balance, 
    cooldowns = :cooldowns,
    faction_id = :faction_id, 
    faction_role = :faction_role, 
    power = :power,
    rank = :rank,
    settings = :settings,
    buffs = :buffs,
    sell_multiplier = :sell_multiplier,
    collection = :collection,
    kills = :kills,
    deaths = :deaths,
    slot_credits = :slot_credits,
    item_flip_credits = :item_flip_credits,
    title = :title,
    playtime_seconds = :playtime_seconds
WHERE uuid = :uuid;
-- # }

-- # { delete
-- #    :uuid string
DELETE FROM glacia_players 
WHERE uuid = :uuid;
-- #  }

-- # { username_by_uuid
-- #    :uuid string
SELECT username FROM glacia_players WHERE uuid = :uuid LIMIT 1;
-- # }

-- # { reset_season
UPDATE glacia_players
SET balance = 950,
    cooldowns = '{}',
    faction_id = NULL,
    faction_role = NULL,
    power = 100,
    settings = '{"chest_inventories":true,"broadcasts":true,"notifications":true,"loot_announcer":true,"quick_claim":true,"max_homes":5,"show_coordinates":true,"guard_inspect_use_form":false}',
    buffs = '[]',
    sell_multiplier = 1,
    collection = '[]',
    kills = 0,
    deaths = 0,
    slot_credits = 0,
    item_flip_credits = 0,
    title = NULL,
    playtime_seconds = 0;
-- # }
-- # }

-- # { glacia_auction_house_listings
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_auction_house_listings (
    listing_id VARCHAR(36) PRIMARY KEY,
    seller_uuid VARCHAR(36) NOT NULL,
    seller_name VARCHAR(16) NOT NULL,
    item_data TEXT NOT NULL,
    price INT NOT NULL,
    created_at INT NOT NULL,
    expires_at INT NOT NULL
);
-- # }

-- # { select
SELECT * FROM glacia_auction_house_listings;
-- # }

-- # { create
-- #    :listing_id string
-- #    :seller_uuid string
-- #    :seller_name string
-- #    :item_data string
-- #    :price int
-- #    :created_at int
-- #    :expires_at int
INSERT OR REPLACE INTO glacia_auction_house_listings (listing_id, seller_uuid, seller_name, item_data, price, created_at, expires_at)
VALUES (:listing_id, :seller_uuid, :seller_name, :item_data, :price, :created_at, :expires_at);
-- # }

-- # { delete
-- #    :listing_id string
DELETE FROM glacia_auction_house_listings WHERE listing_id = :listing_id;
-- # }

-- # { delete_all
DELETE FROM glacia_auction_house_listings;
-- # }
-- # }

-- # { glacia_auction_house_collection
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_auction_house_collection (
    entry_id VARCHAR(36) PRIMARY KEY,
    owner_uuid VARCHAR(36) NOT NULL,
    item_data TEXT NOT NULL,
    reason VARCHAR(32) NOT NULL,
    created_at INT NOT NULL
);
-- # }

-- # { select
SELECT * FROM glacia_auction_house_collection;
-- # }

-- # { create
-- #    :entry_id string
-- #    :owner_uuid string
-- #    :item_data string
-- #    :reason string
-- #    :created_at int
INSERT OR REPLACE INTO glacia_auction_house_collection (entry_id, owner_uuid, item_data, reason, created_at)
VALUES (:entry_id, :owner_uuid, :item_data, :reason, :created_at);
-- # }

-- # { delete
-- #    :entry_id string
DELETE FROM glacia_auction_house_collection WHERE entry_id = :entry_id;
-- # }

-- # { delete_all
DELETE FROM glacia_auction_house_collection;
-- # }
-- # }

-- # { glacia_player_vaults
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_player_vaults (
    owner_uuid VARCHAR(36) NOT NULL,
    vault_number INT NOT NULL,
    contents TEXT NOT NULL,
    updated_at INT NOT NULL,
    PRIMARY KEY (owner_uuid, vault_number)
);
-- # }

-- # { select
SELECT * FROM glacia_player_vaults;
-- # }

-- # { create
-- #    :owner_uuid string
-- #    :vault_number int
-- #    :contents string
-- #    :updated_at int
INSERT OR REPLACE INTO glacia_player_vaults (owner_uuid, vault_number, contents, updated_at)
VALUES (:owner_uuid, :vault_number, :contents, :updated_at);
-- # }

-- # { delete
-- #    :owner_uuid string
-- #    :vault_number int
DELETE FROM glacia_player_vaults
WHERE owner_uuid = :owner_uuid AND vault_number = :vault_number;
-- # }

-- # { delete_all
DELETE FROM glacia_player_vaults;
-- # }
-- # }

-- # { glacia_player_quests
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_player_quests (
    uuid VARCHAR(36) PRIMARY KEY,
    username VARCHAR(16) NOT NULL,
    quest_points INT NOT NULL DEFAULT 0,
    assigned_at INT NOT NULL DEFAULT 0,
    daily_quests TEXT NOT NULL DEFAULT '{}',
    bonus_quests TEXT NOT NULL DEFAULT '[]',
    meta TEXT NOT NULL DEFAULT '{}'
);
-- # }

-- # { select
SELECT * FROM glacia_player_quests;
-- # }

-- # { create
-- #    :uuid string
-- #    :username string
-- #    :quest_points int
-- #    :assigned_at int
-- #    :daily_quests string
-- #    :bonus_quests string
-- #    :meta string
INSERT OR IGNORE INTO glacia_player_quests (uuid, username, quest_points, assigned_at, daily_quests, bonus_quests, meta)
VALUES (:uuid, :username, :quest_points, :assigned_at, :daily_quests, :bonus_quests, :meta);
-- # }

-- # { update
-- #    :uuid string
-- #    :username string
-- #    :quest_points int
-- #    :assigned_at int
-- #    :daily_quests string
-- #    :bonus_quests string
-- #    :meta string
UPDATE glacia_player_quests
SET username = :username,
    quest_points = :quest_points,
    assigned_at = :assigned_at,
    daily_quests = :daily_quests,
    bonus_quests = :bonus_quests,
    meta = :meta
WHERE uuid = :uuid;
-- # }

-- # { delete
-- #    :uuid string
DELETE FROM glacia_player_quests WHERE uuid = :uuid;
-- # }

-- # { delete_all
DELETE FROM glacia_player_quests;
-- # }
-- # }

-- # { glacia_quest_shop_state
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_quest_shop_state (
    state_key VARCHAR(32) PRIMARY KEY,
    value TEXT NOT NULL
);
-- # }

-- # { select
SELECT * FROM glacia_quest_shop_state;
-- # }

-- # { upsert
-- #    :state_key string
-- #    :value string
INSERT OR REPLACE INTO glacia_quest_shop_state (state_key, value)
VALUES (:state_key, :value);
-- # }

-- # { delete_all
DELETE FROM glacia_quest_shop_state;
-- # }
-- # }

-- # { glacia_factions
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_factions (
    faction_id VARCHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(16),
    description TEXT,
    leader_uuid VARCHAR(36) NOT NULL,
    leader_name VARCHAR(16) NOT NULL,
    max_members INT DEFAULT 5,
    base_power_cap INT DEFAULT 2500
);
-- # }

-- # { select
SELECT * FROM glacia_factions;
-- # }

-- # { create
-- #    :faction_id string
-- #    :name string
-- #    :description string
-- #    :leader_uuid string
-- #    :leader_name string
-- #    :max_members int
-- #    :base_power_cap int
INSERT OR REPLACE INTO glacia_factions (faction_id, name, description, leader_uuid, leader_name, max_members, base_power_cap)
VALUES (:faction_id, :name, :description, :leader_uuid, :leader_name, :max_members, :base_power_cap);
-- # }

-- # { update
-- #   :faction_id string
-- #   :name string
-- #   :description string
-- #   :leader_uuid string
-- #   :leader_name string
-- #   :max_members int
-- #   :base_power_cap int
UPDATE glacia_factions
SET name = :name, description = :description, leader_uuid = :leader_uuid, leader_name = :leader_name, max_members = :max_members, base_power_cap = :base_power_cap
WHERE faction_id = :faction_id;
-- # }

-- # { delete
-- #    :faction_id string
DELETE FROM glacia_factions WHERE faction_id = :faction_id;
-- #  }

-- # { delete_all
DELETE FROM glacia_factions;
-- #  }
-- # }

-- # { glacia_faction_homes
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_faction_homes (
    faction_id   VARCHAR(36) PRIMARY KEY,
    world        VARCHAR(64)  NOT NULL,
    x            INTEGER      NOT NULL,
    y            INTEGER      NOT NULL,
    z            INTEGER      NOT NULL
);
-- # }

-- # { select
SELECT * FROM glacia_faction_homes;
-- # }

-- # { create
-- #    :faction_id string
-- #    :world string
-- #    :x int
-- #    :y int
-- #    :z int
INSERT OR REPLACE INTO glacia_faction_homes (faction_id, world, x, y, z)
VALUES (:faction_id, :world, :x, :y, :z);
-- # }

-- # { update
-- #    :faction_id string
-- #    :world string
-- #    :x int
-- #    :y int
-- #    :z int
UPDATE glacia_faction_homes
SET world = :world, x = :x, y = :y, z = :z
WHERE faction_id = :faction_id;
-- # }

-- # { delete
-- #    :faction_id string
DELETE FROM glacia_faction_homes WHERE faction_id = :faction_id;
-- #  }

-- # { delete_all
DELETE FROM glacia_faction_homes;
-- #  }
-- # }

-- # { glacia_faction_roles
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_faction_roles (
    faction_id   VARCHAR(36) NOT NULL,
    role_key     VARCHAR(32) NOT NULL,
    kill_mobs    INTEGER DEFAULT 0,
    open_containers   INTEGER DEFAULT 0,
    interact_doors     INTEGER DEFAULT 0,
    pickup  INTEGER DEFAULT 0,
    break  INTEGER DEFAULT 0,
    place  INTEGER DEFAULT 0,
    use_buckets  INTEGER DEFAULT 0,
    change_permissions  INTEGER DEFAULT 0,
    demote_users  INTEGER DEFAULT 0,
    promote_users  INTEGER DEFAULT 0,
    kick_users  INTEGER DEFAULT 0,
    use_redstone  INTEGER DEFAULT 0,
    rename_faction  INTEGER DEFAULT 0,
    faction_home  INTEGER DEFAULT 0,
    break_spawners  INTEGER DEFAULT 0,
    change_settings  INTEGER DEFAULT 0,
    manage_warps  INTEGER DEFAULT 0,
    change_description  INTEGER DEFAULT 0,
    invite_players  INTEGER DEFAULT 0,
    view_audit_log  INTEGER DEFAULT 0,
    PRIMARY KEY (faction_id, role_key)
);
-- # }

-- # { select
SELECT * FROM glacia_faction_roles;
-- # }

-- # { create
-- #    :faction_id string
-- #    :role_key string
-- #    :kill_mobs int
-- #    :open_containers int
-- #    :interact_doors int
-- #    :pickup int
-- #    :break int
-- #    :place int
-- #    :use_buckets int
-- #    :change_permissions int
-- #    :demote_users int
-- #    :promote_users int
-- #    :kick_users int
-- #    :use_redstone int
-- #    :rename_faction int
-- #    :faction_home int
-- #    :break_spawners int
-- #    :change_settings int
-- #    :manage_warps int
-- #    :change_description int
-- #    :invite_players int
-- #    :view_audit_log int
INSERT OR REPLACE INTO glacia_faction_roles (faction_id, role_key, kill_mobs, open_containers, interact_doors, pickup, break, place, use_buckets, change_permissions, demote_users, promote_users, kick_users, use_redstone, rename_faction, faction_home, break_spawners, change_settings, manage_warps, change_description, invite_players, view_audit_log)
VALUES (:faction_id, :role_key, :kill_mobs, :open_containers, :interact_doors, :pickup, :break, :place, :use_buckets, :change_permissions, :demote_users, :promote_users, :kick_users, :use_redstone, :rename_faction, :faction_home, :break_spawners, :change_settings, :manage_warps, :change_description, :invite_players, :view_audit_log);
-- # }

-- # { update
-- #    :faction_id string
-- #    :role_key string
-- #    :kill_mobs int
-- #    :open_containers int
-- #    :interact_doors int
-- #    :pickup int
-- #    :break int
-- #    :place int
-- #    :use_buckets int
-- #    :change_permissions int
-- #    :demote_users int
-- #    :promote_users int
-- #    :kick_users int
-- #    :use_redstone int
-- #    :rename_faction int
-- #    :faction_home int
-- #    :break_spawners int
-- #    :change_settings int
-- #    :manage_warps int
-- #    :change_description int
-- #    :invite_players int
-- #    :view_audit_log int
UPDATE glacia_faction_roles
SET kill_mobs = :kill_mobs,
    open_containers = :open_containers,
    interact_doors = :interact_doors,
    pickup = :pickup,
    break = :break,
    place = :place,
    use_buckets = :use_buckets,
    change_permissions = :change_permissions,
    demote_users = :demote_users,
    promote_users = :promote_users,
    kick_users = :kick_users,
    use_redstone = :use_redstone,
    rename_faction = :rename_faction,
    faction_home = :faction_home,
    break_spawners = :break_spawners,
    change_settings = :change_settings,
    manage_warps = :manage_warps,
    change_description = :change_description,
    invite_players = :invite_players,
    view_audit_log = :view_audit_log
WHERE faction_id = :faction_id AND role_key = :role_key;
-- # }

-- # { delete
-- #    :faction_id string
DELETE FROM glacia_faction_roles
WHERE faction_id = :faction_id;
-- #  }

-- # { delete_all
DELETE FROM glacia_faction_roles;
-- #  }
-- # }

-- # { glacia_faction_members
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_faction_members (
    faction_id   VARCHAR(36) NOT NULL,
    member_uuid  VARCHAR(36) NOT NULL,
    role_key     VARCHAR(32) NOT NULL,
    joined_at    INTEGER      NOT NULL,
    PRIMARY KEY (faction_id, member_uuid)
);
-- # }

-- # { select
SELECT * FROM glacia_faction_members;
-- # }

-- # { select_by_faction
-- #    :faction_id string
SELECT * FROM glacia_faction_members
WHERE faction_id = :faction_id;
-- # }

-- # { create
-- #    :faction_id string
-- #    :member_uuid string
-- #    :role_key string
-- #    :joined_at int
INSERT OR REPLACE INTO glacia_faction_members (faction_id, member_uuid, role_key, joined_at)
VALUES (:faction_id, :member_uuid, :role_key, :joined_at);
-- # }

-- # { update
-- #    :faction_id string
-- #    :member_uuid string
-- #    :role_key string
-- #    :joined_at int
UPDATE glacia_faction_members
SET role_key = :role_key,
    joined_at = :joined_at
WHERE faction_id = :faction_id AND member_uuid = :member_uuid;
-- # }

-- # { delete
-- #    :faction_id string
-- #    :member_uuid string
DELETE FROM glacia_faction_members
WHERE faction_id = :faction_id AND member_uuid = :member_uuid;
-- #  }

-- # { delete_all
DELETE FROM glacia_faction_members;
-- #  }
-- # }

-- # { glacia_faction_claims
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_faction_claims (
    faction_id VARCHAR(36) NOT NULL,
    world_name VARCHAR(64)  NOT NULL,
    chunkX     INTEGER      NOT NULL,
    chunkZ     INTEGER      NOT NULL,
    PRIMARY KEY (world_name, chunkX, chunkZ)
);
-- # }

-- # { select
SELECT * FROM glacia_faction_claims;
-- # }

-- # { create
-- #    :faction_id string
-- #    :world_name string
-- #    :chunkX int
-- #    :chunkZ int
INSERT OR REPLACE INTO glacia_faction_claims (
    faction_id, world_name,
    chunkX,     chunkZ
) VALUES (
    :faction_id, :world_name,
    :chunkX,     :chunkZ
);
-- # }

-- # { update
-- #    :faction_id string
-- #    :world_name string
-- #    :chunkX int
-- #    :chunkZ int
UPDATE glacia_faction_claims
SET faction_id = :faction_id
WHERE world_name = :world_name
  AND chunkX     = :chunkX
  AND chunkZ     = :chunkZ;
-- # }

-- # { delete
-- #    :world_name string
-- #    :chunkX int
-- #    :chunkZ int
DELETE FROM glacia_faction_claims
WHERE world_name = :world_name
  AND chunkX     = :chunkX
  AND chunkZ     = :chunkZ;
-- # }

-- # { deleteByFaction
-- #    :faction_id string
DELETE FROM glacia_faction_claims
WHERE faction_id = :faction_id;
-- #  }

-- # { delete_all
DELETE FROM glacia_faction_claims;
-- #  }
-- # }

-- # { glacia_faction_relations
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_faction_relations (
    faction_id VARCHAR(36) NOT NULL,
    other_id VARCHAR(36) NOT NULL,
    relation VARCHAR(16) NOT NULL,
    PRIMARY KEY (faction_id, other_id)
);
-- # }

-- # { select
SELECT * FROM glacia_faction_relations;
-- # }

-- # { create
-- #    :faction_id string
-- #    :other_id string
-- #    :relation string
INSERT OR REPLACE INTO glacia_faction_relations (faction_id, other_id, relation)
VALUES (:faction_id, :other_id, :relation);
-- # }

-- # { update
-- #    :faction_id string
-- #    :other_id string
-- #    :relation string
UPDATE glacia_faction_relations
SET relation = :relation
WHERE faction_id = :faction_id AND other_id = :other_id;
-- # }

-- # { delete
-- #    :faction_id string
-- #    :other_id string
DELETE FROM glacia_faction_relations
WHERE faction_id = :faction_id AND other_id = :other_id;
-- #  }

-- # { delete_all
DELETE FROM glacia_faction_relations;
-- #  }
-- # }

-- # { glacia_faction_upgrade_bank
-- #    { init
CREATE TABLE IF NOT EXISTS glacia_faction_upgrade_bank (
    faction_id VARCHAR(36) PRIMARY KEY,
    bank_balance REAL NOT NULL DEFAULT 0,
    upgrade_data TEXT NOT NULL DEFAULT '{}'
);
-- # }

-- # { select
SELECT * FROM glacia_faction_upgrade_bank;
-- # }

-- # { create
-- #    :faction_id string
-- #    :bank_balance float
-- #    :upgrade_data string
INSERT OR REPLACE INTO glacia_faction_upgrade_bank (faction_id, bank_balance, upgrade_data)
VALUES (:faction_id, :bank_balance, :upgrade_data);
-- # }

-- # { delete
-- #    :faction_id string
DELETE FROM glacia_faction_upgrade_bank WHERE faction_id = :faction_id;
-- #  }

-- # { delete_all
DELETE FROM glacia_faction_upgrade_bank;
-- #  }
-- # }

-- # { glacia_ranks
-- #  { init
CREATE TABLE IF NOT EXISTS glacia_ranks (
    rank_key VARCHAR(32) PRIMARY KEY,
    display_name VARCHAR(32) NOT NULL,
    parent_rank VARCHAR(32) DEFAULT NULL,
    chat_format TEXT NOT NULL,
    nametag_format TEXT NOT NULL
);
-- # }

-- # { select
SELECT * FROM glacia_ranks;
-- # }

-- # { create
-- #    :rank_key string
-- #    :display_name string
-- #    :parent_rank ?string
-- #    :chat_format string
-- #    :nametag_format string
INSERT OR REPLACE INTO glacia_ranks (rank_key, display_name, parent_rank, chat_format, nametag_format)
VALUES (:rank_key, :display_name, :parent_rank, :chat_format, :nametag_format);
-- # }

-- # { update
-- #    :rank_key string
-- #    :display_name string
-- #    :parent_rank ?string
-- #    :chat_format string
-- #    :nametag_format string
UPDATE glacia_ranks
SET display_name = :display_name, parent_rank = :parent_rank, chat_format = :chat_format, nametag_format = :nametag_format
WHERE rank_key = :rank_key;
-- # }

-- # { delete
-- #    :rank_key string
DELETE FROM glacia_ranks WHERE rank_key = :rank_key;
-- #  }
-- # }

-- # { glacia_rank_permissions
-- #  { init
CREATE TABLE IF NOT EXISTS glacia_rank_permissions (
    rank_key VARCHAR(32) NOT NULL,
    permission VARCHAR(32) NOT NULL,
    PRIMARY KEY (rank_key, permission),
    FOREIGN KEY (rank_key) REFERENCES glacia_ranks (rank_key) on DELETE CASCADE
);
-- # }

-- # { select
SELECT * FROM glacia_rank_permissions;
-- # }

-- # { create
-- #    :rank_key string
-- #    :permission string
INSERT OR REPLACE INTO glacia_rank_permissions (rank_key, permission)
VALUES (:rank_key, :permission);
-- # }

-- # { update
-- #    :rank_key string
-- #    :permission string
UPDATE glacia_rank_permissions
SET permission = :permission
WHERE rank_key = :rank_key;
-- # }

-- # { delete
-- #    :rank_key string
-- #    :permission string
DELETE FROM glacia_rank_permissions WHERE rank_key = :rank_key AND permission = :permission;
-- #  }

-- # { delete_all_for_rank
-- #    :rank_key string
DELETE FROM glacia_rank_permissions WHERE rank_key = :rank_key;
-- #  }
-- # }

-- # { warps
-- #    { init
CREATE TABLE IF NOT EXISTS warps (
    warp_name VARCHAR(32) PRIMARY KEY NOT NULL,
    world VARCHAR(32) NOT NULL,
    x INTEGER NOT NULL,
    y INTEGER NOT NULL,
    z INTEGER NOT NULL
    );
-- # }

-- #  { select
SELECT *
FROM warps;
-- #  }
-- #  { create
-- #      :warp_name string
-- #      :world string
-- #      :x int
-- #      :y int
-- #      :z int
INSERT OR REPLACE INTO warps(warp_name, world, x, y, z)
VALUES (:warp_name, :world, :x, :y, :z);
-- #  }
-- #  { delete
-- #      :warp_name int
DELETE FROM warps
WHERE warp_name=:warp_name;
-- #  }
-- # }

-- # { homes
-- #  { init
CREATE TABLE IF NOT EXISTS homes (
    uuid VARCHAR(36),
    home_name VARCHAR(32),
    world_name VARCHAR(32),
    x INT,
    y INT,
    z INT,
    max_homes INT DEFAULT 3,
    PRIMARY KEY (uuid, home_name)
    );
-- #  }
-- # { select
SELECT *
FROM homes;
-- # }

-- #  { create
-- #      :uuid string
-- #      :home_name string
-- #      :world_name string
-- #      :x int
-- #      :y int
-- #      :z int
-- #      :max_homes int
INSERT OR REPLACE INTO homes(uuid, home_name, world_name, x, y, z, max_homes)
VALUES (:uuid, :home_name, :world_name, :x, :y, :z, :max_homes);
-- #  }

-- #  { delete
-- #      :uuid string
-- #      :home_name string
DELETE FROM homes
WHERE uuid = :uuid AND home_name = :home_name;
-- #  }

-- #  { delete_all
DELETE FROM homes;
-- #  }

-- #  { update
-- #      :uuid string
-- #      :max_homes int
UPDATE homes
SET max_homes = :max_homes
WHERE uuid = :uuid;
-- #   }
-- # }

-- # { glacia_server
-- # { init
CREATE TABLE IF NOT EXISTS glacia_server (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
-- # }

-- # { select
SELECT * FROM glacia_server;
-- # }

-- # { update
-- #    :key string
-- #    :value string
UPDATE glacia_server
SET value = :value
WHERE key = :key;
-- # }

-- # { create
-- #    :key string
-- #    :value string
INSERT OR REPLACE INTO glacia_server (key, value)
VALUES (:key, :value);
-- # }

-- # { delete
-- #    :key string
DELETE FROM glacia_server WHERE key = :key;
-- #  }
-- # }

-- # { coinflip
-- #  { initialize
CREATE TABLE IF NOT EXISTS CoinFlips (
    uuid VARCHAR(36) NOT NULL,
    username VARCHAR(16) NOT NULL,
    type TEXT NOT NULL,
    money INT NOT NULL,
    PRIMARY KEY (uuid, type)
);
-- #  }
-- #  { select
SELECT *
FROM CoinFlips;
-- #  }

-- #  { create
-- #      :uuid string
-- #      :username string
-- #      :type string
-- #      :money int
INSERT OR REPLACE INTO CoinFlips (uuid, username, type, money)
VALUES (:uuid, :username, :type, :money);
-- #  }
-- #  { delete
-- #      :uuid string
DELETE FROM CoinFlips
WHERE uuid=:uuid;
-- #  }

-- #  { delete_all
DELETE FROM CoinFlips;
-- #  }
-- # }

-- # { glacia_jackpot
-- #   { init
CREATE TABLE IF NOT EXISTS glacia_jackpot (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    pot INTEGER NOT NULL DEFAULT 0,
    last_draw INTEGER NOT NULL DEFAULT 0,
    draw_at INTEGER NOT NULL DEFAULT 0,
    tickets_json TEXT NOT NULL DEFAULT '{}'
);
-- #   }

-- #   { select
SELECT * FROM glacia_jackpot WHERE id = 1;
-- #   }

-- #   { update
-- #      :pot int
-- #      :last_draw int
-- #      :draw_at int
-- #      :tickets_json string
UPDATE glacia_jackpot
SET pot = :pot,
    last_draw = :last_draw,
    draw_at = :draw_at,
    tickets_json = :tickets_json
WHERE id = 1;
-- #   }

-- #   { insert
-- #      :pot int
-- #      :last_draw int
-- #      :draw_at int
-- #      :tickets_json string
INSERT OR IGNORE INTO glacia_jackpot (id, pot, last_draw, draw_at, tickets_json)
VALUES (1, :pot, :last_draw, :draw_at, :tickets_json);
-- #   }

-- #   { reset
UPDATE glacia_jackpot
SET pot = 0,
    last_draw = 0,
    draw_at = 0,
    tickets_json = '{}'
WHERE id = 1;
-- #   }
-- # }

-- # { glacia_jackpot_stats
-- #   { init
CREATE TABLE IF NOT EXISTS glacia_jackpot_stats (
    uuid VARCHAR(36) PRIMARY KEY,
    username VARCHAR(16),
    wins INT DEFAULT 0,
    winnings INT DEFAULT 0,
    FOREIGN KEY (uuid) REFERENCES glacia_players(uuid)
);
-- #   }

-- #   { select
SELECT * FROM glacia_jackpot_stats WHERE uuid = :uuid;
-- #   }

-- #   { update
-- #      :uuid string
-- #      :wins int
-- #      :winnings int
UPDATE glacia_jackpot_stats SET wins = :wins, winnings = :winnings WHERE uuid = :uuid;
-- #   }

-- #   { insert
-- #      :uuid string
-- #      :username string
-- #      :wins int
-- #      :winnings int
INSERT OR IGNORE INTO glacia_jackpot_stats (uuid, username, wins, winnings) VALUES (:uuid, :username, :wins, :winnings);
-- #   }

-- #   { delete_all
DELETE FROM glacia_jackpot_stats;
-- #   }
-- # }

-- # { glacia_bounties
-- #   { init
CREATE TABLE IF NOT EXISTS glacia_bounties (
    target_uuid VARCHAR(36) PRIMARY KEY,
    target_name VARCHAR(16) NOT NULL,
    amount INT NOT NULL DEFAULT 0,
    updated_at INT NOT NULL DEFAULT 0
);
-- #   }
-- #   { select
SELECT * FROM glacia_bounties;
-- #   }
-- #   { create
-- #      :target_uuid string
-- #      :target_name string
-- #      :amount int
-- #      :updated_at int
INSERT OR REPLACE INTO glacia_bounties (target_uuid, target_name, amount, updated_at)
VALUES (:target_uuid, :target_name, :amount, :updated_at);
-- #   }
-- #   { delete
-- #      :target_uuid string
DELETE FROM glacia_bounties WHERE target_uuid = :target_uuid;
-- #   }

-- #   { delete_all
DELETE FROM glacia_bounties;
-- #   }
-- # }

-- # { glacia_combat_loggers
-- #   { init
CREATE TABLE IF NOT EXISTS glacia_combat_loggers (
    uuid VARCHAR(36) PRIMARY KEY,
    username VARCHAR(16),
    world VARCHAR(64),
    x REAL,
    y REAL,
    z REAL,
    yaw REAL,
    pitch REAL,
    inventory TEXT,
    armor TEXT,
    offhand TEXT DEFAULT '',
    xp INT,
    health REAL,
    expires INT,
    dead_on_login INT DEFAULT 0
);
-- #   }

-- #   { select
SELECT * FROM glacia_combat_loggers;
-- #   }

-- #   { get
-- #      :uuid string
SELECT * FROM glacia_combat_loggers WHERE uuid = :uuid;
-- #   }

-- #   { create
-- #      :uuid string
-- #      :username string
-- #      :world string
-- #      :x float
-- #      :y float
-- #      :z float
-- #      :yaw float
-- #      :pitch float
-- #      :inventory string
-- #      :armor string
-- #      :offhand string
-- #      :xp int
-- #      :health float
-- #      :expires int
INSERT OR REPLACE INTO glacia_combat_loggers (
    uuid, username, world, x, y, z, yaw, pitch, inventory, armor, offhand, xp, health, expires) 
VALUES (
    :uuid, :username,
    :world, :x, :y, :z, :yaw, :pitch,
    :inventory, :armor, :offhand,
    :xp, :health, :expires
);
-- #   }

-- #   { update_dead
-- #      :uuid string
UPDATE glacia_combat_loggers
SET dead_on_login = 1
WHERE uuid = :uuid;
-- #   }

-- #   { delete
-- #      :uuid string
DELETE FROM glacia_combat_loggers WHERE uuid = :uuid;
-- #   }

-- #   { delete_all
DELETE FROM glacia_combat_loggers;
-- #   }
-- # }
