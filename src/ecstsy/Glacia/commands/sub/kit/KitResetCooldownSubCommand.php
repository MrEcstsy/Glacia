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

final class KitResetCooldownSubCommand extends BaseSubCommand {

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

        if (!KitRepository::clearKitCooldown($target, $kit)) {
            $sender->sendMessage(C::colorize("&r&cFailed to reset {$target->getName()}'s cooldown for '$kitId'."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&aReset &f{$target->getName()}'s&r&a cooldown for &f" . ((string) $kit["display"]) . "&r&a."));
        $target->sendMessage(C::colorize("&r&aYour cooldown for &f" . ((string) $kit["display"]) . "&r&a has been reset."));

        GlaciaUtils::logAdminAction("Reset Kit Cooldown", [
            "admin" => $sender->getName(),
            "target" => $target->getName(),
            "kit" => (string) $kit["id"]
        ]);
    }

    public function getPermission(): string
    {
        return "glacia.kit.resetcooldown";
    }
}
