<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class OutpostCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("id", true));
        $this->registerArgument(2, new RawStringArgument("extra", true));
        $this->registerArgument(3, new IntegerArgument("number", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $manager = Loader::getCaptureManager();
        $action = strtolower((string) ($args["action"] ?? "help"));
        $id = strtolower((string) ($args["id"] ?? ""));

        switch ($action) {
            case "create":
                $type = strtolower(trim((string) ($args["extra"] ?? "economy")));
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost create <id> <economy|experience>"));
                    return;
                }
                if (!$manager->createOutpost($id, $type)) {
                    $sender->sendMessage(C::colorize("&r&cOutpost &f{$id} &calready exists or is invalid."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aCreated outpost &f{$id}&a of type &f{$type}&a."));
                $sender->sendMessage(C::colorize("&r&7Next: stand in the middle and run &f/outpost setpoint {$id}&7."));
                $sender->sendMessage(C::colorize("&r&7Then use &f/outpost radius {$id} <blocks> &7and &f/outpost time {$id} <seconds>&7."));
                return;

            case "setpoint":
            case "center":
            case "point":
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost setpoint <id>"));
                    return;
                }
                if (!$sender instanceof Player) {
                    $sender->sendMessage(C::colorize("&r&cThis action requires an in-game player position."));
                    return;
                }
                if (!$manager->setOutpostPoint($id, $sender->getPosition())) {
                    $sender->sendMessage(C::colorize("&r&cOutpost &f{$id} &ccould not be found."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aSet the capture center for outpost &f{$id}&a."));
                $sender->sendMessage(C::colorize("&r&7This point is the middle of the capture circle and the hologram location."));
                return;

            case "radius":
            case "range":
                $radius = isset($args["number"]) ? (int) $args["number"] : (int) ($args["extra"] ?? 0);
                if ($id === "" || $radius <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost radius <id> <radius>"));
                    return;
                }
                if (!$manager->setOutpostRadius($id, $radius)) {
                    $sender->sendMessage(C::colorize("&r&cOutpost &f{$id} &ccould not be found."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aSet the capture radius for &f{$id} &ato &f{$radius} &ablocks."));
                $sender->sendMessage(C::colorize("&r&7Faction members have to stand inside that circle around the center point."));
                return;

            case "time":
            case "capturetime":
                $seconds = isset($args["number"]) ? (int) $args["number"] : (int) ($args["extra"] ?? 0);
                if ($id === "" || $seconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost time <id> <seconds>"));
                    return;
                }
                if (!$manager->setOutpostCaptureTime($id, $seconds)) {
                    $sender->sendMessage(C::colorize("&r&cOutpost &f{$id} &ccould not be found."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aSet the capture time for &f{$id} &ato &f{$seconds}s&a."));
                return;

            case "info":
            case "status":
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost info <id>"));
                    return;
                }
                $outpost = $manager->getOutpost($id);
                if ($outpost === null) {
                    $sender->sendMessage(C::colorize("&r&cOutpost &f{$id} &ccould not be found."));
                    return;
                }
                $world = trim((string) ($outpost["world"] ?? ""));
                $ownerFactionId = (string) ($outpost["owner-faction"] ?? "");
                $ownerName = $ownerFactionId !== "" ? Loader::getFactionManager()->getFaction($ownerFactionId)?->getName() : null;
                $sender->sendMessage(C::colorize("&r&6Outpost &f{$id}"));
                $sender->sendMessage(C::colorize("&r&7Type: &f" . (string) ($outpost["type"] ?? "economy")));
                $sender->sendMessage(C::colorize("&r&7Center: &f" . ($world !== "" ? "{$world} " : "unset ") . (int) ($outpost["x"] ?? 0) . ", " . (int) ($outpost["y"] ?? 0) . ", " . (int) ($outpost["z"] ?? 0)));
                $sender->sendMessage(C::colorize("&r&7Radius: &f" . (int) ($outpost["radius"] ?? 0) . " &8| &7Capture Time: &f" . (int) ($outpost["capture-time"] ?? 0) . "s"));
                $sender->sendMessage(C::colorize("&r&7Owner: &f" . ($ownerName ?? "None")));
                $sender->sendMessage(C::colorize("&r&7Buff: &fx" . number_format((float) ($outpost["benefit-multiplier"] ?? 1.25), 2)));
                return;

            case "types":
                $sender->sendMessage(C::colorize("&r&6Outpost Types"));
                $sender->sendMessage(C::colorize("&r&eEconomy &7- boosts sell income for the owning faction."));
                $sender->sendMessage(C::colorize("&r&eExperience &7- boosts mob level XP for the owning faction."));
                return;

            case "delete":
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost delete <id>"));
                    return;
                }
                if (!$manager->deleteOutpost($id)) {
                    $sender->sendMessage(C::colorize("&r&cOutpost &f{$id} &ccould not be found."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aDeleted outpost &f{$id}&a."));
                return;

            case "list":
                $outposts = $manager->getOutposts();
                if ($outposts === []) {
                    $sender->sendMessage(C::colorize("&r&cNo outposts are configured."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&6Outposts"));
                foreach ($outposts as $outpostId => $outpost) {
                    $world = trim((string) ($outpost["world"] ?? ""));
                    $radius = (int) ($outpost["radius"] ?? 0);
                    $capture = (int) ($outpost["capture-time"] ?? 0);
                    $sender->sendMessage(C::colorize("&r&e{$outpostId} &7- &f" . (string) ($outpost["display"] ?? $outpostId) . " &8(" . (string) ($outpost["type"] ?? "unknown") . "&8) &7[" . ($world !== "" ? "{$world}, r={$radius}, {$capture}s" : "unset") . "]"));
                }
                return;
        }

        $sender->sendMessage(C::colorize("&r&6Outpost Setup"));
        $sender->sendMessage(C::colorize("&r&e1. /outpost create <id> <economy|experience>"));
        $sender->sendMessage(C::colorize("&r&e2. Stand in the middle and do /outpost setpoint <id>"));
        $sender->sendMessage(C::colorize("&r&e3. /outpost radius <id> <blocks>"));
        $sender->sendMessage(C::colorize("&r&e4. /outpost time <id> <seconds>"));
        $sender->sendMessage(C::colorize("&r&e5. /outpost info <id>"));
        $sender->sendMessage(C::colorize("&r&7Radius = the capture circle around the center point."));
        $sender->sendMessage(C::colorize("&r&7If one faction stands inside alone long enough, they capture it and get the type buff."));
        $sender->sendMessage(C::colorize("&r&eOther: /outpost list, /outpost types, /outpost delete <id>"));
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
