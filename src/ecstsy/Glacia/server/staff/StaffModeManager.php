<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\GameRuleUtils;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class StaffModeManager {

    public const NBT_STAFF_TOOL = "GlaciaStaffTool";
    public const TOOL_TP = "tp";
    public const TOOL_PUNISH = "punish";
    public const TOOL_EXIT = "exit";

    private static bool $configLoaded = false;
    private static bool $noClip = true;

    /** @var list<array{label: string, duration: string}> duration "" = permanent */
    private static array $punishmentPresets = [];

    /**
     * @var array<string, array{
     *   gamemode: GameMode,
     *   allow_flight: bool,
     *   flying: bool,
     *   block_collision: bool,
     *   auto_vanish: bool,
     *   inventory: array<int, Item>,
     *   armor: array<int, Item>,
     *   offhand: Item,
     *   cursor: array<int, Item>,
     *   show_coordinates: bool
     * }>
     */
    private static array $snapshots = [];

    public static function loadFromConfig() : void {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $section = (array) ($cfg?->get("staff-mode", []) ?? []);
        self::$noClip = (bool) ($section["no-clip"] ?? true);
        self::$punishmentPresets = [];
        foreach ((array) ($section["punishment-presets"] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row["label"] ?? ""));
            if ($label === "") {
                continue;
            }
            $duration = trim((string) ($row["duration"] ?? ""));
            self::$punishmentPresets[] = ["label" => $label, "duration" => $duration];
        }
        if (self::$punishmentPresets === []) {
            self::$punishmentPresets = [
                ["label" => "1 hour", "duration" => "1h"],
                ["label" => "1 day", "duration" => "1d"],
                ["label" => "7 days", "duration" => "7d"],
                ["label" => "Permanent", "duration" => ""],
            ];
        }
        self::$configLoaded = true;
    }

    public static function isInStaffMode(Player $player) : bool {
        return isset(self::$snapshots[$player->getUniqueId()->toString()]);
    }

    public static function shouldHideFromPlayerList(Player $subject) : bool {
        return VanishManager::isVanished($subject);
    }

    public static function canViewerSeeHiddenStaff(?Player $viewer) : bool {
        return VanishManager::canViewerSeeVanished($viewer);
    }

    public static function shouldHideStaffFromViewer(?Player $viewer, Player $staff): bool
    {
        return self::shouldHideFromPlayerList($staff) && !self::canViewerSeeHiddenStaff($viewer);
    }

    public static function toggle(Player $player, GlaciaPlayer $session) : void {
        if (!self::$configLoaded) {
            self::loadFromConfig();
        }
        if (self::isInStaffMode($player)) {
            self::exit($player, $session);
        } else {
            self::enter($player, $session);
        }
    }

    public static function enter(Player $player, GlaciaPlayer $session) : void {
        if (self::isInStaffMode($player)) {
            return;
        }
        if (!$player->hasPermission("glacia.admin")) {
            $player->sendMessage(C::colorize("&r&cYou cannot use staff mode."));
            return;
        }

        $uuid = $player->getUniqueId()->toString();
        self::$snapshots[$uuid] = [
            "gamemode" => $player->getGamemode(),
            "allow_flight" => $player->getAllowFlight(),
            "flying" => $player->isFlying(),
            "block_collision" => $player->hasBlockCollision(),
            "auto_vanish" => !VanishManager::isVanished($player),
            "inventory" => self::cloneItemMap($player->getInventory()->getContents()),
            "armor" => self::cloneItemMap($player->getArmorInventory()->getContents()),
            "offhand" => clone $player->getOffHandInventory()->getItem(0),
            "cursor" => self::cloneItemMap($player->getCursorInventory()->getContents()),
            "show_coordinates" => (bool) ($session->getSetting("show_coordinates") ?? true),
        ];

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getOffHandInventory()->clearAll();
        $player->getCursorInventory()->clearAll();

        $player->setGamemode(GameMode::ADVENTURE());
        $player->setAllowFlight(true);
        $player->setFlying(true);
        if (self::$noClip) {
            $player->setHasBlockCollision(false);
        }

        $inv = $player->getInventory();
        $inv->setItem(0, self::tagTool(VanillaItems::COMPASS()->setCustomName(C::colorize("&r&bStaff &fTeleport")), self::TOOL_TP));
        $inv->setItem(1, self::tagTool(VanillaItems::BOOK()->setCustomName(C::colorize("&r&cStaff &fPunish")), self::TOOL_PUNISH));
        $inv->setItem(8, self::tagTool(VanillaItems::CLOCK()->setCustomName(C::colorize("&r&aExit &7Staff Mode")), self::TOOL_EXIT));

        if ((self::$snapshots[$uuid]["auto_vanish"] ?? false) === true) {
            VanishManager::enable($player);
        }
        // Force coordinate HUD off while in staff mode, but keep a snapshot so we can restore.
        $session->setSetting("show_coordinates", false);
        GameRuleUtils::modifyGameRule($player, GameRuleUtils::RULE_SHOW_COORDINATES, false, true);
        $session->setInAdminMode(true);
        $player->sendMessage(GlaciaUtils::format("staff-mode.enabled"));
        GlaciaUtils::logAdminAction("Staff Mode Entered", ["admin" => $player->getName()]);
    }

    public static function exit(Player $player, GlaciaPlayer $session) : void {
        $uuid = $player->getUniqueId()->toString();
        $snap = self::$snapshots[$uuid] ?? null;
        if ($snap === null) {
            return;
        }
        unset(self::$snapshots[$uuid]);

        $player->setGamemode($snap["gamemode"]);
        $player->setAllowFlight($snap["allow_flight"]);
        $player->setFlying($snap["flying"]);
        $player->setHasBlockCollision($snap["block_collision"]);

        $player->getInventory()->setContents($snap["inventory"]);
        $player->getArmorInventory()->setContents($snap["armor"]);
        $player->getOffHandInventory()->setItem(0, $snap["offhand"]);
        $player->getCursorInventory()->setContents($snap["cursor"]);
        $session->setSetting("show_coordinates", (bool) ($snap["show_coordinates"] ?? true));
        GameRuleUtils::modifyGameRule($player, GameRuleUtils::RULE_SHOW_COORDINATES, (bool) ($snap["show_coordinates"] ?? true), true);

        $session->setInAdminMode(false);

        if (($snap["auto_vanish"] ?? false) === true) {
            VanishManager::disable($player, true);
        }

        $worldManager = Server::getInstance()->getWorldManager();
        $world = $worldManager->getDefaultWorld();
        if ($world === null && $worldManager->loadWorld("world")) {
            $world = $worldManager->getWorldByName("world");
        }
        if ($world !== null) {
            ZoneManager::allowNextBoundaryCrossing($player, 15);
            $player->teleport($world->getSpawnLocation());
        }

        $player->sendMessage(GlaciaUtils::format("staff-mode.disabled"));
        GlaciaUtils::logAdminAction("Staff Mode Exited", ["admin" => $player->getName()]);
    }

    /**
     * Restore saved gear before the player is removed so disk persistence matches their real inventory.
     */
    public static function restoreSnapshotForDisconnect(Player $player) : void {
        $uuid = $player->getUniqueId()->toString();
        $snap = self::$snapshots[$uuid] ?? null;
        if ($snap === null) {
            return;
        }
        unset(self::$snapshots[$uuid]);
        $player->getInventory()->setContents($snap["inventory"]);
        $player->getArmorInventory()->setContents($snap["armor"]);
        $player->getOffHandInventory()->setItem(0, $snap["offhand"]);
        $player->getCursorInventory()->setContents($snap["cursor"]);
        $player->setGamemode($snap["gamemode"]);
        $player->setAllowFlight($snap["allow_flight"]);
        $player->setFlying($snap["flying"]);
        $player->setHasBlockCollision($snap["block_collision"]);
        GameRuleUtils::modifyGameRule($player, GameRuleUtils::RULE_SHOW_COORDINATES, (bool) ($snap["show_coordinates"] ?? true), true);
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session !== null) {
            $session->setSetting("show_coordinates", (bool) ($snap["show_coordinates"] ?? true));
            $session->setInAdminMode(false);
        }

        if (($snap["auto_vanish"] ?? false) === true) {
            VanishManager::disable($player, false);
        }
    }

    public static function onPlayerJoinVisible(Player $joiner) : void {
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            if ($online === $joiner) {
                continue;
            }
            if (self::shouldHideFromPlayerList($online) && !self::canViewerSeeHiddenStaff($joiner)) {
                $joiner->hidePlayer($online);
            }
            if (self::shouldHideFromPlayerList($joiner) && !self::canViewerSeeHiddenStaff($online)) {
                $online->hidePlayer($joiner);
            }
        }
    }

    public static function applyHideFromAll(Player $staff) : void {
        foreach (Server::getInstance()->getOnlinePlayers() as $viewer) {
            if ($viewer === $staff) {
                continue;
            }
            if (!self::canViewerSeeHiddenStaff($viewer)) {
                $viewer->hidePlayer($staff);
            }
        }
    }

    private static function applyShowToAll(Player $staff) : void {
        foreach (Server::getInstance()->getOnlinePlayers() as $viewer) {
            if ($viewer === $staff) {
                continue;
            }
            $viewer->showPlayer($staff);
        }
    }

    public static function handleToolUse(Player $player, Item $item) : void {
        $id = self::readToolId($item);
        if ($id === null) {
            return;
        }
        match ($id) {
            self::TOOL_TP => self::openTeleportForm($player),
            self::TOOL_PUNISH => self::openPunishPickPlayer($player),
            self::TOOL_EXIT => self::exit($player, Loader::getPlayerManager()->getSession($player)),
            default => null,
        };
    }

    private static function openTeleportForm(Player $staff) : void {
        $buttons = [];
        foreach (Server::getInstance()->getOnlinePlayers() as $p) {
            if ($p === $staff) {
                continue;
            }
            if (self::shouldHideFromPlayerList($p) && !self::canViewerSeeHiddenStaff($staff)) {
                continue;
            }
            $buttons[] = $p;
        }
        if ($buttons === []) {
            $staff->sendMessage(C::colorize("&r&cNo players are available to teleport to."));
            return;
        }
        $form = new SimpleForm(function (Player $player, mixed $data) use ($buttons) : void {
            if (!is_int($data) || !isset($buttons[$data])) {
                return;
            }
            if (!self::isInStaffMode($player)) {
                return;
            }
            $target = $buttons[$data];
            if (!$target->isConnected()) {
                $player->sendMessage(C::colorize("&r&cThat player is no longer online."));
                return;
            }
            ZoneManager::allowNextBoundaryCrossing($player, 15);
            ZoneManager::allowNextBoundaryCrossing($target, 15);
            $player->teleport($target->getLocation());
            $player->sendMessage(C::colorize("&r&aTeleported to &f" . $target->getName() . "&a."));
            GlaciaUtils::logAdminAction("Staff Teleport", ["admin" => $player->getName(), "target" => $target->getName()]);
        });
        $form->setTitle(C::colorize("&l&3Staff Teleport"));
        $form->setContent(C::colorize("&7Choose a player."));
        foreach ($buttons as $p) {
            $form->addButton(C::colorize("&0" . $p->getName()));
        }
        $staff->sendForm($form);
    }

    private static function openPunishPickPlayer(Player $staff) : void {
        $buttons = [];
        foreach (Server::getInstance()->getOnlinePlayers() as $p) {
            if ($p === $staff) {
                continue;
            }
            if (self::shouldHideFromPlayerList($p) && !self::canViewerSeeHiddenStaff($staff)) {
                continue;
            }
            $buttons[] = $p;
        }
        if ($buttons === []) {
            $staff->sendMessage(C::colorize("&r&cNo other players are online."));
            return;
        }
        $form = new SimpleForm(function (Player $player, mixed $data) use ($buttons) : void {
            if (!is_int($data) || !isset($buttons[$data])) {
                return;
            }
            if (!self::isInStaffMode($player)) {
                return;
            }
            $target = $buttons[$data];
            self::openPunishPresets($player, $target->getName());
        });
        $form->setTitle(C::colorize("&l&cStaff Punish"));
        $form->setContent(C::colorize("&7Pick a player, then a ban length."));
        foreach ($buttons as $p) {
            $form->addButton(C::colorize("&0" . $p->getName()));
        }
        $staff->sendForm($form);
    }

    private static function openPunishPresets(Player $staff, string $targetName) : void {
        $presets = self::$punishmentPresets;
        $form = new SimpleForm(function (Player $player, mixed $data) use ($targetName, $presets) : void {
            if (!is_int($data) || !isset($presets[$data])) {
                return;
            }
            if (!self::isInStaffMode($player)) {
                return;
            }
            $preset = $presets[$data];
            self::applyBanFromPreset($player, $targetName, $preset["label"], $preset["duration"]);
        });
        $form->setTitle(C::colorize("&l&cBan: &f" . $targetName));
        $form->setContent(C::colorize("&7Select a duration."));
        foreach ($presets as $p) {
            $dur = $p["duration"] !== "" ? $p["duration"] : "permanent";
            $form->addButton(C::colorize("&0" . $p["label"] . "\n&8" . $dur));
        }
        $staff->sendForm($form);
    }

    /**
     * @param array{label: string, duration: string} $preset
     */
    private static function applyBanFromPreset(Player $staff, string $targetName, string $label, string $durationSpec) : void {
        if (strcasecmp($staff->getName(), $targetName) === 0) {
            $staff->sendMessage(C::colorize("&r&cYou cannot ban yourself."));
            return;
        }
        $banList = Server::getInstance()->getNameBans();
        if ($banList->isBanned($targetName)) {
            $staff->sendMessage(C::colorize("&r&e{$targetName} is already banned."));
            return;
        }
        $reason = "Staff mode ({$label})";
        $expires = null;
        if (trim($durationSpec) !== "") {
            $sec = GeneralUtils::parseTimeString($durationSpec);
            if ($sec <= 0) {
                $staff->sendMessage(C::colorize("&r&cInvalid duration in misc.yml for preset: {$label}"));
                return;
            }
            $expires = new \DateTime("+" . $sec . " seconds");
        }
        $banList->addBan($targetName, $reason, $expires, $staff->getName());

        $onlineTarget = PlayerUtils::getPlayerByPrefix($targetName);
        if ($onlineTarget !== null && strcasecmp($onlineTarget->getName(), $targetName) === 0) {
            $onlineTarget->kick(C::colorize("&cBanned: &f{$reason}"));
            $targetName = $onlineTarget->getName();
        }

        $staff->sendMessage(C::colorize("&r&aBanned &f{$targetName}&a (&f{$label}&a)."));
        GlaciaUtils::logAdminAction("Staff Ban", [
            "admin" => $staff->getName(),
            "target" => $targetName,
            "preset" => $label,
            "duration" => $durationSpec !== "" ? $durationSpec : "permanent",
        ]);
    }

    public static function readToolId(Item $item) : ?string {
        $tag = $item->getNamedTag();
        if ($tag->getTag(self::NBT_STAFF_TOOL) === null) {
            return null;
        }
        $v = $tag->getString(self::NBT_STAFF_TOOL, "");
        return $v !== "" ? $v : null;
    }

    private static function tagTool(Item $item, string $toolId) : Item {
        $nbt = clone $item->getNamedTag();
        $nbt->setString(self::NBT_STAFF_TOOL, $toolId);
        return $item->setNamedTag($nbt);
    }

    /**
     * @return array<int, Item>
     */
    private static function cloneItemMap(array $items) : array {
        $out = [];
        foreach ($items as $slot => $item) {
            $out[(int) $slot] = clone $item;
        }
        return $out;
    }

    /**
     * @param PlayerListEntry[] $entries
     * @return PlayerListEntry[]
     */
    public static function filterPlayerListAddEntries(array $entries, ?Player $viewer) : array {
        if ($viewer !== null && self::canViewerSeeHiddenStaff($viewer)) {
            return $entries;
        }
        $filtered = [];
        foreach ($entries as $entry) {
            $p = Server::getInstance()->getPlayerByUUID($entry->uuid);
            if ($p !== null && self::shouldHideFromPlayerList($p)) {
                continue;
            }
            $filtered[] = $entry;
        }
        return $filtered;
    }
}
