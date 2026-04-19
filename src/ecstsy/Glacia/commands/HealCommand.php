<?php
declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;


final class HealCommand extends BaseCommand {
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("name", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if(!$sender instanceof Player){
            return;
        }

        $target = isset($args["name"]) ? PlayerUtils::getPlayerByPrefix($args["name"]) : $sender;

        if($target === null){
            $sender->sendMessage(C::colorize("&cPlayer not found."));
            return;
        }

        $isSelf = $target === $sender;

        $session = PlayerManager::getInstance()->getSession($sender);
        if($isSelf && $session->isOnCooldown("commands.heal")){
            $remaining = $session->getCooldown("commands.heal");
            $sender->sendMessage(GlaciaUtils::format("player.command.cooldown", ["time" => GeneralUtils::translateTime($remaining)], "error"));
            return;
        }

        if($target->getHealth() >= $target->getMaxHealth()){
            $sender->sendMessage(C::colorize("&c" . ($isSelf ? "You are" : $target->getName() . " is") . " already at full health."));
            return;
        }

        $target->setHealth($target->getMaxHealth());

        if($isSelf){
            $sender->sendMessage(GlaciaUtils::format("heal.success", [], "success"));
            $session->addCooldown("commands.heal", 60);
        } else {
            $sender->sendMessage(GlaciaUtils::format("heal.success.target", ["player" => $target->getName()], "success"));
            $target->sendMessage(GlaciaUtils::format("heal.success.other", ["player" => $sender->getName()], "success"));
        }
    }

    public function getPermission(): string {
        return "glacia.heal";
    }
}
