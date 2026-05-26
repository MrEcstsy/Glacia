<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\rename;

use ecstsy\Glacia\server\factions\FactionNameBlocklist;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ItemRenameManager {

    private const STAGE_AWAITING_NAME = "awaiting_name";
    private const STAGE_AWAITING_CONFIRM = "awaiting_confirm";
    private const MAX_VISIBLE_LENGTH = 32;

    /** @var array<string, array{stage: string, preview?: string, slot?: int, refundPending: bool}> */
    private static array $sessions = [];

    public static function start(Player $player): void
    {
        if (self::hasSession($player)) {
            $player->sendMessage(C::colorize("&r&cYou are already in a rename session. Type &acancel &r&cto get your tag back."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $tag = $player->getInventory()->getItemInHand();
        if (!GlaciaItemFactory::isGlaciaItem($tag, "renametag")) {
            $player->sendMessage(C::colorize("&r&cYou must be holding a rename tag to do that."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $tag->pop();
        $player->getInventory()->setItemInHand($tag);

        self::$sessions[$player->getUniqueId()->toString()] = [
            "stage" => self::STAGE_AWAITING_NAME,
            "refundPending" => true
        ];

        $player->sendMessage(C::colorize("&r&6&l    Itemtag Usage"));
        $player->sendMessage(C::colorize("&r&61. &7Hold the ITEM you'd like to rename."));
        $player->sendMessage(C::colorize("&r&62. &7Send the new name as a chat message with & color codes."));
        $player->sendMessage(C::colorize("&r&63. &7Confirm the preview of the new name that is displayed."));
        PlayerUtils::playSound($player, "random.orb");
    }

    public static function hasSession(Player $player): bool
    {
        return isset(self::$sessions[$player->getUniqueId()->toString()]);
    }

    public static function clear(Player $player): void
    {
        unset(self::$sessions[$player->getUniqueId()->toString()]);
    }

    public static function abort(Player $player, bool $notify = false): void
    {
        $session = self::$sessions[$player->getUniqueId()->toString()] ?? null;
        if ($session === null) {
            return;
        }

        self::refundTag($player, $session["refundPending"] ?? false);
        self::clear($player);

        if ($notify) {
            $player->sendMessage(C::colorize("&r&cRename cancelled."));
            PlayerUtils::playSound($player, "note.bass");
        }
    }

    public static function handleChat(Player $player, string $message): bool
    {
        $key = $player->getUniqueId()->toString();
        $session = self::$sessions[$key] ?? null;
        if ($session === null) {
            return false;
        }

        $message = trim(str_replace(["\n", "\r"], " ", $message));
        $lower = strtolower($message);

        if ($lower === "cancel") {
            self::abort($player, true);
            return true;
        }

        if (($session["stage"] ?? self::STAGE_AWAITING_NAME) === self::STAGE_AWAITING_NAME) {
            if ($lower === "confirm") {
                $player->sendMessage(C::colorize("&r&cType a new name first, or type &lccancel&r&c to stop."));
                PlayerUtils::playSound($player, "note.bass");
                return true;
            }

            $target = $player->getInventory()->getItemInHand();
            $validation = self::validateTargetItem($target);
            if ($validation !== null) {
                $player->sendMessage($validation);
                PlayerUtils::playSound($player, "note.bass");
                return true;
            }

            $colored = C::colorize($message);
            $clean = trim(C::clean($colored));

            if ($clean === "") {
                $player->sendMessage(C::colorize("&r&cThat name is invalid. Try something visible."));
                PlayerUtils::playSound($player, "note.bass");
                return true;
            }

            if (strlen($clean) > self::MAX_VISIBLE_LENGTH) {
                $player->sendMessage(C::colorize("&r&cThat name is too long. Keep it under " . self::MAX_VISIBLE_LENGTH . " visible characters."));
                PlayerUtils::playSound($player, "note.bass");
                return true;
            }

            $violation = FactionNameBlocklist::firstGenericTextViolation($clean, "item name", self::MAX_VISIBLE_LENGTH);
            if ($violation !== null) {
                $player->sendMessage(C::colorize("&r&c" . $violation));
                PlayerUtils::playSound($player, "note.bass");
                return true;
            }

            self::$sessions[$key] = [
                "stage" => self::STAGE_AWAITING_CONFIRM,
                "preview" => $colored,
                "slot" => $player->getInventory()->getHeldItemIndex(),
                "refundPending" => $session["refundPending"] ?? true
            ];

            $player->sendMessage(C::colorize("&r&l&e(!) &r&eItem Name Preview: ") . $colored);
            $player->sendMessage(C::colorize("&r&7Type &aconfirm &r&7if this looks correct, otherwise type &ccancel &r&7to start over."));
            PlayerUtils::playSound($player, "random.click");
            return true;
        }

        if ($lower !== "confirm") {
            $player->sendMessage(C::colorize("&r&7Type &aconfirm &r&7to apply the preview, or &ccancel &r&7to stop."));
            PlayerUtils::playSound($player, "note.bass");
            return true;
        }

        $target = $player->getInventory()->getItemInHand();
        $validation = self::validateTargetItem($target);
        if ($validation !== null) {
            self::refundTag($player, $session["refundPending"] ?? false);
            self::clear($player);
            $player->sendMessage($validation);
            PlayerUtils::playSound($player, "note.bass");
            return true;
        }

        $expectedSlot = $session["slot"] ?? $player->getInventory()->getHeldItemIndex();
        if ($player->getInventory()->getHeldItemIndex() !== $expectedSlot) {
            self::refundTag($player, $session["refundPending"] ?? false);
            self::clear($player);
            $player->sendMessage(C::colorize("&r&cYou changed hotbar slots. Your rename tag was returned."));
            PlayerUtils::playSound($player, "note.bass");
            return true;
        }

        $preview = (string) ($session["preview"] ?? "");
        $target->setCustomName($preview);
        $player->getInventory()->setItemInHand($target);

        self::clear($player);
        $player->sendMessage(C::colorize("&r&aYour item has been renamed to: ") . $preview);
        PlayerUtils::playSound($player, "random.anvil_use");
        return true;
    }

    private static function validateTargetItem(Item $item): ?string
    {
        if ($item->isNull()) {
            return C::colorize("&r&cHold the item you want to rename in your hand first.");
        }

        if (GlaciaItemFactory::isGlaciaItem($item, "renametag")) {
            return C::colorize("&r&cYou cannot rename the rename tag itself.");
        }

        if ($item->getCount() > 1) {
            return C::colorize("&r&cHold a single item before renaming it.");
        }

        return null;
    }

    private static function refundTag(Player $player, bool $shouldRefund): void
    {
        if (!$shouldRefund) {
            return;
        }

        $refund = GlaciaItemFactory::renametag();
        $inventory = $player->getInventory();
        if ($inventory->canAddItem($refund)) {
            $inventory->addItem($refund);
            return;
        }

        $player->getWorld()->dropItem($player->getPosition(), $refund);
    }
}
