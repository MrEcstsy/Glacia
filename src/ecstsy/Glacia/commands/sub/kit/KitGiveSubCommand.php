<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\kit;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\kits\KitRepository;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class KitGiveSubCommand extends BaseSubCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new PlayerArgument("player"));
        $this->registerArgument(1, new RawStringArgument("kit"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        /** @var Player $target */
        $target = $args["player"];
        $kitId = (string) $args["kit"];
        $kit = KitRepository::getKitById($kitId);

        if ($kit === null) {
            $sender->sendMessage(C::colorize("&r&cKit '$kitId' does not exist."));
            return;
        }

        if (!KitRepository::grantKit($target, $kit)) {
            $sender->sendMessage(C::colorize("&r&cFailed to give kit '$kitId' to {$target->getName()}."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&aGave &f" . ((string) $kit["display"]) . "&r&a to &f{$target->getName()}&r&a."));
        GlaciaUtils::logAdminAction("Give Kit", [
            "admin" => $sender->getName(),
            "target" => $target->getName(),
            "kit" => (string) $kit["id"]
        ]); 
    }

    public function getPermission(): string
    {
        return "glacia.kit.give";
    }
}
