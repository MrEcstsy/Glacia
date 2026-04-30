<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\FactionPermissions;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class CreateSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("name"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if($session === null) return;

        if($session->getFactionId() !== null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.already_in_faction"));
            return;
        }

        $name = $args["name"];

        $result = GlaciaUtils::validateName($name);
        if ($result !== null) {
            [$filteredName, $error] = $result;
            $sender->sendMessage(C::RED . $error);
            return;
        }

        $filteredName = preg_replace("/[^A-Za-z0-9]/", "", $name); 

        $factionId = uniqid("", true);
        $f = Loader::getFactionManager()->createFaction($factionId, $filteredName, "Default faction description :(", $sender->getUniqueId()->toString(), $sender->getName());
        if ($f === null) {
            $sender->sendMessage(C::colorize("&r&cCould not create that faction (name may have been taken). Try again."));
            return;
        }

        $session->joinFaction($factionId, "leader");
        Loader::getDatabase()->waitAll();
        $f->refreshScoreboards();

        $factionName = $f->getName();

        GlaciaUtils::logFactionAudit("Create", [
            "founder" => $sender->getName(),
            "faction" => $factionName,
            "faction_id" => $factionId,
        ]);

        $sender->sendMessage(C::colorize("&r&l&3    FACTION CREATED"));
        $sender->sendMessage(C::colorize("&r&7You have founded &b{$factionName}&7."));
        $sender->sendMessage(C::colorize(""));
        $sender->sendMessage(C::colorize("&r&f&o Use &b/f desc &f<text> &7to set your description."));
        $sender->sendMessage(C::colorize("&r&f&o Invite players with &b/f invite &f<player>&7."));
        $sender->sendMessage(C::colorize("&r&f&o Manage roles with &b/f promote &7and &b/f demote&7.\n"));

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if ($player->getName() === $sender->getName()) {
                continue;
            }
            $player->sendMessage(C::colorize("&r&l&3    FACTION CREATED"));
            $player->sendMessage(C::colorize("&r&7A new force has emerged: &b{$factionName}&7."));
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}