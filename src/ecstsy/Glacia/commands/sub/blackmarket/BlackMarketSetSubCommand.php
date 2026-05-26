<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\blackmarket;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\blackmarket\BlackMarketAuctionManager;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class BlackMarketSetSubCommand extends BaseSubCommand {

    protected function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("id"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $id = trim((string) ($args["id"] ?? ""));
        if ($id === "") {
            $sender->sendMessage(C::colorize("&r&cProvide a pool entry id (see &fblackmarket.yml &cpool keys)."));
            return;
        }

        $manager = BlackMarketAuctionManager::getInstance();
        if (!$manager->setNextAuctionPoolEntry($id)) {
            $sender->sendMessage(C::colorize(
                "&r&cUnknown pool entry &f{$id}&c. Use a key from &fblackmarket.yml &cunder &fpool&c."
            ));
            return;
        }

        $display = $manager->getPoolEntryDisplayName($id);
        $sender->sendMessage(C::colorize(
            "&r&aNext Black Market auction will use pool entry &f{$id}" . ($display !== "" ? " &7(" . $display . "&7)" : "") . "&a. Written to &fblackmarket.yml&a."
        ));
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}
