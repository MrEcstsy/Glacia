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

final class RemoveHomeCommand extends BaseCommand {

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

        $homeName = isset($args["home"]) ? $args["home"] : null;

        if ($homeName !== null) {
            $home = Loader::getHomeManager()->getPlayerHome($sender->getUniqueId(), $homeName);
            if ($home !== null) { // Probably redundant 
                Loader::getHomeManager()->deleteHome($home);
                $sender->sendMessage(C::colorize("&cSuccessfully removed home '{$homeName}'!"));
            } else {
                $sender->sendMessage(C::colorize("&cHome '{$homeName}' does not exist."));
            }
        } else {
            $sender->sendMessage(C::colorize("&cHome '{$homeName}' does not exist."));
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
