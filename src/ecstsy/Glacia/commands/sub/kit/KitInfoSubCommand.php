<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\kit;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\kits\KitRepository;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class KitInfoSubCommand extends BaseSubCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("kit"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $kitId = (string) $args["kit"];
        $kit = KitRepository::getKitById($kitId);

        if ($kit === null) {
            $sender->sendMessage(C::colorize("&r&cKit '$kitId' does not exist."));
            return;
        }

        $fixedItems = count($kit["items"] ?? []);
        $randomRolls = (int) ($kit["random-rolls"] ?? 0);
        $permission = (string) ($kit["permission"] ?? "none");
        $cooldown = GeneralUtils::translateTime((int) ($kit["cooldown"] ?? 0));

        $sender->sendMessage(C::colorize("&r&bKit Info &7- &f" . ((string) $kit["display"])));
        $sender->sendMessage(C::colorize("&r&7Id: &f" . ((string) $kit["id"])));
        $sender->sendMessage(C::colorize("&r&7Permission: &f" . $permission));
        $sender->sendMessage(C::colorize("&r&7Cooldown: &f" . $cooldown));
        $sender->sendMessage(C::colorize("&r&7Slot: &f" . ((string) $kit["slot"])));
        $sender->sendMessage(C::colorize("&r&7Fixed Items: &f" . $fixedItems));
        $sender->sendMessage(C::colorize("&r&7Random Rolls: &f" . $randomRolls));
    }

    public function getPermission(): string
    {
        return "glacia.kit.info";
    }
}
