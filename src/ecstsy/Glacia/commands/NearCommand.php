<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\staff\StaffModeManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class NearCommand extends BaseCommand {

    public function prepare() : void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $radius = max(1, (int) ($config?->get("near-radius", 100) ?? 100));
        $nearby = [];

        foreach ($sender->getWorld()->getPlayers() as $player) {
            if ($player === $sender) {
                continue;
            }
            if (PetManager::isHiddenFromNear($player)) {
                continue;
            }
            if (StaffModeManager::shouldHideFromPlayerList($player) && !StaffModeManager::canViewerSeeHiddenStaff($sender)) {
                continue;
            }

            $distance = $sender->getPosition()->distance($player->getPosition());
            if ($distance <= $radius) {
                $nearby[$player->getName()] = $distance;
            }
        }

        if ($nearby === []) {
            $sender->sendMessage(C::colorize("&r&cNo players were found within &f{$radius}&r&c blocks."));
            return;
        }

        asort($nearby);
        $sender->sendMessage(C::colorize("&r&bNearby Players &7(&f{$radius}&7 block radius)&b:"));
        foreach ($nearby as $name => $distance) {
            $sender->sendMessage(C::colorize("&r&7- &f{$name} &7(" . number_format($distance, 1) . "m)"));
        }
    }

    public function getPermission() : string
    {
        return "glacia.command.near";
    }
}
