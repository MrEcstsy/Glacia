<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\anticheat;

use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatProfile;
use ecstsy\Glacia\server\staff\anticheat\GuardLagResult;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use function array_slice;
use function array_sum;
use function count;
use function floor;
use function implode;
use function max;
use function min;
use function round;
use function sprintf;
use function strtolower;
use function usort;

final class AntiCheatInspectPresenter {

    public const SECTION_SUMMARY = "summary";
    public const SECTION_COMBAT = "combat";
    public const SECTION_MOVEMENT = "movement";
    public const SECTION_NETWORK = "network";
    public const SECTION_VIOLATIONS = "violations";
    public const SECTION_METHOD = "method";

    /**
     * @return array{
     *   manager: AntiCheatManager,
     *   profile: AntiCheatProfile,
     *   violations: array<string, int>,
     *   lag: GuardLagResult,
     *   risk: array{risk:int, trust:int, band:string, bandCol:string, confidence:string, confDetail:string, liveRisk:int, historyRisk:int, historyWeight:float, historyResetDaysLeft:int},
     *   ping:int,
     *   world:string,
     *   sessionMinutes:int,
     *   hitCount:int
     * }
     */
    public static function snapshot(Player $target): array {
        $manager = AntiCheatManager::getInstance();
        $profile = $manager->getLiveProfile($target);
        $violations = $profile->getViolations();
        $lag = $manager->assessLag($target);

        return [
            "manager" => $manager,
            "profile" => $profile,
            "violations" => $violations,
            "lag" => $lag,
            "risk" => self::riskSnapshot($profile, $violations),
            "ping" => $target->getNetworkSession()->getPing(),
            "world" => $target->getWorld()->getFolderName(),
            "sessionMinutes" => (int) floor(max(0.0, microtime(true) - $profile->getJoinWallTime()) / 60.0),
            "hitCount" => $profile->getHitCount(),
        ];
    }

    /**
     * @param array<string, int> $violations
     * @return array{risk: int, trust: int, band: string, bandCol: string, confidence: string, confDetail: string, liveRisk:int, historyRisk:int, historyWeight:float, historyResetDaysLeft:int}
     */
    public static function riskSnapshot(AntiCheatProfile $profile, array $violations): array {
        $manager = AntiCheatManager::getInstance();
        $sum = array_sum($violations);
        $peak = $violations === [] ? 0 : max($violations);
        $liveRisk = (int) min(100, round(sqrt(max(0, $sum)) * 14.0 + $peak * 2.2));
        $historyRisk = $manager->isGuardRiskHistoryEnabled()
            ? (int) min(100, round($profile->getRiskHistoryScore()))
            : 0;
        $historyWeight = $manager->getGuardRiskHistoryBlendWeight();
        $risk = (int) min(100, max(
            $liveRisk,
            round(($liveRisk * (1.0 - $historyWeight)) + ($historyRisk * $historyWeight))
        ));
        $trust = max(0, min(100, 100 - $risk));

        [$band, $bandCol] = match (true) {
            $risk <= 22 => ["Low concern", "&a"],
            $risk <= 48 => ["Moderate", "&e"],
            $risk <= 72 => ["Elevated", "&6"],
            default => ["High", "&c"],
        };

        $sessionSec = max(0.0, microtime(true) - $profile->getJoinWallTime());
        $hits = $profile->getHitCount();
        $pingSamples = $profile->lagPingSampleCount();

        $confidence = "Medium";
        $detail = "Session data is still partial.";
        if ($sessionSec < 45.0 || $pingSamples < 8) {
            $confidence = "Low";
            $detail = "Need a longer session and more ping samples.";
        } elseif ($sessionSec >= 180.0 && $hits >= 10 && $pingSamples >= 25) {
            $confidence = "High";
            $detail = "Solid combat and network sample size.";
        } elseif ($sessionSec >= 90.0 && $pingSamples >= 15) {
            $confidence = "Medium";
            $detail = "Enough data for a decent read.";
        }

        $historyResetDaysLeft = 0;
        if ($manager->isGuardRiskHistoryEnabled()) {
            $detail .= " Season memory contributes " . (int) round($historyWeight * 100) . "% until a Guard reset or season wipe.";
        }

        return [
            "risk" => $risk,
            "trust" => $trust,
            "band" => $band,
            "bandCol" => $bandCol,
            "confidence" => $confidence,
            "confDetail" => $detail,
            "liveRisk" => $liveRisk,
            "historyRisk" => $historyRisk,
            "historyWeight" => $historyWeight,
            "historyResetDaysLeft" => $historyResetDaysLeft,
        ];
    }

    public static function buildHomeBody(Player $target): string {
        $snapshot = self::snapshot($target);
        $risk = $snapshot["risk"];
        $topViolations = self::topViolations($snapshot["violations"], 3);

        $lines = [
            "&l&bGuard Snapshot&r",
            "&8Live readout for &f" . $target->getName(),
            "",
            "&l&dTarget&r",
            "&7Name: &f" . $target->getName(),
            "&7World: &f" . $snapshot["world"],
            "",
            "&l&dOverview&r",
            "&7Risk: " . $risk["bandCol"] . $risk["band"] . "&7 (&f" . $risk["risk"] . "&7/100)",
            "&7Live risk: &f" . $risk["liveRisk"] . "&7/100",
            "&7Season memory: &f" . $risk["historyRisk"] . "&7/100",
            "&7Trust: &b" . $snapshot["risk"]["trust"] . "&7/100",
            "&7Confidence: " . self::confidenceColor($risk["confidence"]) . $risk["confidence"],
            "&7Ping: &f" . $snapshot["ping"] . "ms",
            "&7Session: &f~" . $snapshot["sessionMinutes"] . "m",
            "&7Hits logged: &f" . $snapshot["hitCount"],
            "&7Lag tier: " . self::lagTierColored($snapshot["lag"]),
            "",
            "&l&eTop Active Signals&r",
        ];

        if ($topViolations === []) {
            $lines[] = "&a- No active VL counters.";
        } else {
            foreach ($topViolations as [$check, $vl]) {
                $lines[] = "&7- " . self::vlColor($vl) . $check . "&7: &f" . $vl;
            }
        }

        $lines[] = "";
        $lines[] = "&8Use the buttons below for focused sections.";

        return C::colorize(implode("\n", $lines));
    }

    public static function buildSectionBody(Player $target, string $section): string {
        $snapshot = self::snapshot($target);
        $profile = $snapshot["profile"];
        $violations = $snapshot["violations"];
        $lag = $snapshot["lag"];
        $risk = $snapshot["risk"];

        $body = match ($section) {
            self::SECTION_SUMMARY => implode("\n", [
                "&l&bSummary&r",
                "",
                "&7Risk band: " . $risk["bandCol"] . $risk["band"],
                "&7Risk index: &f" . $risk["risk"] . "&7/100",
                "&7Live risk: &f" . $risk["liveRisk"] . "&7/100",
                "&7Season memory: &f" . $risk["historyRisk"] . "&7/100",
                "&7Trust: &b" . $risk["trust"] . "&7/100",
                "&7Confidence: " . self::confidenceColor($risk["confidence"]) . $risk["confidence"],
                "&8" . $risk["confDetail"],
                "",
                "&7Ping: &f" . $snapshot["ping"] . "ms",
                "&7Lag tier: " . self::lagTierColored($lag),
                "&7Session: &f~" . $snapshot["sessionMinutes"] . "m",
                "&7Hits logged: &f" . $snapshot["hitCount"],
                "&7Guard enabled: " . ($snapshot["manager"]->isAnticheatEnabled() ? "&aYes" : "&cNo"),
            ]),
            self::SECTION_COMBAT => implode("\n", [
                "&l&cCombat Snapshot&r",
                "",
                "&8Click trade pressure and reach context.",
                "",
                "&7Reach VL: " . self::vlColor($violations["Reach"] ?? 0) . ($violations["Reach"] ?? 0),
                "&7AutoClicker VL: " . self::vlColor($violations["AutoClicker"] ?? 0) . ($violations["AutoClicker"] ?? 0),
                "&7Velocity VL: " . self::vlColor($violations["Velocity"] ?? 0) . ($violations["Velocity"] ?? 0),
                "&7Hit count: &f" . $profile->getHitCount(),
                "&7Swing/hit ratio: &f" . ($profile->getHitCount() > 0 ? sprintf("%.2f", $profile->swingHitRatio()) : "-"),
                "",
                "&8Use this with live spectating.",
                "&8High Reach + clean ping beats VL alone.",
            ]),
            self::SECTION_MOVEMENT => implode("\n", [
                "&l&bMovement Snapshot&r",
                "",
                "&8Timer, packets, and pace checks.",
                "",
                "&7Speed VL: " . self::vlColor($violations["Speed"] ?? 0) . ($violations["Speed"] ?? 0),
                "&7Timer VL: " . self::vlColor($violations["Timer"] ?? 0) . ($violations["Timer"] ?? 0),
                "&7BadPackets VL: " . self::vlColor($violations["BadPackets"] ?? 0) . ($violations["BadPackets"] ?? 0),
                "",
                "&8Movement VL can rise during lag or chunk stress.",
                "&8Cross-check lag tier before acting.",
            ]),
            self::SECTION_NETWORK => implode("\n", [
                "&l&9Network Snapshot&r",
                "",
                "&8Lag context for every other read.",
                "",
                "&7Lag tier: " . self::lagTierColored($lag),
                "&7Raw ping: &f" . $snapshot["ping"] . "ms",
                "&7Ping EWMA: &f" . sprintf("%.0f", $lag->pingEwmaMs) . "ms",
                "&7Jitter est.: &f" . sprintf("%.1f", $lag->jitterMs) . "ms",
                "&7Reach multiplier: &bx" . sprintf("%.2f", $lag->multiplier),
                "",
                "&8Higher jitter widens combat tolerances.",
            ]),
            self::SECTION_VIOLATIONS => self::buildViolationSection($violations),
            default => implode("\n", [
                "&l&dHow Guard Reads Risk&r",
                "",
                "&7- Risk starts from live VL totals.",
                "&7- Season memory stores alert-worthy hits until manual reset.",
                "&7- Peak checks and stacked counters raise it.",
                "&7- Live evidence still matters more than history.",
                "&7- Trust is just the inverse read of that score.",
                "&7- Confidence grows with session, hits, and ping samples.",
                "&8- Treat this as evidence support, not auto-proof.",
            ]),
        };

        return C::colorize($body);
    }

    /**
     * @param array<string, int> $violations
     * @return list<array{0:string,1:int}>
     */
    public static function topViolations(array $violations, int $limit = 3): array {
        $rows = [];
        foreach ($violations as $check => $vl) {
            $rows[] = [$check, $vl];
        }

        usort($rows, static fn(array $a, array $b): int => $b[1] <=> $a[1]);
        return array_slice($rows, 0, max(0, $limit));
    }

    /**
     * @param array<string, int> $violations
     */
    public static function buildViolationSection(array $violations): string {
        if ($violations === []) {
            return C::colorize(implode("\n", [
                "&l&eViolations&r",
                "",
                "&aNo active VL counters.",
                "&8Decay is applied over time.",
            ]));
        }

        $rows = self::topViolations($violations, 12);
        $lines = ["&l&eViolations&r", ""];
        foreach ($rows as [$check, $vl]) {
            $lines[] = "&7- " . self::vlColor($vl) . $check . "&7: &f" . $vl;
        }
        if (count($violations) > count($rows)) {
            $lines[] = "";
            $lines[] = "&8+" . (count($violations) - count($rows)) . " more counters hidden here.";
        }

        return C::colorize(implode("\n", $lines));
    }

    private static function lagTierColored(GuardLagResult $lag): string {
        return match ($lag->tier) {
            GuardLagResult::TIER_HARD => "&cUnstable",
            GuardLagResult::TIER_SOFT => "&eMild stress",
            default => "&aStable",
        };
    }

    private static function confidenceColor(string $confidence): string {
        return match (strtolower($confidence)) {
            "high" => "&a",
            "low" => "&c",
            default => "&e",
        };
    }

    private static function vlColor(int $vl): string {
        return match (true) {
            $vl >= 14 => "&c",
            $vl >= 8 => "&6",
            $vl >= 4 => "&e",
            default => "&a",
        };
    }
}
