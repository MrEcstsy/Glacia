<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\items\GlaciaSpawnerItem;
use ecstsy\Glacia\server\zones\ZoneManager;
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
        if ($action === "spawner") {
            $this->handleSpawner($sender, $args);
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
            $position->getWorld()->getFolderName() . " {$position->getFloorX()}, {$position->getFloorZ()}&a. Y is ignored."
        ));

        $note = ZoneManager::getLastBoundaryEditNote();
        if ($note !== null) {
            $sender->sendMessage(C::colorize($note));
        }
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

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
