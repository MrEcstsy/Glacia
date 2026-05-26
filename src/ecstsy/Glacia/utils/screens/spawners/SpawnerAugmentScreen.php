<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\spawners;

use ecstsy\Glacia\blocks\tiles\SpawnerTile;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;
use function number_format;
use function str_replace;
use function trim;
use function ucwords;

final class SpawnerAugmentScreen extends BaseScreen
{
    private SimpleForm $form;

    public function __construct(private readonly SpawnerTile $tile)
    {
        $this->form = new SimpleForm(function (Player $player, ?int $data): void {
            if ($data === null) {
                return;
            }

            if ($data === 2) {
                return;
            }

            $held = $player->getInventory()->getItemInHand();
            if (!GlaciaItemFactory::isGlaciaItem($held, "spawner_augment")) {
                $player->sendMessage(C::colorize("&r&cHold a Spawner Augment to apply it."));
                return;
            }

            $available = $held->getCount();
            $capacity = max(0, $this->tile->getMaxAugmentCount() - $this->tile->getAugmentCount());
            if ($available <= 0 || $capacity <= 0) {
                $player->sendMessage(C::colorize("&r&cThis spawner stack cannot take any more augments."));
                return;
            }

            $wanted = $data === 0 ? 1 : $available;
            $toApply = min($wanted, $available, $capacity);
            if ($toApply <= 0) {
                $player->sendMessage(C::colorize("&r&cNo augments were applied."));
                return;
            }

            $applied = $this->tile->addAugments($toApply);
            if ($applied <= 0) {
                $player->sendMessage(C::colorize("&r&cThis spawner stack is already fully augmented."));
                return;
            }

            if (!$player->isCreative()) {
                $held->setCount($held->getCount() - $applied);
                $player->getInventory()->setItemInHand($held->getCount() > 0 ? $held : \pocketmine\item\VanillaItems::AIR());
            }

            $player->sendMessage(C::colorize("&r&aApplied &f{$applied}&a Spawner Augment" . ($applied === 1 ? "" : "s") . "&a."));
        });

        $stack = $tile->getStackCount();
        $augments = $tile->getAugmentCount();
        $remaining = max(0, $tile->getMaxAugmentCount() - $augments);
        $baseEach = $tile->getSpawnerValue();
        $baseTotal = $baseEach * $stack;
        $bonusValue = $tile->getAugmentWorthBonus();
        $mob = self::formatSpawnerName($tile);

        $this->form->setTitle(C::colorize("&r&l&8{$mob} Augments"));
        $this->form->setContent(C::colorize(
            "&r&fStack Size: &7{$stack}\n" .
            "&r&fAugmented Spawners: &7{$augments}&f/&7{$stack}\n" .
            "&r&fAugment Slots Open: &7{$remaining}\n" .
            "&r&fBase /f top Value: &7$" . number_format($baseTotal) . "\n" .
            "&r&fBonus Value: &7+$" . number_format($bonusValue) . "\n" .
            "&r&fCurrent /f top Value: &7$" . number_format($this->tile->getFactionWorthContribution()) . "\n" .
            "&r&8Each augment adds &f25% &r&8of one spawner's value."
        ));

        $this->form->addButton(C::colorize("&r&bApply 1"));
        $this->form->addButton(C::colorize("&r&aApply From Held Stack"));
        $this->form->addButton(C::colorize("&r&8Cancel"));
    }

    public function getForm(): SimpleForm
    {
        return $this->form;
    }

    public static function display(Player $player, SpawnerTile $tile): void
    {
        if ($tile->isZoneSpawner()) {
            $player->sendMessage(C::colorize("&r&cZone spawners cannot be augmented."));
            return;
        }

        $player->sendForm((new self($tile))->getForm());
    }

    private static function formatSpawnerName(SpawnerTile $tile): string
    {
        $legacyId = $tile->getLegacyEntityId();
        $short = $legacyId > 0 ? GlaciaUtils::convertMobIdToName($legacyId) : "";
        if ($short !== "") {
            return ucwords(str_replace("_", " ", trim($short)));
        }

        $parts = explode(":", $tile->getEntityTypeId(), 2);
        $fallback = $parts[1] ?? $parts[0] ?? "Mob";
        return ucwords(str_replace("_", " ", trim($fallback)));
    }
}
