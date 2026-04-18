<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class EnderSeeCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $targetName = (string) ($args["player"] ?? "");
        $target = PlayerUtils::getPlayerByPrefix($targetName);
        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cThat player could not be found."));
            return;
        }

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST, $target->getEnderInventory());
        $menu->setName(C::colorize("&r&8" . $target->getName() . "'s Ender Chest"));
        $menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
        }));
        $menu->send($sender);

        GlaciaUtils::logAdminAction("EnderSee", [
            "admin" => $sender->getName(),
            "target" => $target->getName()
        ]);
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
