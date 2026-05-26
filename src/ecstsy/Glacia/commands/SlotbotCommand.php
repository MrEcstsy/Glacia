<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\slotbot\SlotbotRewardRepository;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\Glacia\utils\screens\slotbot\SlotbotMenuScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SlotbotCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("subcommand", true));
        $this->registerArgument(1, new RawStringArgument("player", true));
        $this->registerArgument(2, new RawStringArgument("amount", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $sub = strtolower(trim((string) ($args["subcommand"] ?? "")));
        if ($sub === "rerollshop") {
            if (!$sender->hasPermission("glacia.admin")) {
                $sender->sendMessage(C::colorize("&r&cYou do not have permission to reroll the slotbot credit shop."));
                return;
            }

            SlotbotRewardRepository::rerollCreditShop();
            $offer = SlotbotRewardRepository::getCurrentCreditShopOffer($sender instanceof Player ? $sender : null);
            $itemName = $offer !== null
                ? (($offer["item"]->getCustomName() !== "") ? $offer["item"]->getCustomName() : $offer["item"]->getName())
                : "&cNo valid offer";
            $sender->sendMessage(C::colorize("&r&aSlotbot credit shop rerolled. &7Current offer: &f" . $itemName));
            return;
        }

        if (in_array($sub, ["addcredits", "takecredits", "setcredits", "viewcredits"], true)) {
            if (!$sender->hasPermission("glacia.admin")) {
                $sender->sendMessage(C::colorize("&r&cYou do not have permission to manage slotbot credits."));
                return;
            }

            $targetName = trim((string) ($args["player"] ?? ""));
            if ($targetName === "") {
                $sender->sendMessage(C::colorize("&r&cUsage: /slotbot {$sub} <player> " . ($sub === "viewcredits" ? "" : "<amount>")));
                return;
            }

            $target = PlayerUtils::getPlayerByPrefix($targetName);
            if (!$target instanceof Player) {
                $sender->sendMessage(C::colorize("&r&cThat player is not online."));
                return;
            }

            $session = Loader::getPlayerManager()->getSession($target);
            if ($session === null) {
                $sender->sendMessage(C::colorize("&r&cThat player's session is unavailable."));
                return;
            }

            if ($sub === "viewcredits") {
                $sender->sendMessage(C::colorize("&r&e" . $target->getName() . "&7 has &f" . number_format($session->getSlotCredits()) . " &7slotbot credits."));
                return;
            }

            $amount = (int) trim((string) ($args["amount"] ?? ""));
            if ($amount <= 0) {
                $sender->sendMessage(C::colorize("&r&cUsage: /slotbot {$sub} <player> <amount>"));
                return;
            }

            $before = $session->getSlotCredits();
            switch ($sub) {
                case "addcredits":
                    $session->addSlotCredits($amount);
                    break;
                case "takecredits":
                    $session->setSlotCredits(max(0, $before - $amount));
                    break;
                case "setcredits":
                    $session->setSlotCredits($amount);
                    break;
            }

            $after = $session->getSlotCredits();
            $sender->sendMessage(C::colorize("&r&aUpdated &f" . $target->getName() . "&a's slotbot credits to &f" . number_format($after) . "&a."));
            if ($target !== $sender) {
                $target->sendMessage(C::colorize("&r&eYour slotbot credits are now &f" . number_format($after) . "&e."));
            }
            return;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cUse /slotbot rerollshop, /slotbot addcredits, /slotbot takecredits, /slotbot setcredits, or /slotbot viewcredits from console."));
            return;
        }

        (new SlotbotMenuScreen($sender))->display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
