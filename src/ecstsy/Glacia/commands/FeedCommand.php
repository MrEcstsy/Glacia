<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FeedCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($sender);

        if ($session->isOnCooldown("commands.feed")) {
            $sender->sendMessage(GlaciaUtils::format("player.command.cooldown", ["time" => GeneralUtils::translateTime($session->getCooldown("commands.feed"))], "error"));
            return;
        }

        $sender->getHungerManager()->setFood(20);
        $sender->getHungerManager()->setSaturation(20.0);
        $sender->sendMessage(GlaciaUtils::format("feed.success", [], "success"));
        $session->addCooldown("commands.feed", 60); 
    }

    public function getPermission(): string {
        return "glacia.feed";
    }
}