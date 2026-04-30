<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\faction;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\contribution\FactionContributionManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class FactionContributionScreen
{
    public static function display(Player $player): void
    {
        if (!FactionContributionManager::isEnabled()) {
            $player->sendMessage(C::colorize("&r&cFaction contribution is currently disabled."));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        $faction = $session?->getFaction();
        if ($faction === null) {
            $player->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $factionId = $faction->getFactionId();
        $personal = FactionContributionManager::getPersonalPoints($player);
        $factionPoints = FactionContributionManager::getFactionPoints($factionId);
        $milestone = FactionContributionManager::getFactionMilestone($factionId);
        $bar = FactionContributionManager::getProgressBarData($factionId);
        $tasks = FactionContributionManager::getRequiredTasksForNextMilestone($factionId);
        $progress = FactionContributionManager::getTaskProgress($factionId);

        $content = [];
        $content[] = "&r&fPersonal Points: &a" . number_format($personal);
        $content[] = "&r&fFaction Points: &a" . number_format($factionPoints);
        $content[] = "&r&fMilestone Tier: &e" . number_format($milestone);
        $content[] = "&r&fProgress: &b" . number_format($bar["progress"]) . "&7/&b" . number_format($bar["target"]) . " &7(" . (int) round($bar["percent"] * 100) . "%)";
        $content[] = "";
        if ($tasks !== []) {
            $content[] = "&r&7Next milestone tasks:";
            foreach ($tasks as $task => $needed) {
                $done = max(0, (int) ($progress[$task] ?? 0));
                $content[] = "&r&8- &f" . str_replace("_", " ", ucfirst($task)) . "&7: &b{$done}&7/&b{$needed}";
            }
        } else {
            $content[] = "&r&aAll configured milestones unlocked.";
        }

        $content[] = "";
        $content[] = "&r&8Note: rare fish catches auto-count.";
        $content[] = "&r&8Turn-in is only for special deposit items.";

        $preview = self::resolveDepositPreview($player->getInventory()->getItemInHand());

        $form = new SimpleForm(function(Player $submitter, ?int $data): void {
            if ($data === null) {
                return;
            }
            switch ($data) {
                case 0:
                    self::display($submitter);
                    return;
                case 1:
                    self::depositFromHand($submitter, null);
                    return;
                case 2:
                    self::depositFromHand($submitter, 1);
                    return;
                case 3:
                    self::sendChatSummary($submitter);
                    return;
            }
        });

        $form->setTitle(C::colorize("&r&bFaction Contribution"));
        $form->setContent(C::colorize(implode("\n", $content)));
        $form->addButton(C::colorize("&r&bRefresh"));

        if ($preview["pointsEach"] > 0) {
            $total = $preview["pointsEach"] * $preview["count"];
            $form->addButton(C::colorize("&r&aTurn In All (Special)\n&7+{$total} points"));
            $form->addButton(C::colorize("&r&eTurn In 1 (Special)\n&7+{$preview["pointsEach"]} points"));
        } else {
            $form->addButton(C::colorize("&r&cTurn In All (Special)\n&7Hold valid item"));
            $form->addButton(C::colorize("&r&cTurn In 1 (Special)\n&7Hold valid item"));
        }

        $form->addButton(C::colorize("&r&7Send Chat Summary"));
        $player->sendForm($form);
    }

    public static function depositFromHand(Player $player, ?int $requestedAmount): void
    {
        if (!FactionContributionManager::isEnabled()) {
            $player->sendMessage(C::colorize("&r&cFaction contribution is currently disabled."));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        $faction = $session?->getFaction();
        if ($faction === null) {
            $player->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $inHand = $player->getInventory()->getItemInHand();
        if ($inHand->isNull()) {
            $player->sendMessage(C::colorize("&r&cHold a special deposit item in your hand first."));
            return;
        }

        $preview = self::resolveDepositPreview($inHand);
        $pointsEach = $preview["pointsEach"];
        if ($pointsEach <= 0) {
            $player->sendMessage(C::colorize("&r&cThat item is not a configured special deposit item."));
            return;
        }

        $request = $requestedAmount !== null ? max(1, $requestedAmount) : $inHand->getCount();
        $toConsume = min($request, $inHand->getCount());
        if ($toConsume <= 0) {
            $player->sendMessage(C::colorize("&r&cNothing to turn in."));
            return;
        }

        $total = $pointsEach * $toConsume;
        $inHand->pop($toConsume);
        $player->getInventory()->setItemInHand($inHand);

        FactionContributionManager::addSpecialDeposit($player, $total, "Special Turn-In");
        FactionContributionManager::recordTaskProgress($player, "special_deposits", $toConsume);
        $player->sendMessage(C::colorize("&r&aTurned in &f{$toConsume}x &afor &f{$total} &acontribution points."));
    }

    public static function sendChatSummary(Player $player): void
    {
        $session = Loader::getPlayerManager()->getSession($player);
        $faction = $session?->getFaction();
        if ($faction === null) {
            $player->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $factionId = $faction->getFactionId();
        $personal = FactionContributionManager::getPersonalPoints($player);
        $factionPoints = FactionContributionManager::getFactionPoints($factionId);
        $milestone = FactionContributionManager::getFactionMilestone($factionId);
        $bar = FactionContributionManager::getProgressBarData($factionId);
        $tasks = FactionContributionManager::getRequiredTasksForNextMilestone($factionId);
        $progress = FactionContributionManager::getTaskProgress($factionId);

        $player->sendMessage(C::colorize("&r&8-=-=-=&7-=-=-= &bFaction Contribution &7-=-=-=&8-=-=-="));
        $player->sendMessage(C::colorize("&r&b&l> &r&fPersonal Points&7: &a" . number_format($personal)));
        $player->sendMessage(C::colorize("&r&b&l> &r&fFaction Points&7: &a" . number_format($factionPoints)));
        $player->sendMessage(C::colorize("&r&b&l> &r&fMilestone Tier&7: &e" . number_format($milestone)));
        $player->sendMessage(C::colorize("&r&b&l> &r&fProgress&7: &b" . number_format($bar["progress"]) . "&7/&b" . number_format($bar["target"]) . " &7(" . (int) round($bar["percent"] * 100) . "%)"));

        if ($tasks !== []) {
            $player->sendMessage(C::colorize("&r&7Next milestone tasks:"));
            foreach ($tasks as $task => $needed) {
                $done = max(0, (int) ($progress[$task] ?? 0));
                $player->sendMessage(C::colorize("&r&8- &f" . str_replace("_", " ", ucfirst($task)) . "&7: &b{$done}&7/&b{$needed}"));
            }
        } else {
            $player->sendMessage(C::colorize("&r&aAll configured milestones unlocked."));
        }
    }

    /**
     * @return array{pointsEach:int,count:int}
     */
    private static function resolveDepositPreview(Item $inHand): array
    {
        if ($inHand->isNull()) {
            return ["pointsEach" => 0, "count" => 0];
        }
        $normalizedKeys = [];
        $glacia = $inHand->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia !== null) {
            $glaciaItem = trim(strtolower($glacia->getString("glaciaItem", "")));
            if ($glaciaItem !== "") {
                $normalizedKeys[] = $glaciaItem;
            }
        }
        $normalizedKeys[] = strtolower(str_replace(" ", "_", C::clean($inHand->getVanillaName())));
        $pointsEach = 0;
        foreach ($normalizedKeys as $key) {
            $pointsEach = FactionContributionManager::getDepositPointsForItemKey($key);
            if ($pointsEach > 0) {
                break;
            }
        }
        return ["pointsEach" => $pointsEach, "count" => $inHand->getCount()];
    }
}

