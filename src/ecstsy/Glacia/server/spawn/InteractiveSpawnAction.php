<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\spawn;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\floatingtext\FloatingTextManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\screens\enchanter\EnchanterFormScreen;
use ecstsy\Glacia\utils\screens\enchanter\EnchanterScreen;
use ecstsy\Glacia\utils\screens\quests\QuestShopScreen;
use ecstsy\Glacia\utils\screens\quests\QuestsScreen;
use ecstsy\Glacia\utils\screens\blackmarket\BlackMarketScreen;
use ecstsy\Glacia\utils\screens\shop\ShopFormScreen;
use ecstsy\Glacia\utils\screens\shop\ShopScreen;
use ecstsy\Glacia\utils\screens\slotbot\SlotbotMenuScreen;
use pocketmine\player\Player;
use pocketmine\Server;

final class InteractiveSpawnAction
{
    /** @var array<string, string> */
    private const FLOATING_TEXT_TEMPLATES = [
        "slotbot" => "slotbot_corner",
        "slots" => "slotbot_corner",
        "shop" => "shop_hub",
        "blackmarket" => "black_market",
        "black_market" => "black_market",
        "bm" => "black_market",
        "bah" => "black_market",
        "enchanter" => "enchanting_hub",
        "ce" => "enchanting_hub",
        "enchants" => "enchanting_hub",
        "quest" => "quest_board",
        "quests" => "quest_board",
        "questshop" => "quest_board",
        "quest_shop" => "quest_board",
        "qshop" => "quest_board",
        "duel" => "duel_arena",
        "duels" => "duel_arena",
        "freefixall" => "fixall_station",
        "fixallfree" => "fixall_station",
        "repair" => "fixall_station",
    ];

    /** @var array<string, string> */
    private const SLAPPER_LAST_LINES = [
        "slotbot" => "&r&7Right-click to &eplay",
        "slots" => "&r&7Right-click to &eplay",
        "shop" => "&r&7Right-click to &aopen the shop",
        "blackmarket" => "&r&7Right-click to &eopen the black market",
        "black_market" => "&r&7Right-click to &eopen the black market",
        "bm" => "&r&7Right-click to &eopen the black market",
        "bah" => "&r&7Right-click to &eopen the black market",
        "enchanter" => "&r&7Right-click to &denchant",
        "ce" => "&r&7Right-click to &denchant",
        "enchants" => "&r&7Right-click to &denchant",
        "quest" => "&r&7Right-click for &bquests",
        "quests" => "&r&7Right-click for &bquests",
        "questshop" => "&r&7Right-click for &6quest shop",
        "quest_shop" => "&r&7Right-click for &6quest shop",
        "qshop" => "&r&7Right-click for &6quest shop",
        "duel" => "&r&7Right-click to &cduel",
        "duels" => "&r&7Right-click to &cduel",
        "freefixall" => "&r&7Right-click to &arepair gear",
        "fixallfree" => "&r&7Right-click to &arepair gear",
        "repair" => "&r&7Right-click to &arepair gear",
    ];

    public static function execute(Player $player, string $action): bool
    {
        $action = trim($action);
        if ($action === "") {
            return false;
        }

        $normalized = strtolower($action);
        $player->removeCurrentWindow();

        switch ($normalized) {
            case "slotbot":
            case "slots":
                (new SlotbotMenuScreen($player))->display($player);
                return true;

            case "shop":
                $session = Loader::getPlayerManager()->getSession($player);
                if ($session !== null && $session->getSetting("chest_inventories") === false) {
                    ShopFormScreen::display($player);
                } else {
                    (new ShopScreen())->display($player);
                }
                return true;

            case "blackmarket":
            case "black_market":
            case "bm":
            case "bah":
                BlackMarketScreen::display($player);
                return true;

            case "enchanter":
            case "ce":
            case "enchants":
                $session = Loader::getPlayerManager()->getSession($player);
                if ($session !== null && $session->getSetting("chest_inventories") === false) {
                    EnchanterFormScreen::display($player);
                } else {
                    (new EnchanterScreen())->display($player);
                }
                return true;

            case "quest":
            case "quests":
                QuestsScreen::display($player);
                return true;

            case "questshop":
            case "quest_shop":
            case "qshop":
                QuestShopScreen::display($player);
                return true;

            case "duel":
            case "duels":
                Server::getInstance()->dispatchCommand($player, "duel");
                return true;

            case "freefixall":
            case "fixallfree":
            case "repair":
                $fixed = GlaciaUtils::repairAll($player);
                $player->sendMessage(GlaciaUtils::format($fixed ? "fixall.success" : "fixall.no_damaged", [], $fixed ? "success" : "error"));
                return true;
        }

        $command = $action;
        if (str_starts_with($normalized, "cmd:")) {
            $command = trim(substr($action, 4));
        } elseif (str_starts_with($action, "/")) {
            $command = substr($action, 1);
        }

        if ($command === "") {
            return false;
        }

        Server::getInstance()->dispatchCommand($player, $command);
        return true;
    }

    public static function suggestLabel(string $action): string
    {
        $normalized = strtolower(trim($action));
        $templateId = self::FLOATING_TEXT_TEMPLATES[$normalized] ?? null;
        if ($templateId !== null && FloatingTextManager::hasTemplate($templateId)) {
            $label = FloatingTextManager::formatStaticTemplate(
                $templateId,
                self::SLAPPER_LAST_LINES[$normalized] ?? "&r&7Right-click to use"
            );
            if ($label !== "") {
                return $label;
            }
        }

        return match ($normalized) {
            "slotbot", "slots" => "&r&e&lSLOT BOT\n&r&7Try your luck for stacked rewards.\n&r&7Right-click to &eplay",
            "shop" => "&r&b&lGLACIA &r&fShop\n&r&7Buy gear, blocks, and supplies.\n&r&7Right-click to &aopen the shop",
            "blackmarket", "black_market", "bm", "bah" => "&r&b&lGLACIA &r&fBlack Market\n&r&7Rotating stock. Limited offers.\n&r&7Right-click to &eopen the black market",
            "enchanter", "ce", "enchants" => "&r&5&lENCHANTING\n&r&7Custom books, conduits, gems,\n&r&7Right-click to &denchant",
            "quest", "quests" => "&r&d&lQUEST BOARD\n&r&7Pick up rotating objectives.\n&r&7Right-click for &bquests",
            "questshop", "quest_shop", "qshop" => "&r&d&lQUEST SHOP\n&r&7Spend quest points here.\n&r&7Right-click to &6open",
            "duel", "duels" => "&r&c&lDUEL ARENA\n&r&7Settle fights without losing spawn flow.\n&r&7Right-click to &cduel",
            "freefixall", "fixallfree", "repair" => "&r&a&lREPAIR STATION\n&r&7Repair all gear for free.\n&r&7Right-click to &arepair",
            default => "&r&b&lINTERACTIVE\n&r&7Right-click to use",
        };
    }
}
