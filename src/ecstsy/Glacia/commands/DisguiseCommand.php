<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\disguises\DisguiseManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class DisguiseCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("disguise", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&cThis command can only be used in-game."));
            return;
        }

        $input = strtolower(trim((string) ($args["disguise"] ?? "")));
        $manager = Loader::getDisguiseManager();

        if ($input === "" || $input === "list") {
            GlaciaUtils::openDisguiseScreen($sender);
            return;
        }

        if (in_array($input, ["off", "none", "clear", "remove", "undisguise"], true)) {
            if (!$manager->isDisguised($sender)) {
                $sender->sendMessage(C::colorize("&r&7You are not currently disguised."));
                return;
            }

            $manager->clear($sender);
            $sender->sendMessage(C::colorize("&r&aYour disguise has faded."));
            return;
        }

        if (!DisguiseManager::hasDisguise($input)) {
            $sender->sendMessage(C::colorize("&r&cUnknown disguise. Try &f/disguise list&r&c."));
            return;
        }

        $permission = DisguiseManager::getPermission($input);
        if (!$sender->hasPermission($permission) && !$sender->hasPermission("glacia.admin")) {
            $sender->sendMessage(C::colorize("&r&cYou have not unlocked that disguise yet."));
            return;
        }

        $manager->apply($sender, $input);
        $sender->sendMessage(C::colorize("&r&dDisguise active: &f" . DisguiseManager::getDisplayName($input)));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
