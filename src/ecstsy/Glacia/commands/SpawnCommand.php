<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class SpawnCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("target", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $input = trim((string) ($args["target"] ?? ""));

        if ($input === "" || strtolower($input) === "self") {
            if (!$sender instanceof Player) {
                $sender->sendMessage(C::colorize("&r&cRun this command in-game or use &f/spawn <player>&c."));
                return;
            }

            $spawn = $this->getServerSpawn();
            if ($spawn === null) {
                $sender->sendMessage(C::colorize("&r&cSpawn world is not available."));
                return;
            }

            ZoneManager::allowNextBoundaryCrossing($sender, 15);
            GlaciaUtils::timedTeleport($sender, $spawn);
            return;
        }

        if (strtolower($input) === "set") {
            if (!$sender instanceof Player) {
                $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
                return;
            }

            if (
                !$sender->hasPermission("glacia.command.world.setspawn") &&
                !$sender->hasPermission("glacia.world.setspawn") &&
                !$sender->hasPermission("glacia.admin")
            ) {
                $sender->sendMessage(C::colorize("&r&cYou do not have permission to set spawn."));
                return;
            }

            $world = $sender->getWorld();
            $ok = Loader::getWorldManagerService()->setWorldSpawn(
                $world->getFolderName(),
                $sender->getPosition(),
                $sender->getName()
            );

            $sender->sendMessage(C::colorize(
                $ok
                    ? "&r&aSpawn set for &f" . $world->getFolderName() . "&a."
                    : "&r&cFailed to set the world spawn."
            ));
            return;
        }

        if (
            !$sender->hasPermission("glacia.admin") &&
            !$sender->hasPermission("glacia.command.world.teleport")
        ) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to force players to spawn."));
            return;
        }

        $target = PlayerUtils::getPlayerByPrefix($input);
        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cPlayer not found."));
            return;
        }

        $spawn = $this->getServerSpawn();
        if ($spawn === null) {
            $sender->sendMessage(C::colorize("&r&cSpawn world is not available."));
            return;
        }

        ZoneManager::allowNextBoundaryCrossing($target, 15);
        $target->teleport($spawn);

        $sender->sendMessage(C::colorize("&r&aSent &f" . $target->getName() . " &ato spawn."));
        if (!$sender instanceof Player || $sender->getName() !== $target->getName()) {
            $target->sendMessage(C::colorize("&r&aYou were sent to spawn" . ($sender instanceof Player ? " by &f" . $sender->getName() : "") . "&a."));
        }
    }

    private function getServerSpawn(): ?\pocketmine\world\Position
    {
        $worldManager = Server::getInstance()->getWorldManager();
        $world = $worldManager->getDefaultWorld();
        if ($world === null && $worldManager->loadWorld("world")) {
            $world = $worldManager->getWorldByName("world");
        }

        return $world?->getSpawnLocation();
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
