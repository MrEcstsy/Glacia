<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class NightVisionCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new PlayerArgument("player", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $target = $args["player"] ?? null;
        if (!$target instanceof Player) {
            if (!$sender instanceof Player) {
                $sender->sendMessage(C::colorize('&r&cConsole must specify a player: /nv <player>'));

                return;
            }
            $target = $sender;
        } elseif ($target !== $sender && !$sender->hasPermission('glacia.nv.other')) {
            $sender->sendMessage(C::colorize('&r&cYou cannot apply night vision to other players.'));

            return;
        }

        $effects = $target->getEffects();
        $nv = VanillaEffects::NIGHT_VISION();
        if ($effects->has($nv)) {
            $effects->remove($nv);
            $msg = '&r&7Night vision &coff&7.';
        } else {
            $effects->add(new EffectInstance($nv, 20 * 60 * 60, 0, false, false, null, false));
            $msg = '&r&7Night vision &aon&7 (1 hour — run again to toggle off).';
        }

        $target->sendMessage(C::colorize($msg));
        if ($sender !== $target) {
            $sender->sendMessage(C::colorize('&r&7Night vision toggled for &f' . $target->getName() . '&7.'));
        }
    }

    public function getPermission(): string
    {
        return 'glacia.nv';
    }
}
