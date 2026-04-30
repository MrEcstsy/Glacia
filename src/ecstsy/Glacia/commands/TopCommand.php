<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;

final class TopCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        if (Loader::getCombatManager()->isInCombat($sender)) {
            $sender->sendMessage(C::colorize("&r&cYou cannot use /top while in combat."));
            return;
        }

        $world = $sender->getWorld();
        $x = $sender->getPosition()->getFloorX();
        $z = $sender->getPosition()->getFloorZ();
        $y = $world->getHighestBlockAt($x, $z) + 1;

        // Ensure a safe 2-block tall teleport spot.
        for ($i = 0; $i < 16; $i++) {
            if (
                !$world->getBlockAt($x, $y, $z)->isSolid() &&
                !$world->getBlockAt($x, $y + 1, $z)->isSolid()
            ) {
                break;
            }
            $y++;
        }

        $sender->teleport(new Position($x + 0.5, $y, $z + 0.5, $world));
        $sender->setMotion(new Vector3(0.0, 0.0, 0.0));
        $sender->resetFallDistance();
        $sender->sendMessage(C::colorize("&r&aTeleported to the top."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
