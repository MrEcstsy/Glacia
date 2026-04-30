<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\audit\FactionAuditAccess;
use ecstsy\Glacia\server\factions\audit\FactionAuditFeed;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FactionAuditSubCommand extends BaseSubCommand {

    private const PER_PAGE = 8;

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session === null || $session->getFactionId() === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        if (!FactionAuditAccess::canView($session)) {
            $sender->sendMessage(C::colorize("&r&cYou need faction leader, officer, or the &fview-audit-log&c role permission to use this."));
            return;
        }

        $factionId = $session->getFactionId();
        $faction = $session->getFaction();
        $name = $faction !== null ? $faction->getName() : "";

        $page = isset($args["page"]) ? max(1, (int) $args["page"]) : 1;
        $maxPage = FactionAuditFeed::getTotalPages($factionId, self::PER_PAGE);
        if ($page > $maxPage) {
            $sender->sendMessage(C::colorize("&r&cThat page does not exist. Max: &f{$maxPage}"));
            return;
        }

        $entries = FactionAuditFeed::getPage($factionId, $page, self::PER_PAGE);

        $headerCore = "&b&lFaction audit &r&7— &r&a&l{$name}&r &7{$page}/{$maxPage}";
        $sender->sendMessage(C::colorize(self::padFactionLine($headerCore)));
        if ($entries === []) {
            $sender->sendMessage(C::colorize("&r&7Nothing logged yet. Claims, bank moves, create/disband, etc. will show here."));
            return;
        }

        foreach ($entries as $row) {
            $ts = isset($row["ts"]) ? (int) $row["ts"] : 0;
            $when = $ts > 0 ? date("M j g:i A", $ts) : "?";
            $action = (string) ($row["action"] ?? "?");
            /** @var array<string, mixed> $data */
            $data = isset($row["data"]) && is_array($row["data"]) ? $row["data"] : [];
            $detail = self::summarizeData($action, $data);
            $sender->sendMessage(C::colorize("&r&8│ &7{$when} &b{$action}&7 » &r{$detail}"));
        }
    }

    private static function padFactionLine(string $ampersandFormatted, int $lineWidth = 52): string {
        $stripped = preg_replace('/&[0-9a-fk-or]/i', '', $ampersandFormatted) ?? "";
        $len = strlen($stripped);
        $pad = max(0, (int) floor(($lineWidth - $len) / 2));

        return "&r" . str_repeat(" ", $pad) . $ampersandFormatted;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function summarizeData(string $action, array $data): string {
        $player = (string) ($data["player"] ?? $data["founder"] ?? $data["leader"] ?? "");
        $chunk = "";
        if (isset($data["chunk_x"], $data["chunk_z"])) {
            $chunk = "chunk " . $data["chunk_x"] . ", " . $data["chunk_z"];
            if (isset($data["world"])) {
                $chunk .= " (" . $data["world"] . ")";
            }
        }
        $amount = $data["amount"] ?? null;

        return match ($action) {
            "Bank Deposit", "Bank Withdraw" => ($player !== "" ? "&e{$player}&7 " : "") . "&a$" . ($amount !== null ? number_format((float) $amount) : "?"),
            "Claim", "Unclaim" => $chunk !== "" ? "&7{$chunk}" : self::formatFallback($data),
            "Create" => $player !== "" ? "&e{$player} &7created" : self::formatFallback($data),
            "Disband" => $player !== "" ? "&e{$player} &7disbanded" : self::formatFallback($data),
            default => self::formatFallback($data),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatFallback(array $data): string {
        $parts = [];
        foreach ($data as $k => $v) {
            if ($k === "faction_id") {
                continue;
            }
            if (is_scalar($v) || $v === null) {
                $parts[] = (string) $k . "=" . (string) $v;
            }
        }
        $s = implode(", ", array_slice($parts, 0, 4));
        return $s !== "" ? "&7" . $s : "&7—";
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
