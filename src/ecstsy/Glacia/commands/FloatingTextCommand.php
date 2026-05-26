<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\floatingtext\FloatingTextManager;
use ecstsy\Glacia\server\floatingtext\FloatingTextRemoveMode;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;

final class FloatingTextCommand extends BaseCommand
{
    protected function prepare() : void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("arg1", true));
        $this->registerArgument(2, new RawStringArgument("arg2", true));
        $this->registerArgument(3, new RawStringArgument("arg3", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void
    {
        $action = strtolower((string) ($args["action"] ?? "help"));
        $arg1 = strtolower(trim((string) ($args["arg1"] ?? "")));
        $arg2 = strtolower(trim((string) ($args["arg2"] ?? "")));
        $arg3 = trim((string) ($args["arg3"] ?? ""));

        switch ($action) {
            case "templates":
            case "template":
                $sender->sendMessage(C::colorize("&r&bFloatingText templates: &f" . implode(", ", FloatingTextManager::getTemplateIds())));
                return;

            case "list":
                $sender->sendMessage(C::colorize("&r&bFloatingText instances: &f" . implode(", ", FloatingTextManager::getInstanceIds())));
                return;

            case "refresh":
            case "reload":
                FloatingTextManager::loadFromConfig();
                $sender->sendMessage(C::colorize("&r&aFloating texts reloaded from config."));
                return;

            case "remove":
            case "delete":
                if ($arg1 === "") {
                    if (!$sender instanceof Player) {
                        $sender->sendMessage(C::colorize("&r&cUsage: /floatingtext remove <id>"));
                        return;
                    }

                    FloatingTextRemoveMode::enable($sender);
                    $sender->sendMessage(C::colorize("&r&aFloating text remove mode enabled. &7Punch a floating text to delete it (&f2 minutes&7)."));
                    return;
                }
                if (!FloatingTextManager::removeInstance($arg1)) {
                    $sender->sendMessage(C::colorize("&r&cUnknown floating text id: &f{$arg1}"));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aRemoved floating text &f{$arg1}&a."));
                return;

            case "tp":
            case "teleport":
                if (!$sender instanceof Player) {
                    $sender->sendMessage(C::colorize("&r&cRun this in-game."));
                    return;
                }
                $instance = FloatingTextManager::getInstance($arg1);
                if ($instance === null) {
                    $sender->sendMessage(C::colorize("&r&cUnknown floating text id: &f{$arg1}"));
                    return;
                }
                $worldName = (string) ($instance["world"] ?? "");
                $worldManager = Server::getInstance()->getWorldManager();
                $world = $worldManager->getWorldByName($worldName);
                if ($world === null && $worldName !== "" && $worldManager->loadWorld($worldName)) {
                    $world = $worldManager->getWorldByName($worldName);
                }

                if ($world === null) {
                    $sender->sendMessage(C::colorize("&r&cWorld for &f{$arg1} &cis not loaded."));
                    return;
                }
                $sender->teleport(new Position(
                    (float) ($instance["x"] ?? 0.0),
                    (float) ($instance["y"] ?? 0.0),
                    (float) ($instance["z"] ?? 0.0),
                    $world
                ));
                $sender->sendMessage(C::colorize("&r&aTeleported to &f{$arg1}&a."));
                return;

            case "spawn":
            case "create":
                if (!$sender instanceof Player) {
                    $sender->sendMessage(C::colorize("&r&cRun this in-game."));
                    return;
                }
                if ($arg1 === "" || $arg2 === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /floatingtext spawn <id> <template> [yOffset]"));
                    return;
                }
                if (!FloatingTextManager::hasTemplate($arg2)) {
                    $sender->sendMessage(C::colorize("&r&cUnknown template: &f{$arg2}"));
                    return;
                }
                $offset = is_numeric($arg3) ? (float) $arg3 : 2.0;
                $pos = $sender->getPosition();
                if (!FloatingTextManager::upsertInstance(
                    $arg1,
                    $arg2,
                    $pos->getWorld()->getFolderName(),
                    $pos->x,
                    $pos->y + $offset,
                    $pos->z
                )) {
                    $sender->sendMessage(C::colorize("&r&cFailed to create floating text. Check the template id."));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aSpawned floating text &f{$arg1}&a using template &f{$arg2}&a."));
                return;

            case "move":
                if (!$sender instanceof Player) {
                    $sender->sendMessage(C::colorize("&r&cRun this in-game."));
                    return;
                }
                if ($arg1 === "") {
                    $sender->sendMessage(C::colorize("&r&cUsage: /floatingtext move <id> [yOffset]"));
                    return;
                }
                $offset = is_numeric($arg2) ? (float) $arg2 : 2.0;
                $pos = $sender->getPosition();
                if (!FloatingTextManager::moveInstance($arg1, $pos->getWorld()->getFolderName(), $pos->x, $pos->y + $offset, $pos->z)) {
                    $sender->sendMessage(C::colorize("&r&cUnknown floating text id: &f{$arg1}"));
                    return;
                }
                $sender->sendMessage(C::colorize("&r&aMoved floating text &f{$arg1}&a to your position."));
                return;
        }

        $sender->sendMessage(C::colorize("&r&cUsage: /floatingtext <templates|list|spawn|move|remove [id]|tp|refresh>"));
    }

    public function getPermission() : string
    {
        return "glacia.admin";
    }
}
