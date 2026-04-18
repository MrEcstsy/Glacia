<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\Faction;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class TopSubCommand extends BaseSubCommand {

    private const PER_PAGE = 10;

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("category", true));
        $this->registerArgument(1, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $category = strtolower((string) ($args["category"] ?? "value"));
        $page = max(1, (int) ($args["page"] ?? 1));

        $label = match ($category) {
            "value", "worth", "spawners", "spawner", "spawnervalue" => "Faction Value",
            "power" => "Power",
            "claims", "claimscount" => "Claims",
            "members", "membercount" => "Members",
            default => null,
        };

        if ($label === null) {
            $sender->sendMessage(C::colorize("&r&cUsage: /f top [value|power|claims|members] [page]"));
            return;
        }

        $factions = Loader::getFactionManager()->getAllFactions();
        usort($factions, function(Faction $a, Faction $b) use ($category): int {
            $aStat = $this->getFactionStat($a, $category);
            $bStat = $this->getFactionStat($b, $category);
            return $bStat <=> $aStat ?: strcasecmp($a->getName(), $b->getName());
        });

        $totalPages = max(1, (int) ceil(count($factions) / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $sender->sendMessage(C::colorize("&r&b&lFaction Top &r&7({$label}) &f{$page}/{$totalPages}"));

        $slice = array_slice($factions, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        foreach ($slice as $index => $faction) {
            $rank = (($page - 1) * self::PER_PAGE) + $index + 1;
            $sender->sendMessage(C::colorize(
                "&r&b{$rank}. &f" . $faction->getName() . " &7- &b" . $this->formatFactionStat($faction, $category)
            ));
        }
    }

    private function getFactionStat(Faction $faction, string $category): int
    {
        return match ($category) {
            "value", "worth", "spawners", "spawner", "spawnervalue" => $faction->getFactionValue(),
            "power" => $faction->getFactionPower(),
            "claims", "claimscount" => $faction->getClaimCount(),
            "members", "membercount" => count($faction->getMembers()),
            default => 0,
        };
    }

    private function formatFactionStat(Faction $faction, string $category): string
    {
        return match ($category) {
            "value", "worth", "spawners", "spawner", "spawnervalue" => "$" . number_format($faction->getFactionValue()),
            "power" => number_format($faction->getFactionPower()),
            "claims", "claimscount" => (string) $faction->getClaimCount(),
            "members", "membercount" => (string) count($faction->getMembers()),
            default => "0",
        };
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
