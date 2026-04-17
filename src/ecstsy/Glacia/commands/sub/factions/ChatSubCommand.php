<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\ChatTypes;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ChatSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("chat-type"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) return;

        $raw = strtolower($args["chat-type"]);

        $session = Loader::getPlayerManager()->getSession($sender);
        $faction = $session->getFaction();

        switch ($raw) {
            case "a":
            case "ally":
                if ($faction === null) {
                    $sender->sendMessage(GlaciaUtils::format("player.no_faction"));
                    return;
                }
                $type = ChatTypes::ALLY;
                $label = "Ally";
                break;

            case "f":
            case "faction":
                if ($faction === null) {
                    $sender->sendMessage(GlaciaUtils::format("player.no_faction"));
                    return;
                }
                $type = ChatTypes::FACTION;
                $label = "Faction";
                break;

            case "all":
            case "public":
            case "p":
                $type = ChatTypes::ALL;
                $label = "Public";
                break;

            default:
                $sender->sendMessage(C::colorize("&cUsage: /{$aliasUsed} <a|ally|f|faction|all|public|p>"));
                return;
        }
        
        $session->setCurrentChat($type);
        $sender->sendMessage(GlaciaUtils::format("faction.chat_set", ['chatType' => $label], "success"));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}