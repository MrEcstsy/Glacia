<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\args\RawStringArgument;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\redstone\RedstoneManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

use function array_key_exists;
use function array_keys;
use function implode;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function max;
use function round;
use function rtrim;
use function sprintf;
use function strtolower;
use function trim;

final class CannonConfigCommand extends BaseCommand
{
    /** @var array<string, string> */
    private const KEY_PATHS = [
        "fuse" => "dispenser.tnt-fuse-ticks",
        "cannonmode" => "dispenser.cannon-mode",
        "worksunderwater" => "dispenser.works-underwater",
        "currents" => "dispenser.primed-tnt-liquid-currents",
        "gravity" => "dispenser.gravity-multiplier",
        "waterdrag" => "dispenser.water-drag-multiplier",
        "stackvel" => "dispenser.primed-tnt-stack-velocity-per-extra-piece",
        "launchbase" => "dispenser.primed-tnt-launch-base",
        "launchh" => "dispenser.tnt-velocity.horizontal",
        "launchvb" => "dispenser.tnt-velocity.vertical-boost",
        "launchv" => "dispenser.tnt-velocity.vertical",
        "gravspeed" => "dispenser.primed-tnt-gravity-times-one-plus-velocity.enabled",
        "gravspeedk" => "dispenser.primed-tnt-gravity-times-one-plus-velocity.velocity-coefficient",
        "gravspeedmode" => "dispenser.primed-tnt-gravity-times-one-plus-velocity.velocity-mode",
        "gravspeedcurve" => "dispenser.primed-tnt-gravity-times-one-plus-velocity.curve",
        "propenabled" => "cannon-tnt-propulsion.enabled",
        "propdisponly" => "cannon-tnt-propulsion.only-dispenser-primed-targets",
        "propradius" => "cannon-tnt-propulsion.radius",
        "propfallmin" => "cannon-tnt-propulsion.minimum-falloff",
        "propfpad" => "cannon-tnt-propulsion.falloff-radius-pad",
        "proppower" => "cannon-tnt-propulsion.power",
        "propvertical" => "cannon-tnt-propulsion.vertical-boost",
        "propaddv" => "cannon-tnt-propulsion.additive-vertical-boost",
        "propvbfull" => "cannon-tnt-propulsion.vertical-boost-full",
        "propvbhit" => "cannon-tnt-propulsion.vertical-boost-scales-with-hit-strength",
        "propwater" => "cannon-tnt-propulsion.water-multiplier",
        "propmaxmotion" => "cannon-tnt-propulsion.max-motion",
        "propabsmax" => "cannon-tnt-propulsion.absolute-max-motion",
        "sandhd" => "sand-cobweb.horizontal-damp",
        "sandvd" => "sand-cobweb.vertical-damp",
        "sandstep" => "sand-cobweb.max-fall-per-tick",
        "sandnodrag" => "sand-cobweb.no-drag-while-inside",
    ];

    /** Alias → canonical key (see KEY_PATHS) */
    private const KEY_ALIASES = [
        "fuse" => "fuse",
        "cm" => "cannonmode",
        "cannon" => "cannonmode",
        "wu" => "worksunderwater",
        "uw" => "worksunderwater",
        "flow" => "currents",
        "streams" => "currents",
        "g" => "gravity",
        "wd" => "waterdrag",
        "drag" => "waterdrag",
        "water" => "waterdrag",
        "stack" => "stackvel",
        "stackbonus" => "stackvel",
        "lb" => "launchbase",
        "lh" => "launchh",
        "lvb" => "launchvb",
        "lv" => "launchv",
        "gs" => "gravspeed",
        "gsk" => "gravspeedk",
        "gsm" => "gravspeedmode",
        "gsc" => "gravspeedcurve",
        "gpv" => "gravspeed",
        "gpvk" => "gravspeedk",
        "gpvm" => "gravspeedmode",
        "gpvc" => "gravspeedcurve",
        "prop" => "propenabled",
        "handvanilla" => "propdisponly",
        "disponly" => "propdisponly",
        "radius" => "propradius",
        "fallmin" => "propfallmin",
        "fpad" => "propfpad",
        "power" => "proppower",
        "pv" => "propvertical",
        "addv" => "propaddv",
        "vbfull" => "propvbfull",
        "vbhit" => "propvbhit",
        "pwm" => "propwater",
        "maxmot" => "propmaxmotion",
        "maxmotion" => "propmaxmotion",
        "absmax" => "propabsmax",
        "cap" => "propabsmax",
        "sdh" => "sandhd",
        "sandh" => "sandhd",
        "sdv" => "sandvd",
        "sandv" => "sandvd",
        "sdf" => "sandstep",
        "sandfall" => "sandstep",
        "snd" => "sandnodrag",
        "sandfloat" => "sandnodrag",
        "cobweb" => "sandnodrag",
    ];

    /** @var array<string, string> */
    private const KEY_INFO = [
        "fuse" => "Dispenser primed TNT fuse (ticks). 20 = 1s. Higher = longer flight.",
        "cannonmode" => "Enables cannon gravity/drag/water-drag multipliers on dispenser primed TNT.",
        "worksunderwater" => "If true, primed TNT can detonate while submerged.",
        "currents" => "If true, dispenser primed TNT is pushed slightly by flowing water/lava (vanilla-like). False = straighter underwater shots.",
        "gravity" => "Scales gravity in cannon mode (PM base ~0.04). Higher = arcs drop faster.",
        "waterdrag" => "In cannon mode + touching water: scales drag. Higher = horizontal velocity dies faster.",
        "stackvel" => "Each extra TNT in the slot adds (n−1)×this to the launch speed multiplier (0 = no stack bonus).",
        "launchbase" => "Dispenser primed TNT axis speed (blocks/tick) before stack mult — feels dead if tiny; defaults ~0.09.",
        "launchh" => "Dispenser item eject horizontal (not primed TNT). Primed uses launchbase.",
        "launchvb" => "Extra upward boost added to horizontal launch vector.",
        "launchv" => "Vertical component of dispenser primed TNT launch.",
        "gravspeed" => "Gravity×speed tweak for dispenser primed TNT (needs cannon physics path). Off = ignore k/mode/curve.",
        "gravspeedk" => "k in gravity multiplier: inverse uses 1/(1+k×v), legacy uses 1+k×v. Higher k = stronger speed effect.",
        "gravspeedmode" => "Which speed to measure: xz (horizontal), xyz / total (3D length).",
        "gravspeedcurve" => "inverse = faster shot → less gravity (float). legacy / multiply / boost = faster → more drop.",
        "propenabled" => "When one primed TNT explodes, push nearby primed TNT / cannon sand.",
        "propdisponly" => "If true: propulsion skips hand-lit primed TNT (crater gaps like vanilla). Dispenser TNT + cannon sand still push.",
        "propradius" => "Max center distance for a hit. Must exceed target spacing (two-block gap ≈ 3b centers → use r≥4 or bump minimum-falloff).",
        "propfallmin" => "Extra floor on falloff after pad. Usually leave 0; prefer falloff-radius-pad for rim.",
        "propfpad" => "Outer reach = radius+pad for scan box, inclusion, and falloff (1−dist/reach). Default 0.4 fixes rim/diagonal gaps. 0 = strict r sphere.",
        "proppower" => "Per blast: target gets motion += push (strength ≈ power×falloff). Cannon volleys stack until cap/fuse. Tiny power ⇒ small each hit — need many hits or higher power for distance.",
        "propvertical" => "Extra +Y atop radial knock. Flat terrain: radial Y≈0 — this term dominates unless propaddv/propvbhit tune it.",
        "propaddv" => "If false: no extra +Y — only direction.y × strength (vanilla-flat ≈ purely horizontal radial).",
        "propvbfull" => "If true: always add full propvertical upward (repeatable). Ignored when propvbhit is on.",
        "propvbhit" => "If true: vertical add × min(1, hit strength / power) — avoids huge vertical when power is tiny.",
        "propwater" => "Multiplier on propulsion while touching water.",
        "propmaxmotion" => "Soft cap blocks/tick; &gt;0 min(with propabsmax). &lt;=0 = off (ceiling = propabsmax). Avoid tiny positives.",
        "propabsmax" => "Hard velocity ceiling — raise if high power hits an invisible wall.",
        "sandhd" => "Cobweb damping (primed TNT + Glacia mobs): horizontal x/z factor per tick (0–1). Falling sand ignores this.",
        "sandvd" => "Cobweb damping (primed TNT + Glacia mobs): vertical y factor per tick (0–1). Falling sand ignores this.",
        "sandstep" => "Cobweb damping (primed TNT + Glacia mobs): max downward blocks/tick in web. Falling sand ignores this.",
        "sandnodrag" => "Legacy/unused for sand. Falling sand has no custom cobweb handling.",
    ];

    /** @var list<string> */
    private const PROPULSION_KEYS = [
        "propenabled", "propdisponly", "propradius", "propfallmin", "propfpad", "proppower", "propvertical",
        "propaddv", "propvbfull", "propvbhit", "propwater",
        "propmaxmotion", "propabsmax",
    ];

    /** @var list<string> */
    private const SAND_KEYS = ["sandhd", "sandvd", "sandstep", "sandnodrag"];

    /** @var list<string> */
    private const BOOL_KEYS = ["cannonmode", "worksunderwater", "currents", "gravspeed", "propenabled", "propdisponly", "propaddv", "propvbfull", "propvbhit", "sandnodrag"];

    /** @var list<string> */
    private const FLOAT_KEYS = [
        "waterdrag", "gravity", "stackvel", "launchbase", "launchh", "launchvb", "launchv",
        "propradius", "propfallmin", "propfpad", "proppower", "propvertical", "propwater", "propmaxmotion", "propabsmax",
        "gravspeedk",
    ];

    /** String values (not bool/float) */
    private const STRING_KEYS = ["gravspeedmode", "gravspeedcurve"];

    /** @var list<string> */
    private const VERBS = ["list", "show", "dump", "all", "values", "info", "get", "set", "help"];

    private const KEY_LABEL_WIDTH = 16;

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("key", true));
        $this->registerArgument(2, new RawStringArgument("value", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $rawAction = trim((string) ($args["action"] ?? ""));
        $keyArg = strtolower(trim((string) ($args["key"] ?? "")));
        $rawValue = (string) ($args["value"] ?? "");

        $action = $rawAction === "" ? "show" : strtolower($rawAction);

        if (in_array($action, ["dump", "all", "values"], true)) {
            $action = "show";
        }

        if ($action === "?") {
            $action = "help";
        }

        // Shorthand: /cannoncfg wd  →  get waterdrag (single token that is a key or alias)
        if (!in_array($action, self::VERBS, true)) {
            $maybeKey = $this->canonicalKey($action);
            if ($maybeKey !== null && $keyArg === "") {
                $action = "get";
                $keyArg = $maybeKey;
            }
        }

        if ($action === "help") {
            $this->sendHelp($sender);
            return;
        }

        if ($action === "list") {
            $this->sendListGrouped($sender);
            return;
        }

        if ($action === "show") {
            $this->sendShow($sender);
            return;
        }

        if ($action === "info") {
            $this->sendInfo($sender, $keyArg);
            return;
        }

        if ($keyArg === "") {
            $sender->sendMessage(C::colorize("&r&cSpecify a key. &7/cannoncfg show &cor &fcannoncfg list"));
            return;
        }

        $key = $this->canonicalKey($keyArg);
        if ($key === null || !array_key_exists($key, self::KEY_PATHS)) {
            $sender->sendMessage(C::colorize("&r&cUnknown key &f{$keyArg}&c. &7/cannoncfg list"));
            return;
        }

        $config = $this->loadRedstoneConfig($sender);
        if ($config === null) {
            return;
        }

        $path = self::KEY_PATHS[$key];

        if ($action === "get") {
            $value = $config->getNested($path, null);
            $runtimeNote = "";
            if ($value === null) {
                $fallback = $this->resolveYamlRuntimeFallback($key);
                if ($fallback !== null) {
                    $value = $fallback;
                    $runtimeNote = " &8(missing in file — live default)";
                }
            }
            $sender->sendMessage(C::colorize(
                "&r&f{$key} &7= &b" . $this->formatConfigValue($value)
                . $runtimeNote
                . " &8(" . $path . ")"
            ));
            $hint = self::KEY_INFO[$key] ?? "";
            if ($hint !== "") {
                $sender->sendMessage(C::colorize("&r&7" . $hint));
            }
            return;
        }

        if ($action !== "set") {
            $sender->sendMessage(C::colorize("&r&cUnknown action &f{$rawAction}&c. &7/cannoncfg help"));
            return;
        }

        if ($rawValue === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: &f/cannoncfg set {$key} <value>"));
            return;
        }

        $value = $this->coerceValue($key, $rawValue);
        if ($value === null) {
            $hint = "";
            if (in_array($key, self::STRING_KEYS, true)) {
                $hint = $key === "gravspeedcurve"
                    ? " &7curve: &finverse&7|&flegacy&7|multiply|boost|old"
                    : " &7mode: &fxz&7|&fxyz&7|horizontal|total…";
            } elseif ($key === "sandhd" || $key === "sandvd") {
                $hint = " &7use &f0 &7– &f1";
            } elseif ($key === "sandstep") {
                $hint = " &7min &f0.001";
            }
            $sender->sendMessage(C::colorize(
                "&r&cInvalid value for &f{$key}&c. bool: &f1/0&c; fuse &f≥ 1&c; most numbers &f≥ 0&c." . $hint
            ));
            return;
        }

        $config->setNested($path, $value);
        $config->save();
        RedstoneManager::getInstance()->reloadConfig();

        $sender->sendMessage(C::colorize(
            "&r&a{$key} &7→ &b" . $this->formatConfigValue($value)
            . " &a(saved + redstone reload)"
        ));
    }

    private function sendHelp(CommandSender $sender): void
    {
        $sender->sendMessage(C::colorize("&r&6━━ &e/cc &7— &fredstone.yml &7· cannon TNT, propulsion, sand+cobweb"));
        $sender->sendMessage(C::colorize("&r&e/cc &7or &e/cc show &7→ tidy table of current values"));
        $sender->sendMessage(C::colorize("&r&e/cc set &f<key> <value> &7→ save file + reload &8(| &e/cc get &fkey &8| &e/cc info &fkey &8)"));
        $sender->sendMessage(C::colorize("&r&e/cc list &7→ keys grouped by topic + short aliases"));
        $sender->sendMessage(C::colorize("&r&7Cobweb tune (TNT+mobs):&f sandhd sandvd sandstep sandnodrag &7(&f/cc info sandhd&7)"));
    }

    private function sendShow(CommandSender $sender): void
    {
        $config = $this->loadRedstoneConfig($sender);
        if ($config === null) {
            return;
        }

        $sender->sendMessage(C::colorize("&r&6━━ &eCannon / redstone &6━━ &7(/cc)"));
        $sender->sendMessage(C::colorize("&r&8" . str_repeat("─", 42)));

        $sender->sendMessage(C::colorize("&r&e① Dispenser TNT &7— fuse, cannon mode, water, launch"));
        foreach (["fuse", "cannonmode", "worksunderwater", "currents", "gravity", "waterdrag", "stackvel", "launchbase"] as $k) {
            $this->sendShowLine($sender, $config, $k);
        }

        $sender->sendMessage(C::colorize("&r&e② Item eject &7— not primed TNT &8(use launchbase for TNT)"));
        foreach (["launchh", "launchvb", "launchv"] as $k) {
            $this->sendShowLine($sender, $config, $k);
        }

        $sender->sendMessage(C::colorize("&r&e③ TNT gravity × speed &7— fast shot float &8(gs gsk gsm gsc)"));
        foreach (["gravspeed", "gravspeedk", "gravspeedmode", "gravspeedcurve"] as $k) {
            $this->sendShowLine($sender, $config, $k);
        }

        $sender->sendMessage(C::colorize("&r&e④ Explosion propulsion &7— chain push on nearby TNT/sand"));
        foreach (self::PROPULSION_KEYS as $k) {
            $this->sendShowLine($sender, $config, $k);
        }

        $sender->sendMessage(C::colorize("&r&e⑤ Cobweb slowdown &7— primed TNT + mobs &8(/cc info sandhd)"));
        foreach (self::SAND_KEYS as $k) {
            $this->sendShowLine($sender, $config, $k);
        }

        $rm = RedstoneManager::getInstance();
        $effCap = $rm->getPrimedTntEffectiveMotionCap();
        $dispenserOnlyPush = $config->getNested("cannon-tnt-propulsion.only-dispenser-primed-targets", false);
        $sender->sendMessage(C::colorize("&r&8" . str_repeat("─", 42)));
        $sender->sendMessage(C::colorize("&r&7Resolved &fprimed-mot-cap &7= &b" . $this->formatConfigValue($effCap) . " &8bt/tick"));
        $sender->sendMessage(C::colorize("&r&7Each blast &fadds &7velocity until cap or detonation. &e/cc info proppower"));

        $ppLow = $rm->getCannonTntPropulsionPower();
        if ($ppLow > 0.0 && $ppLow < 1.5) {
            $rad = max(0.5, $rm->getCannonTntPropulsionRadius());
            $falloffRough = max(0.0, min(1.0, 1.0 - (2.0 / $rad)));
            $dvRough = $ppLow * $falloffRough;
            $sender->sendMessage(C::colorize(
                "&r&c! &7Low &fproppower&7 → ~&f" . $this->formatConfigValue((float) $dvRough)
                . " &7Δv/tick at ~2b — try &f/cc set proppower 20&7+"
            ));
        }
        if ($dispenserOnlyPush === true || $dispenserOnlyPush === 1 || $dispenserOnlyPush === "true") {
            $sender->sendMessage(C::colorize("&r&c! &7Hand-lit chains:&f propdisponly &coff&7 for Glacia push — &f/cc set propdisponly 0"));
        }
        $sender->sendMessage(C::colorize("&r&7Water flow on TNT:&f /cc set currents 1"));
    }

    private function sendListGrouped(CommandSender $sender): void
    {
        $sender->sendMessage(C::colorize("&r&6━━ /cc keys ━━ &7" . count(self::KEY_PATHS) . " total"));
        $line = static fn(string $label, array $keys): string => "&r&e{$label} &8│ &f" . implode("&7, &f", $keys);

        $sender->sendMessage(C::colorize($line("① TNT dispenser", ["fuse", "cannonmode", "worksunderwater", "currents", "gravity", "waterdrag", "stackvel", "launchbase"])));
        $sender->sendMessage(C::colorize($line("② Item eject", ["launchh", "launchvb", "launchv"])));
        $sender->sendMessage(C::colorize($line("③ Grav×speed", ["gravspeed", "gravspeedk", "gravspeedmode", "gravspeedcurve"])));
        $sender->sendMessage(C::colorize($line("④ Propulsion", self::PROPULSION_KEYS)));
        $sender->sendMessage(C::colorize($line("⑤ Sand+cobweb", self::SAND_KEYS)));

        $sender->sendMessage(C::colorize("&r&8── Aliases (short → key) ──"));
        $sender->sendMessage(C::colorize("&r&f" . $this->formatAliasSummary()));
        $sender->sendMessage(C::colorize("&r&7/cc show  ·  /cc set key val  ·  /cc get key  ·  /cc info key"));
    }

    private function sendShowLine(CommandSender $sender, Config $config, string $key): void
    {
        $path = self::KEY_PATHS[$key];
        $val = $config->getNested($path, null);
        $suffix = "";
        if ($val === null) {
            $fallback = $this->resolveYamlRuntimeFallback($key);
            if ($fallback !== null) {
                $val = $fallback;
                $suffix = " &8(def)";
            }
        }
        $sender->sendMessage(C::colorize(
            "&r &f" . str_pad($key, self::KEY_LABEL_WIDTH) . " &7= &b" . $this->formatConfigValue($val) . $suffix
        ));
    }

    /** When redstone.yml omits a nested key, PM Config returns null — reflect live ReloadConfig defaults. */
    private function resolvePropulsionRuntimeDisplay(string $key): string|float|bool|int|null
    {
        $rm = RedstoneManager::getInstance();

        return match ($key) {
            "propenabled" => $rm->isCannonTntPropulsionEnabled(),
            "propdisponly" => $rm->isCannonTntPropulsionOnlyDispenserPrimedTargets(),
            "propradius" => $rm->getCannonTntPropulsionRadius(),
            "propfallmin" => $rm->getCannonTntPropulsionMinFalloff(),
            "propfpad" => $rm->getCannonTntPropulsionFalloffRadiusPad(),
            "proppower" => $rm->getCannonTntPropulsionPower(),
            "propvertical" => $rm->getCannonTntPropulsionVerticalBoost(),
            "propaddv" => $rm->isCannonTntPropulsionAdditiveVerticalBoostEnabled(),
            "propvbfull" => $rm->isCannonTntPropulsionVerticalBoostFull(),
            "propvbhit" => $rm->isCannonTntPropulsionVerticalBoostScalesWithHitStrength(),
            "propwater" => $rm->getCannonTntPropulsionWaterMultiplier(),
            "propmaxmotion" => $rm->getCannonTntPropulsionMaxMotion(),
            "propabsmax" => $rm->getCannonTntPropulsionAbsoluteMaxMotion(),
            default => null,
        };
    }

    /** When redstone.yml omits nested dispenser gravity×speed keys, show live reload defaults. */
    private function resolveDispenserRuntimeDisplay(string $key): string|float|bool|int|null
    {
        $rm = RedstoneManager::getInstance();

        return match ($key) {
            "gravspeed" => $rm->isDispenserPrimedGravityOnePlusVelocityEnabled(),
            "gravspeedk" => $rm->getDispenserPrimedGravityVelocityCoeff(),
            "gravspeedmode" => $rm->getDispenserPrimedGravityVelocityMode(),
            "gravspeedcurve" => $rm->getDispenserPrimedGravityVelocityCurve(),
            default => null,
        };
    }

    private function resolveSandRuntimeDisplay(string $key): string|float|bool|int|null
    {
        $rm = RedstoneManager::getInstance();

        return match ($key) {
            "sandhd" => $rm->getSandCobwebHorizontalDamp(),
            "sandvd" => $rm->getSandCobwebVerticalDamp(),
            "sandstep" => $rm->getSandCobwebMaxFallPerTick(),
            "sandnodrag" => $rm->isSandCobwebNoDragWhileInsideEnabled(),
            default => null,
        };
    }

    private function resolveYamlRuntimeFallback(string $key): string|float|bool|int|null
    {
        return $this->resolvePropulsionRuntimeDisplay($key)
            ?? $this->resolveDispenserRuntimeDisplay($key)
            ?? $this->resolveSandRuntimeDisplay($key);
    }

    private function sendInfo(CommandSender $sender, string $keyArg): void
    {
        if ($keyArg === "") {
            foreach (self::KEY_PATHS as $k => $_) {
                $desc = self::KEY_INFO[$k] ?? "";
                $sender->sendMessage(C::colorize("&r&e{$k}&7: &f{$desc}"));
            }
            return;
        }

        $key = $this->canonicalKey($keyArg);
        if ($key === null || !array_key_exists($key, self::KEY_PATHS)) {
            $sender->sendMessage(C::colorize("&r&cUnknown key &f{$keyArg}"));
            return;
        }

        $sender->sendMessage(C::colorize("&r&e{$key} &8→ &7" . self::KEY_PATHS[$key]));
        $sender->sendMessage(C::colorize("&r&f" . (self::KEY_INFO[$key] ?? "No description.")));
    }

    private function loadRedstoneConfig(CommandSender $sender): ?Config
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "redstone.yml");
        if ($config === null) {
            $sender->sendMessage(C::colorize("&r&cCould not load redstone.yml"));
        }

        return $config;
    }

    private function canonicalKey(string $raw): ?string
    {
        $s = strtolower(trim($raw));
        if ($s === "") {
            return null;
        }

        if (array_key_exists($s, self::KEY_PATHS)) {
            return $s;
        }

        return self::KEY_ALIASES[$s] ?? null;
    }

    private function formatAliasSummary(): string
    {
        $parts = [];
        foreach (self::KEY_ALIASES as $alias => $target) {
            if ($alias === $target) {
                continue;
            }
            $parts[] = "{$alias}→{$target}";
        }

        return implode("&7, &f", $parts);
    }

    private function formatConfigValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? "true" : "false";
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            $v = round($value, 8);
            if (abs($v - (float) (int) $v) < 1e-9) {
                return (string) (int) round($v);
            }

            return rtrim(rtrim(sprintf("%.6f", $v), "0"), ".");
        }

        return $value === null ? "null" : (string) $value;
    }

    private function coerceValue(string $key, string $raw): int|float|bool|null
    {
        if ($key === "fuse") {
            return is_numeric($raw) ? max(1, (int) $raw) : null;
        }

        if ($key === "sandhd" || $key === "sandvd") {
            return is_numeric($raw) ? max(0.0, min(1.0, (float) $raw)) : null;
        }

        if ($key === "sandstep") {
            return is_numeric($raw) ? max(0.001, (float) $raw) : null;
        }

        if (in_array($key, self::BOOL_KEYS, true)) {
            return $this->parseBool($raw);
        }

        if (in_array($key, self::FLOAT_KEYS, true)) {
            return is_numeric($raw) ? max(0.0, (float) $raw) : null;
        }

        if (in_array($key, self::STRING_KEYS, true)) {
            return $this->coerceStringKey($key, $raw);
        }

        return null;
    }

    private function coerceStringKey(string $key, string $raw): ?string
    {
        $t = strtolower(trim($raw));
        if ($t === "") {
            return null;
        }

        if ($key === "gravspeedcurve") {
            $allowed = ["inverse", "legacy", "multiply", "boost", "old"];
            if (!in_array($t, $allowed, true)) {
                return null;
            }

            return $t;
        }

        if ($key === "gravspeedmode") {
            $allowed = ["xz", "horizontal", "2d", "flat", "xyz", "total", "3d", "length"];
            if (!in_array($t, $allowed, true)) {
                return null;
            }

            return $t;
        }

        return null;
    }

    private function parseBool(string $value): ?bool
    {
        return match (strtolower(trim($value))) {
            "1", "true", "yes", "y", "on", "enable", "enabled" => true,
            "0", "false", "no", "n", "off", "disable", "disabled" => false,
            default => null,
        };
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
