<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\enchantments\manager\SoulGemSessionManager;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\trackers\TrackerManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class WithdrawSoulsCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("amount"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        if (!SoulGemSessionManager::isEnabled()) {
            $sender->sendMessage(C::colorize("&r&cSouls are currently disabled."));
            return;
        }

        $hand = $sender->getInventory()->getItemInHand();
        $input = strtolower(trim((string) ($args["amount"] ?? "")));
        if ($input === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /withdrawsouls <amount|all>"));
            return;
        }

        $usesBoundGem = SoulGemSessionManager::hasBoundSoulGem($hand);
        if ($usesBoundGem) {
            $stored = SoulGemSessionManager::getBoundSoulCount($hand);
            if ($stored <= 0) {
                $sender->sendMessage(C::colorize("&r&cYour held item's Soul Gem is empty."));
                return;
            }
        } elseif (TrackerManager::hasTracker($hand, TrackerManager::SOULS)) {
            $stored = TrackerManager::getProgress($hand, TrackerManager::SOULS);
            if ($stored <= 0) {
                $sender->sendMessage(C::colorize("&r&cYour held item does not have any souls stored."));
                return;
            }
        } else {
            $sender->sendMessage(C::colorize("&r&cYour held item does not have a merged Soul Gem or Soul Tracker."));
            return;
        }

        $amount = $input === "all" ? $stored : GlaciaUtils::parseShorthandNumber($input);
        if ($amount <= 0) {
            $sender->sendMessage(C::colorize("&r&cUsage: /withdrawsouls <amount|all>"));
            return;
        }

        if ($amount > $stored) {
            $sender->sendMessage(C::colorize("&r&cThat item only has &f" . number_format($stored) . " &csouls stored."));
            return;
        }

        if ($usesBoundGem) {
            $group = SoulGemSessionManager::getBoundGroup($hand);
            if ($group === "") {
                $group = SoulGemSessionManager::determinePrimaryGroup($hand);
            }
            $sender->getInventory()->setItemInHand(SoulGemSessionManager::setBoundSoulCount($hand, $stored - $amount));
            $boundType = SoulGemSessionManager::getBoundConduitTypeName($hand);
        } else {
            $group = SoulGemSessionManager::determinePrimaryGroup($hand);
            $sender->getInventory()->setItemInHand(
                TrackerManager::setProgress($hand, TrackerManager::SOULS, $stored - $amount)
            );
            $boundType = "stable";
        }

        $gem = SoulGemSessionManager::createFuelGemForConduitType($boundType, $amount, $group);
        if ($sender->getInventory()->canAddItem($gem)) {
            $sender->getInventory()->addItem($gem);
        } else {
            $sender->getWorld()->dropItem($sender->getPosition(), $gem);
        }

        $sender->sendMessage(C::colorize(
            $usesBoundGem
                ? "&r&aWithdrawn &f" . number_format($amount) . " &asouls from your merged conduit."
                : "&r&aWithdrawn &f" . number_format($amount) . " &asouls into a conduit gem."
        ));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
