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

final class CreateHomeCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("home", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(GlaciaUtils::format("general.ingame-only"));
            return;
        }

        $homeName = isset($args["home"]) ? $args["home"] : null;

        Loader::getHomeManager()->createHome($sender, $homeName);

        $sender->sendMessage(GlaciaUtils::format("home.created", ["home" => $homeName], "success"));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}