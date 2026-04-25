<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class BlessCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $removed = GlaciaUtils::bless($sender);
        if ($removed === []) {
            $sender->sendMessage(C::colorize("&r&c✘ &r&7You have no negative effects to cleanse."));
            return;
        }

        $effects = array_map(
            fn(EffectInstance $effect): string => $sender->getServer()->getLanguage()->translate($effect->getType()->getName()) . " " . self::toRoman($effect->getAmplifier() + 1),
            $removed
        );

        $sender->sendMessage(C::colorize("&r&a✔ &r&7Cleansed &f" . implode("&7, &f", $effects) . "&7."));
    }

    public function getPermission(): string {
        return "glacia.bless";
    }

    private static function toRoman(int $value): string
    {
        $map = [
            10 => "X",
            9 => "IX",
            5 => "V",
            4 => "IV",
            1 => "I"
        ];

        $roman = "";
        foreach ($map as $number => $glyph) {
            while ($value >= $number) {
                $roman .= $glyph;
                $value -= $number;
            }
        }

        return $roman !== "" ? $roman : "I";
    }
}
