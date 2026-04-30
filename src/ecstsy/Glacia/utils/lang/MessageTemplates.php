<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\lang;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;

final class MessageTemplates {

    public static array $messages = [];
    public static array $joinMessages = [];

    public static function load(): void
    {
        self::$messages = self::getDefaultMessages();
        self::$joinMessages = self::getDefaultJoinMessages();

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "messages.yml");
        if ($config === null) {
            return;
        }

        $messageOverrides = $config->get("messages", []);
        if (is_array($messageOverrides)) {
            foreach ($messageOverrides as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    self::$messages[$key] = $value;
                }
            }
        }

        $joinOverrides = $config->get("join-messages", []);
        if (is_array($joinOverrides)) {
            self::$joinMessages = array_values(array_map("strval", $joinOverrides));
        }
    }

    public static function getMessage(string $key): ?string
    {
        $aliases = [
            "player.no_faction" => "player.error.no_faction",
            "faction.chat_set" => "faction.status.chat_set",
            "heal.success-target" => "heal.success.target",
            "heal.success-other" => "heal.success.other",
        ];

        $resolved = $aliases[$key] ?? $key;
        if (isset(self::$messages[$resolved])) {
            return self::$messages[$resolved];
        }

        $normalized = str_replace(["-", "_"], ".", $resolved);
        if (isset(self::$messages[$normalized])) {
            return self::$messages[$normalized];
        }

        return null;
    }

    public static function render(string $key, array $replacements = [], ?string $fallback = null): string
    {
        $template = self::getMessage($key) ?? $fallback ?? $key;
        foreach ($replacements as $k => $v) {
            $normalizedKey = trim((string) $k, "{}");
            $template = str_replace("{" . $normalizedKey . "}", (string) $v, $template);
        }

        return $template;
    }

    private static function getDefaultMessages(): array
    {
        return [
            "general.ingame-only" => "&r&cThis command can only be used in-game.",

            "player.error.invalid" => "&r&c'{name}' &7does not exist or is offline.",
            "player.error.no_faction" => "&r&7You are not in a faction.",
            "player.error.already_in_faction" => "&r&7You are already in a faction.",
            "player.error.not_in_your_faction" => "&r&c'{name}' &7is not in your faction.",
            "player.error.no_permission" => "&r&7You don't have permission to do that.",
            "player.error.no_session" => "&r&7There is an issue with your session. Please rejoin or contact support.",
            "player.status.joined_faction" => "&r&aYou have joined the faction &2'{name}'&a.",
            "player.command.cooldown" => "&r&cYou must wait {time} before using this command again.",

            "faction.error.exists" => "&r&7A faction named &c{name} &7already exists.",
            "faction.error.not_exists" => "&r&7Faction &c'{name}' &7does not exist.",
            "faction.error.page_not_found" => "&r&7Page &c{page} &7not found. &7&o(Max: {maxPage})",
            "faction.error.not_member" => "&r&c{name} &7is not a member of your faction.",
            "faction.error.cant_demote" => "&r&7You can't demote this member.",
            "faction.error.permission_denied" => "&r&7Your role doesn't allow you to do this.",
            "faction.error.cant_kick_self" => "&r&cYou can't kick yourself.",
            "faction.error.cant_kick_leader" => "&r&cYou can't kick the leader.",
            "faction.error.no_pending_allies" => "&r&7You have no pending ally requests.",
            "faction.error.max_allies" => "&r&7You or '{faction}' have reached the maximum number of allies.",
            "faction.error.cant_neutral_self" => "&r&cYou can't neutral your own faction.",
            "faction.error.cant_ally_self" => "&r&cYou can't ally with your own faction.",
            "faction.error.cant_enemy_self" => "&r&cYou can't enemy your own faction.",
            "faction.error.already_allied" => "&r&7You are already allied with &2'{faction}'.",
            "faction.status.chat_set" => "&r&7Chat has been set to &b{chatType}&7.",
            "faction.status.demote.sender" => "&r&7You demoted &2'{player}' &7to &2'{role}'.",
            "faction.status.demote.target" => "&r&cYou were demoted to &2'{role}'.",
            "faction.status.description_set" => "&r&7Faction description set to &2'{description}'.",
            "faction.status.invite_sent" => "&r&7Faction invite sent to &2'{player}' &7they have &2{time} &7to accept.",
            "faction.status.invite_received" => "&r&7You have been invited to &2'{faction}'&7. You have &2{time} &7to accept. &8Tip: &f/f join {faction} &7(or &f/f accept {faction}&7).",
            "faction.status.no_invite" => "&r&7You have no pending invites from this faction.",
            "faction.status.joined" => "&r&2✔ {name} &7has joined your faction.",

            "rank.error.invalid" => "&r&7Rank &c'{name}' &7does not exist.",
            "rank.status.assigned" => "&r&7Assigned rank &2'{name}' &7to &2'{player}'.",

            "staff-mode.enabled" => "&r&aStaff mode enabled. &7You are hidden, invulnerable, and have staff tools. Use the clock to exit.",
            "staff-mode.disabled" => "&r&7Staff mode disabled. &8Returning you to spawn with your gear.",

            "balance.info" => "&r&b◆ &fBalance&8: &b\${balance} &r&b◆",
            "balance.info.other" => "&r&b◆ &f{name}'s Balance&8: &b\${balance} &r&b◆",
            "balance.richer-than" => "&r&7You're richer than them.",
            "balance.poorer-than" => "&r&7They're richer than you.",
            "balance.equal" => "&r&7You both have equal balance.",

            "coinflip.error.invalid_amount" => "&r&cAmount must be at least $1,000.",
            "coinflip.error.already_submitted" => "&r&cYou have already submitted a coin flip.",

            "home.created" => "&r&aSuccessfully created home '{home}'.",

            "warp.usage" => "&r&cUsage: /setwarp <warpname>",
            "warp.created" => "&r&aWarp '{warp}' has been created.",
            "warp.exists" => "&r&cWarp '{warp}' already exists.",

            "dupe.no_item" => "&r&cYou must be holding an item to duplicate.",
            "dupe.no_space" => "&r&cYou do not have enough inventory space to duplicate that item.",
            "dupe.success" => "&r&aDuplicated &f{name} &ax{count}.",

            "feed.success" => "&r&aYou have been fed.",

            "fixall.success" => "&r&aYour items have been repaired.",
            "fixall.no_damaged" => "&r&cYou have no damaged items to repair.",
            "fix.success" => "&r&aYour item has been repaired.",
            "fix.no_damaged" => "&r&cYou must be holding a damaged item to repair it.",

            "flyspeed.success" => "&r&aYour fly speed has been set to {speed}.",
            "flyspeed.invalid" => "&r&cInvalid speed.",

            "heal.success" => "&r&aYou have been healed.",
            "heal.success.other" => "&r&aYou have been healed by {player}.",
            "heal.success.target" => "&r&aYou have healed {player}.",

            "settings.menu.title" => "&r&8Server Settings",
            "settings.menu.content" => "&r&fToggle server features to better fit how you play.",
            "settings.menu.button.chest_inventories" => "Chest Inventories",
            "settings.menu.button.broadcasts" => "Announcer",
            "settings.menu.button.notifications" => "Notifications",
            "settings.menu.button.quick_claim" => "Quick Redeem",
            "settings.menu.button.show_coordinates" => "Show Coordinates",
            "settings.menu.button.scoreboard" => "Scoreboard",
            "settings.toast.label.chest_inventories" => "Chest GUI",
            "settings.toast.label.broadcasts" => "Announcer",
            "settings.toast.label.notifications" => "Notifications",
            "settings.toast.label.quick_claim" => "Quick redeem",
            "settings.toast.label.show_coordinates" => "Coordinates (HUD)",
            "settings.toast.state" => "&r&7{label} setting has been set to: {color}{state}",
            "settings.toast.enabled" => "Enabled",
            "settings.toast.disabled" => "Disabled",
            "settings.coordinates.staff_mode" => "&r&eCoordinates are forced off while in staff mode.",

            "scoreboard.menu.title" => "&r&8Scoreboard",
            "scoreboard.menu.content" => "&r&7Manage your sidebar, choose automatic area-based boards, or build a custom one.",
            "scoreboard.menu.button.visibility" => "Visibility: {state}",
            "scoreboard.menu.button.profile" => "Profile: {profile}",
            "scoreboard.menu.button.edit" => "Edit Custom Board",
            "scoreboard.state.on" => "ON",
            "scoreboard.state.off" => "OFF",
            "scoreboard.message.enabled" => "&r&aScoreboard enabled.",
            "scoreboard.message.disabled" => "&r&cScoreboard hidden.",
            "scoreboard.usage" => "&r&cUsage: /scoreboard <on|off|profile|edit>",
            "scoreboard.profile.title" => "&r&8Scoreboard Profiles",
            "scoreboard.profile.content" => "&r&7Auto will switch into area-specific scoreboards, like Astral Veil when you enter that zone.",
            "scoreboard.profile.button.auto" => "Auto",
            "scoreboard.profile.button.custom" => "Custom",
            "scoreboard.profile.set" => "&r&aScoreboard profile set to &f{profile}&a.",
            "scoreboard.custom.title" => "&r&8Custom Scoreboard",
            "scoreboard.custom.help" => "&r&7Build a compact mobile-friendly board. Leave lines blank to skip them.\n\n&r&fUseful tokens:\n&r&b{tokens}",
            "scoreboard.custom.input.title" => "Title",
            "scoreboard.custom.input.title_placeholder" => "&bCustom Board",
            "scoreboard.custom.input.line" => "Line {number}",
            "scoreboard.custom.input.line_placeholder" => "Optional",
            "scoreboard.custom.saved" => "&r&aCustom scoreboard saved and activated.",

            "trash.menu.title" => "&r&8Trash",
            "trash.message.open" => "&r&eAnything left inside this menu will be deleted when it closes.",
            "trash.message.closed" => "&r&7Closed trash. All items left inside were deleted.",

            "enderchest.title.self" => "&r&8Ender Chest",
            "enderchest.title.other" => "&r&8{player}'s Ender Chest",
            "enderchest.opened.self" => "&r&aOpened your ender chest.",
            "enderchest.error.player_not_found" => "&r&cThat player could not be found.",

            "back.error.none" => "&r&cYou do not have a previous location to return to.",
            "back.error.world_unloaded" => "&r&cThat previous world is not currently loaded.",
            "back.success" => "&r&aTeleported you back to your previous location.",

            "tpa.error.target_busy" => "&r&cThat player already has a pending teleport request.",
            "tpa.error.not_found" => "&r&cNo teleport request from &f{player}&r&c was found.",
            "tpa.error.expired.incoming" => "&r&cThe teleport request from &f{player}&r&c has expired.",
            "tpa.error.expired.outgoing" => "&r&cYour teleport request to &f{player}&r&c has expired.",
            "tpa.request.received.to_you" => "&r&b&l[TPA] &r&f{player} has requested to teleport to you. Use &b/tpaccept {player} &for &b/tpdeny {player}&f.",
            "tpa.request.received.here" => "&r&b&l[TPA] &r&f{player} has requested to teleport you to them. Use &b/tpaccept {player} &for &b/tpdeny {player}&f.",
            "tpa.request.sent" => "&r&b&l[TPA] &r&fTeleport request sent to {player}.",
            "tpa.accepted.sender" => "&r&b&l[/TPA] &r&f{player} has accepted your teleport request.",
            "tpa.accepted.sender.here" => "&r&b&l[/TPAHERE] &r&f{player} has accepted your teleport request.",
            "tpa.accepted.target" => "&r&b&l[TPA] &r&fYou accepted {player}'s teleport request.",
            "tpa.denied.target" => "&r&b&l[TPA] &r&fYou have denied the teleport request from {player}.",
            "tpa.denied.sender" => "&r&b&l[TPA] &r&fYour teleport request to {player} has been denied.",

            "itemflip.credits" => "&r&6Item Flip Credits: &f{credits}",
            "itemflip.pending.none" => "&r&7No pending Item Flip requests.",
            "itemflip.pending.header" => "&r&ePending Item Flip Requests:",
            "itemflip.pending.entry" => "&r&8- &f{player} &7for &f{credits} &7credits &8(&f{expires}s&8)",
            "itemflip.error.player_offline" => "&r&cThat player is not online.",
            "itemflip.error.self" => "&r&cYou cannot item flip yourself.",
            "itemflip.help.title" => "&r&6Item Flip Commands",
            "itemflip.help.challenge" => "&r&e/if <player> &7- challenge or accept that player",
            "itemflip.help.pending" => "&r&e/if pending &7- list incoming requests",
            "itemflip.help.credits" => "&r&e/if credits &7- view your Item Flip Credits",
            "itemflip.help.rules" => "&r&8Both players must be in a safe zone and within 10 blocks.",

            "giveitem.menu.category.title" => "&r&8GI Menu &7- Categories",
            "giveitem.menu.category.content" => "&r&7Pick a category.",
            "giveitem.menu.category.button" => "&r&b{display}\n&r&7{count} items",
            "giveitem.category.ethereal_crates" => "Ethereal Crates",
            "giveitem.menu.item.title" => "&r&8GI Menu &7- {display}",
            "giveitem.menu.item.content" => "&r&7Click an item to get &f1x&r&7.",
            "giveitem.menu.item.button" => "&r&f{label}\n&r&8{id}",
            "giveitem.menu.item.dynamic_id" => "crate:{id}",
            "giveitem.menu.item.back" => "&r&cBack",
            "giveitem.form.title" => "&r&8GI Menu &7- {item}",
            "giveitem.form.content.heroic_upgrade" => "&r&7Choose the success rate for this Heroic Upgrade.",
            "giveitem.form.content.charm_shard" => "&r&7Choose the base success and any bonus success to bake into this Charm Shard.",
            "giveitem.form.content.charm_dust" => "&r&7Set how much bonus success this Charm Dust should add.",
            "giveitem.form.content.blackscroll" => "&r&7Choose the success rate for this Black Scroll.",
            "giveitem.form.content.orb" => "&r&7Configure the orb type, max slots, and success rate.",
            "giveitem.form.content.grouped_item" => "&r&7Choose the enchant group for this item.",
            "giveitem.form.content.numeric_item" => "&r&7Set the amount this item should store.",
            "giveitem.form.content.soul_compass" => "&r&7Set how many uses this Soul Compass should have.",
            "giveitem.form.content.faction_xp_booster" => "&r&7Choose the XP multiplier and duration for this booster.",
            "giveitem.form.content.pet_duration_rune" => "&r&7Choose which pet this Attunement Rune should boost.",
            "giveitem.form.field.success" => "Success %",
            "giveitem.form.field.base_success" => "Base Success %",
            "giveitem.form.field.bonus_success" => "Bonus Success %",
            "giveitem.form.field.bonus" => "Bonus Value",
            "giveitem.form.field.orb_type" => "Orb Type",
            "giveitem.form.field.max_slots" => "Max Slots",
            "giveitem.form.field.group" => "Enchant Group",
            "giveitem.form.field.percent" => "Percent",
            "giveitem.form.field.amount" => "Amount",
            "giveitem.form.field.uses" => "Uses",
            "giveitem.form.field.multiplier" => "Multiplier",
            "giveitem.form.field.duration" => "Duration",
            "giveitem.form.field.pet" => "Pet",
            "giveitem.error.no_categories" => "&cNo item categories are available.",
            "giveitem.error.no_items" => "&cNo items are available in that category.",
            "giveitem.error.manual_item" => "&r&cThis item needs extra parameters. Use &f/gi {id}&c manually.",
            "giveitem.error.invalid_crate" => "&r&cThe ethereal crate &f{id}&c is not configured.",
            "giveitem.error.invalid_success" => "&r&cSuccess must be a number between 1 and 100.",
            "giveitem.error.invalid_bonus" => "&r&cBonus must be a valid positive number.",
            "giveitem.error.invalid_orb_type" => "&r&cOrb type must be weapon, armor, or tool.",
            "giveitem.error.invalid_max_slots" => "&r&cMax slots must be a number between 1 and 100.",
            "giveitem.error.invalid_group" => "&r&cThat enchant group is invalid.",
            "giveitem.error.invalid_percent" => "&r&cPercent must be a number between 1 and 100.",
            "giveitem.error.invalid_amount" => "&r&cAmount must be a valid positive number.",
            "giveitem.error.invalid_uses" => "&r&cUses must be a valid positive number.",
            "giveitem.error.invalid_multiplier" => "&r&cMultiplier must be a valid number.",
            "giveitem.error.invalid_duration" => "&r&cDuration cannot be blank.",
            "giveitem.error.invalid_pet" => "&r&cThat pet selection is invalid.",
            "giveitem.success.given" => "&r&aGiven &f1x {name}&r&a.",
        ];
    }

    private static function getDefaultJoinMessages(): array
    {
        return [
            "   &r&l&fGlacia Factions &bETHEREAL HUB",
            " ",
            "   &r&f      Welcome, &b{player}&f!",
            "   &r&f     Online players: &b{online}",
            " ",
            "   &r&f   {nametag}  &r{pingcolor}{ping}ms {networkbar}",
            "   &r&b&l WEBSITE &r&3➤ &fetherealpe.com",
            "  &r&b&l VOTE &r&3➤ &fvote.etherealpe.com",
            " &r&b&l STORE &r&3➤ &fstore.etherealpe.com",
            " ",
            "       &r&bglacia.etherealpe.com"
        ];
    }
}
