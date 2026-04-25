<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\kits;

use ecstsy\Glacia\server\gkits\GKitRepository;
use ecstsy\Glacia\server\kits\KitRepository;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class KitFormScreen {

    public static function displayRankKits(Player $player): void
    {
        self::display($player, "Rank Kits", KitRepository::getRankKits(), static function(Player $player, array $kit): bool {
            return KitRepository::claimKit($player, $kit);
        });
    }

    public static function displayGKits(Player $player): void
    {
        self::display($player, "Glacial GKits", GKitRepository::getGKits(), static function(Player $player, array $kit): bool {
            return GKitRepository::claim($player, $kit);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $kits
     */
    private static function display(Player $player, string $title, array $kits, callable $claimer): void
    {
        $form = new SimpleForm(function(Player $player, mixed $data) use ($kits, $claimer): void {
            if (!is_string($data) || !ctype_digit($data) || !isset($kits[(int) $data])) {
                return;
            }

            $claimer($player, $kits[(int) $data]);
        });

        $form->setTitle(C::colorize("&r&8{$title}"));
        $form->setContent(C::colorize("&r&8Choose a kit to claim."));

        foreach (array_values($kits) as $index => $kit) {
            $display = C::clean((string) ($kit["display"] ?? $kit["id"] ?? "Kit"));
            $count = count($kit["items"] ?? []) + max(0, (int) ($kit["random-rolls"] ?? 0));
            $form->addButton(
                C::colorize("&r&8{$display}\n&r&8{$count} items"),
                SimpleForm::IMAGE_TYPE_PATH,
                self::getIconPath((string) ($kit["id"] ?? $display)),
                (string) $index
            );
        }

        $player->sendForm($form);
    }

    private static function getIconPath(string $kitId): string
    {
        $key = strtolower(trim($kitId));
        $key = str_replace([" ", "-"], "_", $key);

        $map = [
            "starter" => "starter",
            "cryoshield" => "cryoshield",
            "winterborn" => "winterborn",
            "glacian" => "glacian",
            "iceheart" => "iceheart",
            "frostlord" => "frostlord",
            "soulrender" => "soulrender",
            "nightreign" => "nightreign",
            "skywarden" => "skywarden",
            "kyrostellar" => "kyrostellar",
            "kryostellar" => "kyrostellar",
            "astralmancer" => "astralmancer",
            "gravebound" => "gravebound",
            "everfrost" => "everfrost",
        ];

        $icon = $map[$key] ?? "starter";
        return "ui/icons/kits/{$icon}.png";
    }
}
