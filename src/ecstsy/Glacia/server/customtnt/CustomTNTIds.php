<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\customtnt;

final class CustomTNTIds {

    /** Plain primed TNT (vanilla ignite or dispenser) — not a variant item id. */
    public const PROFILE_NORMAL = "normal";

    public const GIGANTIC = "tnt_gigantic";
    public const LETHAL = "tnt_lethal";
    public const LUCKY = "tnt_lucky";
    public const TACTICAL = "tnt_tactical";

    public static function isCustomTNT(string $id): bool {
        return in_array($id, [
            self::GIGANTIC,
            self::LETHAL,
            self::LUCKY,
            self::TACTICAL
        ], true);
    }
}