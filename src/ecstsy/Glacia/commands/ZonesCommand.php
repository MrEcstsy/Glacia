<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\items\GlaciaSpawnerItem;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
use ecstsy\Glacia\utils\screens\zones\ZonesScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ZonesCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("zone", true));
        $this->registerArgument(2, new RawStringArgument("point", true));
        $this->registerArgument(3, new RawStringArgument("extra", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $action = strtolower((string) ($args["action"] ?? ""));
        if ($action === "set") {
            $this->handleSet($sender, $args);
            return;
        }
        if ($action === "reset") {
            $this->handleReset($sender, $args);
            return;
        }
        if ($action === "setspawn") {
            $this->handleSetSpawn($sender, $args);
            return;
        }
        if ($action === "wand") {
            $this->handleWand($sender, $args);
            return;
        }
        if ($action === "safeset") {
            $this->handleSafeSet($sender, $args);
            return;
        }
        if ($action === "safewand") {
            $this->handleSafeWand($sender, $args);
            return;
        }
        if ($action === "safereset") {
            $this->handleSafeReset($sender, $args);
            return;
        }
        if ($action === "spawner") {
            $this->handleSpawner($sender, $args);
            return;
        }
        if ($action === "trail" || $action === "guide") {
            $this->handleTrail($sender, $args);
            return;
        }

        ZonesScreen::display($sender);
    }

    private function handleSet(Player $sender, array $args): void
    {
        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to edit zones."));
            return;
        }

        $zoneId = strtolower((string) ($args["zone"] ?? ""));
        $point = strtolower((string) ($args["point"] ?? ""));
        if ($zoneId === "" || !in_array($point, ["pos1", "pos2"], true)) {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/zones set <zone> <pos1|pos2>"));
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown zone &f{$zoneId}&c. Available: &f" . implode(", ", array_keys(ZoneManager::getZones()))));
            return;
        }

        if (!ZoneManager::setBoundaryPoint($zoneId, $sender->getPosition(), $point)) {
            $sender->sendMessage(C::colorize("&r&cFailed to update that zone boundary."));
            return;
        }

        $position = $sender->getPosition();
        $sender->sendMessage(C::colorize(
            "&r&aSet &f" . (string) $zone["display"] . " &a{$point} to &f" .
            $position->getWorld()->getFolderName() . " {$position->getFloorX()}, {$position->getFloorY()}, {$position->getFloorZ()}&a."
        ));

        $note = ZoneManager::getLastBoundaryEditNote();
        if ($note !== null) {
            $sender->sendMessage(C::colorize($note));
        }
    }

    private function handleReset(Player $sender, array $args): void
    {
        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to edit zones."));
            return;
        }

        $zoneId = strtolower((string) ($args["zone"] ?? ""));
        if ($zoneId === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/zones reset <zone>"));
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown zone &f{$zoneId}&c. Available: &f" . implode(", ", array_keys(ZoneManager::getZones()))));
            return;
        }

        if (!ZoneManager::resetBoundary($zoneId)) {
            $sender->sendMessage(C::colorize("&r&cFailed to reset that zone boundary."));
            return;
        }

        $sender->sendMessage(C::colorize(
            "&r&aReset saved borders for &f" . (string) ($zone["display"] ?? $zoneId) .
            "&a. Now use &f/zones set {$zoneId} pos1 &aand &f/zones set {$zoneId} pos2&a."
        ));
    }

    private function handleSetSpawn(Player $sender, array $args): void
    {
        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to edit zones."));
            return;
        }

        $zoneId = strtolower((string) ($args["zone"] ?? ""));
        if ($zoneId === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/zones setspawn <zone>"));
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown zone &f{$zoneId}&c. Available: &f" . implode(", ", array_keys(ZoneManager::getZones()))));
            return;
        }

        if (!ZoneManager::setSpawnPoint($zoneId, $sender->getLocation(), $sender->getLocation()->getYaw(), $sender->getLocation()->getPitch())) {
            $sender->sendMessage(C::colorize("&r&cFailed to save that zone spawn."));
            return;
        }

        $position = $sender->getLocation();
        $sender->sendMessage(C::colorize(
            "&r&aSet spawn for &f" . (string) ($zone["display"] ?? $zoneId) . "&a to &f" .
            $position->getWorld()->getFolderName() . " " .
            round($position->getX(), 1) . ", " . round($position->getY(), 1) . ", " . round($position->getZ(), 1) . "&a."
        ));
    }

    private function handleWand(Player $sender, array $args): void
    {
        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to edit zones."));
            return;
        }

        $zoneId = strtolower((string) ($args["zone"] ?? ""));
        if ($zoneId === "" || $zoneId === "off") {
            ZoneManager::endBoundaryEdit($sender);
            $sender->sendMessage(C::colorize("&r&eZone wand mode disabled."));
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown zone &f{$zoneId}&c. Available: &f" . implode(", ", array_keys(ZoneManager::getZones()))));
            return;
        }

        ZoneManager::beginBoundaryEdit($sender, $zoneId);
        $item = ZoneManager::createBoundaryWand($zoneId, C::clean((string) ($zone["display"] ?? $zoneId)));
        foreach ($sender->getInventory()->addItem($item) as $leftover) {
            $sender->getWorld()->dropItem($sender->getPosition(), $leftover);
        }

        $sender->sendMessage(C::colorize(
            "&r&aZone wand armed for &f" . (string) ($zone["display"] ?? $zoneId) .
            "&a. Left click = pos1, right click = pos2."
        ));
    }

    private function handleSafeSet(Player $sender, array $args): void
    {
        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to edit zones."));
            return;
        }

        $zoneId = strtolower((string) ($args["zone"] ?? ""));
        $safeId = strtolower((string) ($args["point"] ?? ""));
        $point = strtolower((string) ($args["extra"] ?? ""));
        if ($zoneId === "" || $safeId === "" || !in_array($point, ["pos1", "pos2"], true)) {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/zones safeset <zone> <safe-id> <pos1|pos2>"));
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown zone &f{$zoneId}&c. Available: &f" . implode(", ", array_keys(ZoneManager::getZones()))));
            return;
        }

        if (!ZoneManager::setSafeAreaPoint($zoneId, $safeId, $sender->getPosition(), $point)) {
            $sender->sendMessage(C::colorize("&r&cFailed to update that safe zone boundary."));
            return;
        }

        $position = $sender->getPosition();
        $sender->sendMessage(C::colorize(
            "&r&aSet safe area &f{$safeId}&a in &f" . (string) ($zone["display"] ?? $zoneId) .
            "&a {$point} to &f" . $position->getWorld()->getFolderName() . " {$position->getFloorX()}, {$position->getFloorY()}, {$position->getFloorZ()}&a."
        ));
    }

    private function handleSafeWand(Player $sender, array $args): void
    {
        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to edit zones."));
            return;
        }

        $zoneId = strtolower((string) ($args["zone"] ?? ""));
        $safeId = strtolower((string) ($args["point"] ?? ""));
        if ($zoneId === "" || $safeId === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/zones safewand <zone> <safe-id>"));
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown zone &f{$zoneId}&c. Available: &f" . implode(", ", array_keys(ZoneManager::getZones()))));
            return;
        }

        $item = ZoneManager::createSafeAreaWand($zoneId, $safeId, C::clean((string) ($zone["display"] ?? $zoneId)));
        foreach ($sender->getInventory()->addItem($item) as $leftover) {
            $sender->getWorld()->dropItem($sender->getPosition(), $leftover);
        }

        $sender->sendMessage(C::colorize(
            "&r&aSafe zone wand armed for &f" . (string) ($zone["display"] ?? $zoneId) .
            "&a / &f{$safeId}&a. Left click = pos1, right click = pos2."
        ));
    }

    private function handleSafeReset(Player $sender, array $args): void
    {
        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to edit zones."));
            return;
        }

        $zoneId = strtolower((string) ($args["zone"] ?? ""));
        $safeId = strtolower((string) ($args["point"] ?? ""));
        if ($zoneId === "" || $safeId === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/zones safereset <zone> <safe-id>"));
            return;
        }

        if (!ZoneManager::resetSafeArea($zoneId, $safeId)) {
            $sender->sendMessage(C::colorize("&r&cFailed to reset that safe zone."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&aReset safe area &f{$safeId}&a for zone &f{$zoneId}&a."));
    }

    private function handleSpawner(Player $sender, array $args): void
    {
        if (!$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to create zone spawners."));
            return;
        }

        $zoneId = strtolower((string) ($args["zone"] ?? ""));
        if ($zoneId === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/zones spawner <zone>"));
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown zone &f{$zoneId}&c. Available: &f" . implode(", ", array_keys(ZoneManager::getZones()))));
            return;
        }

        $item = GlaciaSpawnerItem::createZoneSpawner($zoneId, C::clean((string) ($zone["display"] ?? $zoneId)));
        foreach ($sender->getInventory()->addItem($item) as $leftover) {
            $sender->getWorld()->dropItem($sender->getPosition(), $leftover);
        }

        $sender->sendMessage(C::colorize("&r&aGiven a zone spawner for &f" . (string) ($zone["display"] ?? $zoneId) . "&a."));
    }

    private function handleTrail(Player $sender, array $args): void
    {
        $zoneId = strtolower((string) ($args["zone"] ?? ""));
        if ($zoneId === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/zones trail <zone>"));
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown zone &f{$zoneId}&c. Available: &f" . implode(", ", array_keys(ZoneManager::getZones()))));
            return;
        }

        if (!ZoneManager::canPlayerAccess($sender, $zone)) {
            $sender->sendMessage(C::colorize("&r&cThat zone is still locked for you."));
            return;
        }

        $spawn = ZoneManager::getSpawnPosition($zone);
        if ($spawn === null) {
            $sender->sendMessage(C::colorize("&r&cThat zone does not have a saved spawn point yet."));
            return;
        }

        if ($spawn->getWorld() !== $sender->getWorld()) {
            $sender->sendMessage(C::colorize(
                "&r&eTrail guidance works once you're inside &f" . (string) ($zone["display"] ?? $zoneId) .
                "&e. Teleport there first, then run &f/zones trail {$zoneId}&e."
            ));
            return;
        }

        if (!CustomParticleEffectManager::startTrailTo($sender, $spawn, 14)) {
            $sender->sendMessage(C::colorize("&r&cCouldn't start the trail right now."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&bA guiding trail now points toward &f" . (string) ($zone["display"] ?? $zoneId) . "&b."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
