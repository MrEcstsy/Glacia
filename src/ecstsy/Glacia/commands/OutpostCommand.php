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
                $type = (string) ($args["extra"] ?? "economy");
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost create <id> <type>"));
                    return;
                }
                $ok = $manager->createOutpost($id, $type);
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Created outpost &f{$id}&7 of type &f{$type}&7."
                    : "&r&cOutpost &f{$id} &calready exists or is invalid."
                ));
                return;

            case "setpoint":
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost setpoint <id>"));
                    return;
                }
                if (!$sender instanceof Player) {
                    $sender->sendMessage(C::colorize("&r&cThis action requires an in-game player position."));
                    return;
                }
                $ok = $manager->setOutpostPoint($id, $sender->getPosition());
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Set the point for outpost &f{$id}&7."
                    : "&r&cOutpost &f{$id} &ccould not be found."
                ));
                return;

            case "radius":
                $radius = isset($args["number"]) ? (int) $args["number"] : (int) ($args["extra"] ?? 0);
                if ($id === "" || $radius <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost radius <id> <radius>"));
                    return;
                }
                $ok = $manager->setOutpostRadius($id, $radius);
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Set the radius for &f{$id} &7to &f{$radius}&7."
                    : "&r&cOutpost &f{$id} &ccould not be found."
                ));
                return;

            case "time":
                $seconds = isset($args["number"]) ? (int) $args["number"] : (int) ($args["extra"] ?? 0);
                if ($id === "" || $seconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost time <id> <seconds>"));
                    return;
                }
                $ok = $manager->setOutpostCaptureTime($id, $seconds);
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Set the capture time for &f{$id} &7to &f{$seconds}s&7."
                    : "&r&cOutpost &f{$id} &ccould not be found."
                ));
                return;

            case "delete":
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /outpost delete <id>"));
                    return;
                }
                $ok = $manager->deleteOutpost($id);
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Deleted outpost &f{$id}&7."
                    : "&r&cOutpost &f{$id} &ccould not be found."
                ));
                return;

            case "list":
                $sender->sendMessage(C::colorize("&r&6Outposts"));
                foreach ($manager->getOutposts() as $outpostId => $outpost) {
                    $sender->sendMessage(C::colorize("&r&e{$outpostId} &7- &f" . (string) ($outpost["display"] ?? $outpostId) . " &8(" . (string) ($outpost["type"] ?? "unknown") . "&8)"));
                }
                return;
        }

        $sender->sendMessage(C::colorize("&r&6Outpost Setup"));
        $sender->sendMessage(C::colorize("&r&e/outpost create <id> <type>"));
        $sender->sendMessage(C::colorize("&r&e/outpost setpoint <id>"));
        $sender->sendMessage(C::colorize("&r&e/outpost radius <id> <radius>"));
        $sender->sendMessage(C::colorize("&r&e/outpost time <id> <seconds>"));
        $sender->sendMessage(C::colorize("&r&e/outpost delete <id>"));
        $sender->sendMessage(C::colorize("&r&e/outpost list"));
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
