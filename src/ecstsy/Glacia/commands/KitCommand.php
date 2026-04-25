<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\kit\KitGiveSubCommand;
use ecstsy\Glacia\commands\sub\kit\KitInfoSubCommand;
use ecstsy\Glacia\commands\sub\kit\KitResetCooldownSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\kits\KitFormScreen;
use ecstsy\Glacia\utils\screens\kits\KitScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class KitCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerSubCommand(new KitGiveSubCommand(Loader::getInstance(), "give", "Give a kit to a player"));
        $this->registerSubCommand(new KitResetCooldownSubCommand(Loader::getInstance(), "resetcooldown", "Reset a player's kit cooldown"));
        $this->registerSubCommand(new KitInfoSubCommand(Loader::getInstance(), "info", "View kit information"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cUsage: /kit <give|resetcooldown|info>"));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session !== null && $session->getSetting("chest_inventories") === false) {
            KitFormScreen::displayRankKits($sender);
            return;
        }

        KitScreen::display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
