<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class HomesCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("home", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(GlaciaUtils::format("general.ingame-only", [], "error"));
            return;
        }

        $homes = Loader::getHomeManager()->getHomeList($sender->getUniqueId());

        if (empty($homes)) {
            $sender->sendMessage(C::colorize("&cYou have no homes."));
            return;
        } else {
            $sender->sendMessage(C::colorize("&6Your homes:"));
            foreach ($homes as $home) {
                $position = $home->getPosition();
                if ($position !== null) {
                    $sender->sendMessage(C::colorize("&a- " . $home->getName() . ": " . $position->getFloorX() . ", " . $position->getFloorY() . ", " . $position->getFloorZ() . " (" . $position->getWorld()->getFolderName() . ")"));
                    continue;
                }

                $sender->sendMessage(C::colorize("&a- " . $home->getName() . ": " . $home->x . ", " . $home->y . ", " . $home->z . "&c (world unavailable: " . $home->world_name . ")"));
            }
        }

        $homeName = isset($args["home"]) ? $args["home"] : null;

        if ($homeName !== null) {
            $home = Loader::getHomeManager()->getPlayerHome($sender->getUniqueId(), $homeName);
            if ($home !== null) {
                if ($home->teleport($sender)) {
                    $sender->sendMessage(C::colorize("&aTeleported to home '{$homeName}'."));
                } else {
                    $sender->sendMessage(C::colorize("&cHome '{$homeName}' could not be loaded right now."));
                }
            } else {
                $sender->sendMessage(C::colorize("&cHome '{$homeName}' does not exist."));
            }
        } 
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
