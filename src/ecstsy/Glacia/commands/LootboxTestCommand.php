<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\args\RawStringArgument;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\utils\screens\lootboxes\LootboxAnimationScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class LootboxTestCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("box", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $boxId = strtolower((string) ($args["box"] ?? ""));
        if ($boxId === "") {
            $ids = LootboxRepository::getBoxIds();
            $sender->sendMessage(C::colorize("&r&cUsage: /lootboxtest <box>"));
            $sender->sendMessage(C::colorize("&r&7Available: &f" . ($ids === [] ? "none" : implode("&7, &f", $ids))));
            return;
        }

        $box = LootboxRepository::getBoxByLookup($boxId);
        if ($box === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown lootbox '&f{$boxId}&c'."));
            return;
        }

        (new LootboxAnimationScreen($sender, $box))->display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
