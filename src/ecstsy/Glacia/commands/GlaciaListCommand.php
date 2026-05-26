<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\staff\StaffModeManager;
use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use function count;
use function implode;
use function ksort;
use function sort;
use function strtolower;
use function ucfirst;
use const SORT_STRING;

final class GlaciaListCommand extends BaseCommand {

    /** @var array<string, true> */
    private const STAFF_RANKS = [
        "admin" => true,
        "builder" => true,
        "helper" => true,
        "moderator" => true,
    ];

    /** @var array<int, string> */
    private const RANK_ORDER = [
        "staff",
        "frostlord",
        "iceheart",
        "glacian",
        "winterborn",
        "cryoshield",
        "frostborn",
    ];

    protected function prepare() : void {
        $this->setPermission(DefaultPermissionNames::COMMAND_LIST);
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void {
        $groupedPlayers = [];
        $displayNames = [];

        foreach ($sender->getServer()->getOnlinePlayers() as $player) {
            if (StaffModeManager::shouldHideFromPlayerList($player)) {
                if (!$sender instanceof Player || !StaffModeManager::canViewerSeeHiddenStaff($sender)) {
                    continue;
                }
            }

            if ($sender instanceof Player && !$sender->canSee($player)) {
                continue;
            }

            $rankKey = "frostborn";
            $rankDisplay = "Frostborn";

            $session = Loader::getPlayerManager()->getSession($player);
            if ($session !== null) {
                $rankKey = strtolower($session->getRank());
                $rankDisplay = $session->getRankObject()?->getDisplayName() ?? ucfirst($rankKey);
            }

            $bucketKey = isset(self::STAFF_RANKS[$rankKey]) ? "staff" : $rankKey;
            $bucketDisplay = $bucketKey === "staff" ? "Staff" : $rankDisplay;

            $groupedPlayers[$bucketKey] ??= [];
            $displayNames[$bucketKey] ??= $bucketDisplay;
            $groupedPlayers[$bucketKey][] = $player->getName();
        }

        foreach ($groupedPlayers as &$names) {
            sort($names, SORT_STRING);
        }
        unset($names);

        $visibleCount = 0;
        foreach ($groupedPlayers as $names) {
            $visibleCount += count($names);
        }

        $sender->sendMessage(KnownTranslationFactory::commands_players_list((string) $visibleCount, (string) $sender->getServer()->getMaxPlayers()));

        if ($groupedPlayers === []) {
            $sender->sendMessage(C::colorize("&r&7No players are visible right now."));
            return;
        }

        foreach (self::RANK_ORDER as $bucketKey) {
            if (!isset($groupedPlayers[$bucketKey])) {
                continue;
            }

            $sender->sendMessage($this->formatBucketLine($displayNames[$bucketKey], $groupedPlayers[$bucketKey]));
            unset($groupedPlayers[$bucketKey], $displayNames[$bucketKey]);
        }

        if ($groupedPlayers !== []) {
            ksort($groupedPlayers, SORT_STRING);
            foreach ($groupedPlayers as $bucketKey => $names) {
                $sender->sendMessage($this->formatBucketLine($displayNames[$bucketKey] ?? ucfirst($bucketKey), $names));
            }
        }
    }

    /**
     * @param string[] $names
     */
    private function formatBucketLine(string $label, array $names) : string {
        return C::colorize("&r&b{$label} &7(" . count($names) . ")&f: " . implode("&7, &f", $names));
    }
}
