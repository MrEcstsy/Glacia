<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use muqsit\arithmexp\expression\Expression;
use muqsit\arithmexp\Parser;
use Throwable;

final class EnchantTuningMath
{
    private static ?Parser $parser = null;
    /** @var array<string, Expression> */
    private static array $compiled = [];

    public static function clearCache(): void
    {
        self::$compiled = [];
        self::$parser = null;
    }

    public static function resolveNumber(
        mixed $definition,
        int $level,
        float $default = 0.0,
        ?callable $warningSink = null,
        string $path = "value"
    ): float {
        if ($definition === null) {
            $warningSink?->__invoke($path, $default, "missing");
            return $default;
        }

        if (is_int($definition) || is_float($definition)) {
            return (float) $definition;
        }

        if (is_string($definition)) {
            $trimmed = trim($definition);
            if ($trimmed === "") {
                $warningSink?->__invoke($path, $default, "empty-string");
                return $default;
            }
            if (is_numeric($trimmed)) {
                return (float) $trimmed;
            }

            return self::evaluateExpression(
                $trimmed,
                ["level" => $level],
                $default,
                $warningSink,
                $path
            );
        }

        if (!is_array($definition)) {
            $warningSink?->__invoke($path, $default, "invalid-type:" . gettype($definition));
            return $default;
        }

        if (array_key_exists("value", $definition)) {
            $default = self::resolveNumber($definition["value"], $level, $default, $warningSink, $path . ".value");
        }

        $hasBase = array_key_exists("base", $definition);
        $hasPerLevel = array_key_exists("per-level", $definition)
            || array_key_exists("perLevel", $definition)
            || array_key_exists("per_level", $definition);

        $base = self::readScalarFloat($definition, ["base"], 0.0, $warningSink, $path . ".base");
        $perLevel = self::readScalarFloat($definition, ["per-level", "perLevel", "per_level"], 0.0, $warningSink, $path . ".per-level");
        $startLevel = max(1, (int) self::readScalarFloat($definition, ["start-level", "startLevel", "start_level"], 1.0, $warningSink, $path . ".start-level"));
        $scaled = $hasBase || $hasPerLevel ? $base + (max(0, $level - $startLevel) * $perLevel) : $default;

        $formula = self::readFormula($definition);
        $resolved = $formula !== null
            ? self::evaluateExpression(
                $formula,
                [
                    "level" => $level,
                    "base" => $base,
                    "per_level" => $perLevel,
                    "perLevel" => $perLevel,
                    "start_level" => $startLevel,
                    "startLevel" => $startLevel,
                    "scaled" => $scaled
                ],
                $scaled,
                $warningSink,
                $path . ".formula"
            )
            : $scaled;

        if (array_key_exists("min", $definition)) {
            $resolved = max(
                self::resolveNumber($definition["min"], $level, $resolved, $warningSink, $path . ".min"),
                $resolved
            );
        }

        if (array_key_exists("max", $definition)) {
            $resolved = min(
                self::resolveNumber($definition["max"], $level, $resolved, $warningSink, $path . ".max"),
                $resolved
            );
        }

        if (array_key_exists("round", $definition)) {
            $resolved = round($resolved, max(0, (int) $definition["round"]));
        }

        return $resolved;
    }

    public static function evaluateExpression(
        string $expression,
        array $variables,
        float $default = 0.0,
        ?callable $warningSink = null,
        string $path = "formula"
    ): float {
        $trimmed = trim($expression);
        if ($trimmed === "") {
            $warningSink?->__invoke($path, $default, "empty-formula");
            return $default;
        }

        try {
            $compiled = self::$compiled[$trimmed] ??= self::getParser()->parse($trimmed);
            $result = $compiled->evaluate($variables);
            if (is_bool($result)) {
                return $result ? 1.0 : 0.0;
            }
            if (is_int($result) || is_float($result)) {
                return (float) $result;
            }
        } catch (Throwable $throwable) {
            $warningSink?->__invoke($path, $default, "formula-error:" . $throwable->getMessage());
            return $default;
        }

        $warningSink?->__invoke($path, $default, "formula-result-invalid");
        return $default;
    }

    private static function readFormula(array $definition): ?string
    {
        foreach (["formula", "expression", "expr"] as $key) {
            if (!array_key_exists($key, $definition)) {
                continue;
            }

            $value = trim((string) $definition[$key]);
            if ($value !== "") {
                return $value;
            }
        }

        return null;
    }

    private static function readScalarFloat(
        array $definition,
        array $keys,
        float $default,
        ?callable $warningSink,
        string $path
    ): float {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $definition)) {
                continue;
            }

            $value = $definition[$key];
            if (is_int($value) || is_float($value)) {
                return (float) $value;
            }
            if (is_string($value) && is_numeric(trim($value))) {
                return (float) trim($value);
            }
        }

        return $default;
    }

    private static function getParser(): Parser
    {
        return self::$parser ??= Parser::createDefault();
    }
}
