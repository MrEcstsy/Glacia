<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class RenameSubCommand extends BaseSubCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("name", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $sess = Loader::getPlayerManager()->getSession($sender);
        $fac = $sess->getFaction();

        if (!$fac || $sess->getFactionRole() !== "leader") {
            $sender->sendMessage(GlaciaUtils::format("faction.error.permission_denied"));
            return;
        }

        $new = $args["name"];
        $result = GlaciaUtils::validateName($new, true, $fac->getFactionId());
        if ($result !== null) {
            [$filteredName, $error] = $result;
            $sender->sendMessage(C::colorize("&c" . $error));
            return;
        }

        $filteredName = preg_replace("/[^A-Za-z0-9]/", "", $new); 

        $fac->setName($filteredName);
        $sender->sendMessage(C::colorize("&r&l&a(✓) &r&aYou renamed your faction to &2" . $filteredName . "&a."));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
