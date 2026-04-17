<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\droppackages;

final class DropPackageRarityRoller {

    public static function roll(string $tier): string {
        $table = match($tier){
            "simple" => [
                "simple" => 80,
                "unique" => 15,
                "elite" => 4,
                "ultimate" => 1,
            ],
            "unique" => [
                "simple" => 60,
                "unique" => 25,
                "elite" => 10,
                "ultimate" => 4,
                "legendary" => 1,
            ],
            "elite" => [
                "simple" => 40,
                "unique" => 30,
                "elite" => 20,
                "ultimate" => 8,
                "legendary" => 2,
            ],
            "exotic" => [
                "simple" => 20,
                "unique" => 30,
                "elite" => 30,
                "ultimate" => 15,
                "legendary" => 5,
            ],
            "legendary" => [
                "simple" => 5,
                "unique" => 15,
                "elite" => 30,
                "ultimate" => 30,
                "legendary" => 20,
            ],
            "godly" => [
                "simple" => 1,
                "unique" => 5,
                "elite" => 15,
                "ultimate" => 30,
                "legendary" => 30,
            ],
            default => [
                "simple" => 100
            ]
        };

        $total = array_sum($table);
        $roll = mt_rand(1, $total);

        $current = 0;
        foreach($table as $rarity => $weight){
            $current += $weight;
            if($roll <= $current){
                return $rarity;
            }
        }

        return "simple";
    }
}