<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\enchants;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\Groups;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use ecstsy\Glacia\utils\screens\enchants\EnchantListScreen;
use function array_slice;
use function ceil;
use function count;
use function ksort;
use function max;
use function min;
use function preg_replace;
use function preg_split;
use function strcmp;
use function strip_tags;
use function trim;
use function usort;

final class ListSubCommand extends BaseSubCommand {

    private const ENCHANTMENTS_PER_PAGE = 15;

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument("page", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if ($sender instanceof Player) {
            EnchantListScreen::displayCategoryMenu($sender);
            return;
        }

        $page = isset($args["page"]) ? max(1, (int)$args["page"]) : 1;

        $allEnchantments = CustomEnchantments::getAll();
        $groupedEnchantments = [];

        foreach ($allEnchantments as $enchantment) {
            $groupId = $enchantment->getRarity();
            $groupedEnchantments[$groupId][] = $enchantment;
        }

        ksort($groupedEnchantments);

        $sortedList = [];
        foreach ($groupedEnchantments as $groupId => $enchantments) {
            usort($enchantments, static function($left, $right): int {
                $leftName = strip_tags(C::clean(CustomEnchantments::getEnchantmentDisplayName($left->getName(), "")));
                $rightName = strip_tags(C::clean(CustomEnchantments::getEnchantmentDisplayName($right->getName(), "")));
                return strcmp($leftName, $rightName);
            });

            foreach ($enchantments as $enchant) {
                $sortedList[] = $enchant;
            }
        }

        $totalEnchantments = count($sortedList);
        $totalPages = max(1, (int)ceil($totalEnchantments / self::ENCHANTMENTS_PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $start = ($page - 1) * self::ENCHANTMENTS_PER_PAGE;
        $end = min($start + self::ENCHANTMENTS_PER_PAGE, $totalEnchantments);
        $pageEntries = array_slice($sortedList, $start, self::ENCHANTMENTS_PER_PAGE);

        $sender->sendMessage(C::colorize("&r&6Custom Enchantments &7(Page &f{$page}&7/&f{$totalPages}&7)"));
        $sender->sendMessage(C::colorize("&r&7Showing &f" . ($start + 1) . "&7-&f" . $end . " &7of &f{$totalEnchantments}&7 enchants."));

        $currentGroupId = null;
        foreach ($pageEntries as $offset => $enchant) {
            $index = $start + $offset + 1;
            $groupId = $enchant->getRarity();
            if ($groupId !== $currentGroupId) {
                $currentGroupId = $groupId;
                $groupColor = Groups::translateGroupToColor($groupId);
                $groupName = Groups::getGroupName($groupId) ?? "Unknown";
                $sender->sendMessage(C::colorize("&r&8• " . $groupColor . $groupName . " &8•"));
            }

            $groupColor = Groups::translateGroupToColor($enchant->getRarity());
            $displayName = CustomEnchantments::getEnchantmentDisplayName($enchant->getName(), $groupColor);
            $sender->sendMessage(C::colorize("&r&8#&f{$index} &7" . $displayName));

            $descriptionPreview = self::formatDescriptionPreview($enchant->getDescription());
            if ($descriptionPreview !== "") {
                $sender->sendMessage(C::colorize("&r&8  -> &7" . $descriptionPreview));
            }
        }

        if ($page < $totalPages) {
            $sender->sendMessage(C::colorize("&r&7Use &f/ge list " . ($page + 1) . " &7for the next page."));
        }
    }

    public function getPermission(): ?string {
        return "glacia.list";
    }

    private static function formatDescriptionPreview(string $description): string
    {
        $description = trim($description);
        if ($description === "") {
            return "";
        }

        $lines = preg_split('/\r\n|\r|\n/', $description) ?: [$description];
        $line = trim((string) ($lines[0] ?? ""));
        $line = preg_replace('/\s+/', ' ', $line) ?? $line;
        if ($line === "") {
            return "";
        }

        return mb_strlen($line) > 72 ? mb_substr($line, 0, 69) . "..." : $line;
    }
}
