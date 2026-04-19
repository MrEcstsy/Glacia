<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

final class QueryStmts {
    
    // PLAYER
    public const PLAYERS_INIT = "glacia_players.init";
    public const PLAYERS_SELECT = "glacia_players.select";
    public const PLAYERS_BALTOP = "glacia_players.baltop";
    public const PLAYERS_BALTOP_COUNT = "glacia_players.baltop_count";
    public const PLAYERS_CREATE = "glacia_players.create";
    public const PLAYERS_UPDATE = "glacia_players.update";
    public const PLAYERS_DELETE = "glacia_players.delete";

    public const PLAYER_RANK_INIT = "glacia_ranks.init";
    public const PLAYER_RANK_SELECT = "glacia_ranks.select";
    public const PLAYER_RANK_CREATE = "glacia_ranks.create";
    public const PLAYER_RANK_UPDATE = "glacia_ranks.update";
    public const PLAYER_RANK_DELETE = "glacia_ranks.delete";

    public const PLAYER_PERMISSIONS_INIT = "glacia_rank_permissions.init";
    public const PLAYER_PERMISSIONS_SELECT = "glacia_rank_permissions.select";
    public const PLAYER_PERMISSIONS_CREATE = "glacia_rank_permissions.create";
    public const PLAYER_PERMISSIONS_UPDATE = "glacia_rank_permissions.update";
    public const PLAYER_PERMISSIONS_DELETE = "glacia_rank_permissions.delete";
    public const PLAYER_PERMISSIONS_DELETE_ALL_FOR_RANK = "glacia_rank_permissions.delete_all_for_rank";

    // SERVER
    public const SERVER_HOMES_INIT = "homes.init";
    public const SERVER_HOMES_SELECT = "homes.select";
    public const SERVER_HOMES_CREATE = "homes.create";
    public const SERVER_HOMES_UPDATE = "homes.update";
    public const SERVER_HOMES_DELETE = "homes.delete";

    public const SERVER_WARPS_INIT = "warps.init";
    public const SERVER_WARPS_SELECT = "warps.select";
    public const SERVER_WARPS_CREATE = "warps.create";
    public const SERVER_WARPS_UPDATE = "warps.update";
    public const SERVER_WARPS_DELETE = "warps.delete";

    public const SERVER_GLACIA_INIT = "glacia_server.init";
    public const SERVER_GLACIA_SELECT = "glacia_server.select";
    public const SERVER_GLACIA_CREATE = "glacia_server.create";
    public const SERVER_GLACIA_UPDATE = "glacia_server.update";
    public const SERVER_GLACIA_DELETE = "glacia_server.delete";

    // FACTIONS
    public const FACTIONS_INIT = "glacia_factions.init";
    public const FACTIONS_SELECT = "glacia_factions.select";
    public const FACTIONS_CREATE = "glacia_factions.create";
    public const FACTIONS_UPDATE = "glacia_factions.update";
    public const FACTIONS_DELETE = "glacia_factions.delete";

    public const FACTION_HOME_INIT = "glacia_faction_homes.init";
    public const FACTION_HOME_SELECT = "glacia_faction_homes.select";
    public const FACTION_HOME_CREATE = "glacia_faction_homes.create";
    public const FACTION_HOME_UPDATE = "glacia_faction_homes.update";
    public const FACTION_HOME_DELETE = "glacia_faction_homes.delete";

    public const FACTION_ROLES_INIT = "glacia_faction_roles.init";
    public const FACTION_ROLES_SELECT = "glacia_faction_roles.select";
    public const FACTION_ROLES_CREATE = "glacia_faction_roles.create";
    public const FACTION_ROLES_UPDATE = "glacia_faction_roles.update";
    public const FACTION_ROLES_DELETE = "glacia_faction_roles.delete";

    public const FACTION_MEMBERS_INIT = "glacia_faction_members.init";
    public const FACTION_MEMBERS_SELECT = "glacia_faction_members.select";
    public const FACTION_MEMBERS_SELECT_BY_FACTION = "glacia_faction_members.select_by_faction";
    public const FACTION_MEMBERS_CREATE = "glacia_faction_members.create";
    public const FACTION_MEMBERS_UPDATE = "glacia_faction_members.update";
    public const FACTION_MEMBERS_DELETE = "glacia_faction_members.delete";

    public const FACTION_CLAIM_INIT = "glacia_faction_claims.init";
    public const FACTION_CLAIM_SELECT = "glacia_faction_claims.select";
    public const FACTION_CLAIM_CREATE = "glacia_faction_claims.create";
    public const FACTION_CLAIM_UPDATE = "glacia_faction_claims.update";
    public const FACTION_CLAIM_DELETE = "glacia_faction_claims.delete";
    public const FACTION_CLAIM_DELETE_FACTION = "glacia_faction_claims.deleteByFaction";

    public const FACTION_RELATIONS_INIT = "glacia_faction_relations.init";
    public const FACTION_RELATIONS_SELECT = "glacia_faction_relations.select";
    public const FACTION_RELATIONS_CREATE = "glacia_faction_relations.create";
    public const FACTION_RELATIONS_UPDATE = "glacia_faction_relations.update";
    public const FACTION_RELATIONS_DELETE = "glacia_faction_relations.delete";

    public const FACTION_UPGRADE_BANK_INIT = "glacia_faction_upgrade_bank.init";
    public const FACTION_UPGRADE_BANK_SELECT = "glacia_faction_upgrade_bank.select";
    public const FACTION_UPGRADE_BANK_CREATE = "glacia_faction_upgrade_bank.create";
    public const FACTION_UPGRADE_BANK_DELETE = "glacia_faction_upgrade_bank.delete";

    // COINFLIP QUERY
    public const COINFLIP_INIT = "coinflip.initialize";
    public const COINFLIP_SELECT = "coinflip.select";
    public const COINFLIP_CREATE = "coinflip.create";
    public const COINFLIP_DELETE = "coinflip.delete";

    // JACKPOT QUERY
    public const JACKPOT_INIT = "glacia_jackpot.init";
    public const JACKPOT_SELECT = "glacia_jackpot.select";
    public const JACKPOT_UPDATE = "glacia_jackpot.update";
    public const JACKPOT_INSERT = "glacia_jackpot.insert";

    // JACKPOT STATS QUERY
    public const JACKPOT_STATS_INIT = "glacia_jackpot_stats.init";
    public const JACKPOT_STATS_SELECT = "glacia_jackpot_stats.select";
    public const JACKPOT_STATS_UPDATE = "glacia_jackpot_stats.update";
    public const JACKPOT_STATS_INSERT = "glacia_jackpot_stats.insert";

    // BOUNTIES
    public const BOUNTIES_INIT = "glacia_bounties.init";
    public const BOUNTIES_SELECT = "glacia_bounties.select";
    public const BOUNTIES_CREATE = "glacia_bounties.create";
    public const BOUNTIES_DELETE = "glacia_bounties.delete";

    // COMBAT LOGGER QUERY
    public const COMBAT_LOGGER_INIT = "glacia_combat_loggers.init";
    public const COMBAT_LOGGER_SELECT = "glacia_combat_loggers.select";
    public const COMBAT_LOGGER_GET = "glacia_combat_loggers.get";
    public const COMBAT_LOGGER_CREATE = "glacia_combat_loggers.create";
    public const COMBAT_LOGGER_DELETE = "glacia_combat_loggers.delete";
    public const COMBAT_LOGGER_UPDATE_DEAD = "glacia_combat_loggers.update_dead";

    // AUCTION HOUSE
    public const AUCTION_HOUSE_LISTINGS_INIT = "glacia_auction_house_listings.init";
    public const AUCTION_HOUSE_LISTINGS_SELECT = "glacia_auction_house_listings.select";
    public const AUCTION_HOUSE_LISTINGS_CREATE = "glacia_auction_house_listings.create";
    public const AUCTION_HOUSE_LISTINGS_DELETE = "glacia_auction_house_listings.delete";

    public const AUCTION_HOUSE_COLLECTION_INIT = "glacia_auction_house_collection.init";
    public const AUCTION_HOUSE_COLLECTION_SELECT = "glacia_auction_house_collection.select";
    public const AUCTION_HOUSE_COLLECTION_CREATE = "glacia_auction_house_collection.create";
    public const AUCTION_HOUSE_COLLECTION_DELETE = "glacia_auction_house_collection.delete";

    // PLAYER VAULTS
    public const PLAYER_VAULTS_INIT = "glacia_player_vaults.init";
    public const PLAYER_VAULTS_SELECT = "glacia_player_vaults.select";
    public const PLAYER_VAULTS_CREATE = "glacia_player_vaults.create";
    public const PLAYER_VAULTS_DELETE = "glacia_player_vaults.delete";
}
