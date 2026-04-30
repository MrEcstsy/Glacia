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

final class KothCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("id", true));
        $this->registerArgument(2, new IntegerArgument("seconds", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $manager = Loader::getCaptureManager();
        $action = strtolower((string) ($args["action"] ?? "help"));
        $id = strtolower((string) ($args["id"] ?? ""));

        switch ($action) {
            case "create":
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /koth create <id>"));
                    return;
                }
                $ok = $manager->createKoth($id);
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Created KOTH &f{$id}&7."
                    : "&r&cKOTH &f{$id} &calready exists or is invalid."
                ));
                return;

            case "pos1":
            case "pos2":
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /koth {$action} <id>"));
                    return;
                }
                if (!$sender instanceof Player) {
                    $sender->sendMessage(C::colorize("&r&cThis action requires an in-game player position."));
                    return;
                }
                $ok = $manager->setKothPosition($id, $action, $sender->getPosition());
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Set {$action} for KOTH &f{$id}&7."
                    : "&r&cKOTH &f{$id} &ccould not be found."
                ));
                return;

            case "spawn":
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /koth spawn <id>"));
                    return;
                }
                if (!$sender instanceof Player) {
                    $sender->sendMessage(C::colorize("&r&cThis action requires an in-game player position."));
                    return;
                }
                $ok = $manager->setKothSpawn($id, $sender->getPosition());
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Set spawn for KOTH &f{$id}&7."
                    : "&r&cKOTH &f{$id} &ccould not be found."
                ));
                return;

            case "time":
                $seconds = (int) ($args["seconds"] ?? 0);
                if ($id === "" || $seconds <= 0) {
                    $sender->sendMessage(C::colorize("&r&cUsage: /koth time <id> <seconds>"));
                    return;
                }
                $ok = $manager->setKothCaptureTime($id, $seconds);
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Set capture time for KOTH &f{$id} &7to &f{$seconds}s&7."
                    : "&r&cKOTH &f{$id} &ccould not be found."
                ));
                return;

            case "start":
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /koth start <id>"));
                    return;
                }
                $ok = $manager->startKoth($id);
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Started KOTH &f{$id}&7."
                    : "&r&cKOTH &f{$id} &cis not fully configured."
                ));
                return;

            case "stop":
                $manager->stopKoth();
                $sender->sendMessage(C::colorize("&r&a✔ &r&7Stopped the active KOTH."));
                return;

            case "delete":
                if ($id === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /koth delete <id>"));
                    return;
                }
                $ok = $manager->deleteKoth($id);
                $sender->sendMessage(C::colorize($ok
                    ? "&r&a✔ &r&7Deleted KOTH &f{$id}&7."
                    : "&r&cKOTH &f{$id} &ccould not be found."
                ));
                return;

            case "list":
                $sender->sendMessage(C::colorize("&r&6KOTHs"));
                foreach ($manager->getKoths() as $kothId => $koth) {
                    $sender->sendMessage(C::colorize("&r&e{$kothId} &7- &f" . (string) ($koth["display"] ?? $kothId)));
                }
                return;
        }

        $sender->sendMessage(C::colorize("&r&6KOTH Setup"));
        $sender->sendMessage(C::colorize("&r&e/koth create <id>"));
        $sender->sendMessage(C::colorize("&r&e/koth pos1 <id>"));
        $sender->sendMessage(C::colorize("&r&e/koth pos2 <id>"));
        $sender->sendMessage(C::colorize("&r&e/koth spawn <id>"));
        $sender->sendMessage(C::colorize("&r&e/koth time <id> <seconds>"));
        $sender->sendMessage(C::colorize("&r&e/koth start <id>"));
        $sender->sendMessage(C::colorize("&r&e/koth stop"));
        $sender->sendMessage(C::colorize("&r&e/koth delete <id>"));
        $sender->sendMessage(C::colorize("&r&e/koth list"));
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
