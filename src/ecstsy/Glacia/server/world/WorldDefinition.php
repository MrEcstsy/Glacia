<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\world;

final class WorldDefinition {

    public function __construct(
        public string $name,
        public string $generator,
        public ?string $seed,
        public string $preset,
        public bool $autoLoad,
        public bool $autoGenerate,
        /** @var array<string, bool|int|string|float> */
        public array $gamerules = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(string $name, array $data): self {
        $generator = (string) ($data["generator"] ?? "normal");
        $seedRaw = $data["seed"] ?? null;
        $seed = $seedRaw === null ? null : (string) $seedRaw;
        $preset = (string) ($data["preset"] ?? "");
        $autoLoad = (bool) ($data["auto-load"] ?? true);
        $autoGenerate = (bool) ($data["auto-generate"] ?? true);
        $gamerules = [];
        foreach ((array) ($data["gamerules"] ?? []) as $rule => $value) {
            if (!is_string($rule) || trim($rule) === "") {
                continue;
            }
            if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
                $gamerules[strtolower(trim($rule))] = $value;
            }
        }

        return new self(
            name: $name,
            generator: self::normalizeGenerator($generator),
            seed: $seed,
            preset: $preset,
            autoLoad: $autoLoad,
            autoGenerate: $autoGenerate,
            gamerules: $gamerules
        );
    }

    public static function normalizeGenerator(string $generator): string {
        return match (strtolower(trim($generator))) {
            "default", "normal" => "normal",
            "overworld", "vanilla", "vanilla_overworld" => "vanilla_overworld",
            "vanilla_nether" => "vanilla_nether",
            "nether", "hell" => "nether",
            "flat", "superflat" => "flat",
            "end", "the_end" => "end",
            default => strtolower(trim($generator)),
        };
    }
}
