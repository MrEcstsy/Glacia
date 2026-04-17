<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\exp;

use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use ecstsy\Glacia\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItemFactory as ItemsGlaciaItemFactory;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\utils\TextFormat as C;

final class XpBottleAllSubCommand extends BaseSubCommand {

    protected function prepare(): void {
        $this->setPermission("glacia.default");
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if(!$sender instanceof Player) return;

        $xp = $sender->getXpManager()->getCurrentTotalXp();

        if ($xp <= 0) {
            $sender->sendMessage(C::colorize("&r&cError: &4You don't have any XP."));
            return;
        }

        $sender->getXpManager()->setCurrentTotalXp(0);

        $item = ItemsGlaciaItemFactory::xpNote($sender, $xp);
        $sender->getInventory()->addItem($item);

        $sender->sendMessage(C::colorize("&r&aYou have put " . number_format($xp) . " XP into a bottle."));
    }
}
