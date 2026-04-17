<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\commands\FactionCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class HelpSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!($sender instanceof Player)) {
            return;
        }

        $page = isset($args["page"]) && $args["page"] > 0 ? (int)$args["page"] : 1;
        $parent = $this->getParent();

        if ($parent instanceof FactionCommand) {
            $parentAlias = $parent->getName();
            $parent->onRun($sender, $parentAlias, ["page" => $page]);
        }
    }


    public function getPermission(): string {
        return "glacia.default";
    }
}
