<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\enchants;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\Groups;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class InfoSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new TextArgument("enchantment", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $input = $args["enchantment"] ?? null;
        if ($input === null || trim((string) $input) === "") {
            $sender->sendMessage(C::colorize("&cUsage: /ge info <enchantment>"));
            return;
        }

        $enchantment = CustomEnchantments::getEnchantmentByName((string) $input);
        if (!$enchantment instanceof CustomEnchantment) {
            $sender->sendMessage(C::colorize(str_replace("{enchant}", (string) $input, "&c{enchant} is not a valid enchant!")));
            return;
        }

        $internalId = $enchantment->getName();
        $groupId = $enchantment->getRarity();
        $groupName = Groups::getGroupName($groupId);
        $groupColor = Groups::translateGroupToColor($groupId);
        $displayName = CustomEnchantments::getEnchantmentDisplayName($internalId, $groupColor);

        $sender->sendMessage(C::colorize("&8&m                                              "));
        $sender->sendMessage(C::colorize("&r&e» &r" . $displayName . " &7(" . $groupColor . $groupName . "&7)"));
        $sender->sendMessage(C::colorize("&r&7Internal ID: &f" . $internalId));
        $sender->sendMessage(C::colorize("&r&7Max Level: &f" . $enchantment->getMaxLevel()));

        $applies = self::formatApplicableItems($enchantment->getApplicableItems());
        $sender->sendMessage(C::colorize("&r&7Applies To: &f" . $applies));

        $sender->sendMessage(C::colorize("&r&7Description:"));
        $description = $enchantment->getDescription();
        $lines = preg_split('/\r\n|\r|\n/', $description) ?: [$description];
        foreach ($lines as $line) {
            $sender->sendMessage(C::colorize("&r&f  " . $line));
        }
        $sender->sendMessage(C::colorize("&8&m                                              "));
    }

    public function getPermission(): string {
        return "glacia.info";
    }

    /**
     * Convert ItemEnchantmentTags strings (e.g. "pickaxe", "flint_and_steel") into
     * a human readable, comma-separated list.
     *
     * @param string[] $tags
     */
    private static function formatApplicableItems(array $tags): string {
        if ($tags === []) {
            return "None";
        }

        $pretty = [];
        foreach ($tags as $tag) {
            $pretty[] = ucwords(str_replace('_', ' ', (string) $tag));
        }

        return implode(", ", $pretty);
    }
}
