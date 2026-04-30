<?php
declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class DescriptionSubCommand extends BaseSubCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new TextArgument("description", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $sess = Loader::getPlayerManager()->getSession($sender);
        $fac  = $sess->getFaction();
        if ($fac === null || !in_array($sess->getFactionRole(), ["leader","officer"], true)) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.permission_denied"));
            return;
        }

        $newDesc = $args["description"];
        $descError = GlaciaUtils::validateFactionDescription($newDesc);
        if ($descError !== null) {
            $sender->sendMessage(C::colorize("&r&c" . $descError));
            return;
        }

        $fac->setDescription($newDesc);
        $sender->sendMessage(GlaciaUtils::format("faction.status.description_set", ["description" => $newDesc], "success"));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
