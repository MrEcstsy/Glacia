<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\challenges;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class ChallengesScreen extends BaseScreen {

    public static function display(Player $player): void
    {
        $manager = Loader::getChallengeManager();
        $rows = $manager->getChallengeView($player);
        $timeRemaining = $rows !== [] ? max(0, (int) ($rows[0]["timeRemaining"] ?? 0)) : 0;

        $form = new SimpleForm(function(Player $player, ?int $data) use ($manager): void {
            if ($data === null) {
                return;
            }

            if ($data === 0) {
                $claimed = $manager->claimPendingRewards($player);
                if ($claimed <= 0) {
                    $player->sendMessage(C::colorize("&r&7No pending challenge rewards to claim."));
                    return;
                }

                $player->sendMessage(C::colorize("&r&aClaimed &f" . number_format($claimed) . " &achallenge rewards."));
            }
        });

        $form->setTitle(C::colorize("&r&8Challenges"));
        $form->setContent(C::colorize("&r&7New challenges in... &a" . gmdate("H:i:s", $timeRemaining)));
        $form->addButton(C::colorize("&r&8Claim Pending Rewards"));

        foreach ($rows as $row) {
            $challengeName = self::stripFormattingCodes((string) $row["display"]);
            $rankText = self::formatPlacement((int) ($row["myRank"] ?? 0));
            $participants = (int) ($row["myParticipants"] ?? 0);
            $cap = (int) ($row["myParticipantCap"] ?? 0);
            $participantsText = $cap > 0 ? "{$participants}/{$cap}" : "No Faction";
            $first = (int) ($row["firstPlaceProgress"] ?? 0);
            $mine = (int) ($row["myProgress"] ?? 0);
            $gap = (int) ($row["myGapToFirst"] ?? max(0, $first - $mine));
            $progressText = "1. " . number_format($first) . " - you: " . number_format($mine) . " (" . number_format($gap) . ")";

            $form->addButton(C::colorize(
                "&r&0" . $challengeName . "\n" .
                "&r&8" . $rankText . " &7| &f" . $progressText . " &7| &b" . $participantsText
            ));
        }

        $player->sendForm($form);
    }

    private static function stripFormattingCodes(string $text): string
    {
        $text = C::clean($text);
        $text = (string) preg_replace('/&[0-9a-fk-or]/i', '', $text);
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private static function formatPlacement(int $rank): string
    {
        return match ($rank) {
            1 => "1st Place",
            2 => "2nd Place",
            3 => "3rd Place",
            default => "Unranked",
        };
    }
}
