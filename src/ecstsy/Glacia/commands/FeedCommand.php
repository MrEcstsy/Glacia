<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FeedCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("name", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $target = isset($args["name"]) ? PlayerUtils::getPlayerByPrefix((string) $args["name"]) : ($sender instanceof Player ? $sender : null);
        if ($target === null) {
            $sender->sendMessage(C::colorize("&r&cUsage: /feed [player]"));
            return;
        }

        if (!$sender instanceof Player) {
            $target->getHungerManager()->setFood(20);
            $target->getHungerManager()->setSaturation(20.0);
            $sender->sendMessage(C::colorize("&r&aFed &f" . $target->getName() . "&a."));
            return;
        }

        $isSelf = $target === $sender;
        $session = PlayerManager::getInstance()->getSession($sender);

        if ($isSelf && $session->isOnCooldown("commands.feed")) {
            $sender->sendMessage(GlaciaUtils::format("player.command.cooldown", ["time" => GeneralUtils::translateTime($session->getCooldown("commands.feed"))], "error"));
            return;
        }

        $target->getHungerManager()->setFood(20);
        $target->getHungerManager()->setSaturation(20.0);

        if ($isSelf) {
            $sender->sendMessage(GlaciaUtils::format("feed.success", [], "success"));
            $session->addCooldown("commands.feed", 60);
            return;
        }

        $sender->sendMessage(C::colorize("&r&aFed &f" . $target->getName() . "&a."));
        $target->sendMessage(C::colorize("&r&aYou were fed by &f" . $sender->getName() . "&a."));
    }

    public function getPermission(): string {
        return "glacia.feed";
    }
}