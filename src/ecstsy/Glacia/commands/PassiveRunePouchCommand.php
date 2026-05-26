<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\screens\runes\PassiveRunePouchScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class PassiveRunePouchCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $manager = \ecstsy\Glacia\Loader::getPassiveRunePouchManager();
        $slot = $manager->findFirstPouchSlot($sender);
        if ($slot === null) {
            $sender->sendMessage(\pocketmine\utils\TextFormat::colorize("&r&cYou need a Passive Rune Pouch item to open this."));
            return;
        }

        (new PassiveRunePouchScreen($sender, $slot))->display();
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
