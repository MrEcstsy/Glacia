<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;

final class ConfigDoctor {

    /**
     * @param string[] $files
     * @return array{created:list<string>, validated:list<string>, issues:list<string>}
     */
    public static function repairAndValidate(Loader $loader, array $files): array {
        $created = [];
        $validated = [];
        $issues = [];

        foreach ($files as $file) {
            if (GeneralUtils::syncResourceFile($loader, $file, false)) {
                $created[] = $file;
            }

            GeneralUtils::forgetConfiguration($loader, $file);
            $config = GeneralUtils::getConfiguration($loader, $file);
            if ($config === null) {
                $issues[] = $file;
                continue;
            }

            $validated[] = $file;
        }

        return [
            "created" => $created,
            "validated" => $validated,
            "issues" => $issues,
        ];
    }
}
