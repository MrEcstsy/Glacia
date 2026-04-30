<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\staff\StaffModeManager;
use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use function array_filter;
use function array_map;
use function count;
use function implode;
use function sort;
use const SORT_STRING;

final class GlaciaListCommand extends BaseCommand {

    public function prepare() : void {
        $this->setPermission(DefaultPermissionNames::COMMAND_LIST);
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
        $playerNames = array_map(static function (Player $player) : string {
            return $player->getName();
        }, array_filter($sender->getServer()->getOnlinePlayers(), function (Player $player) use ($sender) : bool {
            if (StaffModeManager::shouldHideFromPlayerList($player)) {
                if (!$sender instanceof Player || !StaffModeManager::canViewerSeeHiddenStaff($sender)) {
                    return false;
                }
            }
            return !$sender instanceof Player || $sender->canSee($player);
        }));
        sort($playerNames, SORT_STRING);

        $sender->sendMessage(KnownTranslationFactory::commands_players_list((string) count($playerNames), (string) $sender->getServer()->getMaxPlayers()));
        $sender->sendMessage(implode(", ", $playerNames));
    }
}
