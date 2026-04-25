<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\tasks\CoinFlipTask;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class CoinFlipInstance {

    public static array $coinFlips = [];

    public const COLORS = [
        "Red" => "&c",
        "Yellow" => "&e",
        "Green" => "&a",
        "Blue" => "&9",
        "Purple" => "&5",
        "Orange" => "&6",
        "Pink" => "&d",
        "Cyan" => "&b"
    ];

    public function __construct(Loader $plugin)
    {
        $this->loadCoinFlips();
    }

    private function loadCoinFlips(): void
    {
        Loader::getDatabase()->executeSelect(QueryStmts::COINFLIP_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                self::$coinFlips[$row["uuid"]] = [
                    "uuid" => $row["uuid"],
                    "username" => $row["username"],
                    "type" => $row["type"],
                    "wager" => $row["money"]
                ];
            }
        });
    }

    public static function getCoinFlipColorOption(Player $player, int $amount): SimpleForm
    {
        $form = new SimpleForm(function (Player $player, $data) use ($amount): void {
            if ($data === null) {
                return;
            }

            $colors = array_keys(self::COLORS);
            $color = $colors[$data];
            $session = Loader::getPlayerManager()->getSession($player);

            if ($session === null || $session->getBalance() < $amount) {
                $player->sendMessage(C::colorize("&r&cYou don't have enough balance."));
                return;
            }

            $session->removeBalance($amount);
            self::addCoinFlip($player, $color, $amount);
            $player->sendMessage(C::colorize("&r&aCoinflip created."));
        });

        $form->setTitle(C::colorize("&r&8Pick a coin flip color"));
        foreach (self::COLORS as $colorName => $colorCode) {
            $form->addButton(C::colorize("&r&l" . $colorCode . $colorName . "\n&r&8Click to pick"));
        }

        return $form;
    }

    public static function getCoinFlipList(Player $player): SimpleForm
    {
        $entries = array_values(array_filter(
            self::$coinFlips,
            static fn(array $cf): bool => ($cf["uuid"] ?? "") !== $player->getUniqueId()->toString()
        ));

        $form = new SimpleForm(function (Player $player, $data) use ($entries): void {
            if ($data === null || !isset($entries[$data])) {
                return;
            }

            self::getCoinFlipOpponentColorForm($player, (string) $entries[$data]["uuid"], $entries[$data]);
        });

        $form->setTitle(C::colorize("&r&8Coin Flips"));
        if ($entries === []) {
            $form->setContent(C::colorize("&r&8No coinflips available."));
            return $form;
        }

        foreach ($entries as $cf) {
            $form->addButton(C::colorize("&r&8&l" . $cf["username"] . "\n&r&8&o$" . number_format((int) $cf["wager"])));
        }

        return $form;
    }

    public static function getCoinFlipOpponentColorForm(Player $player, string $uuid, array $cf): void
    {
        $colors = array_values(array_filter(
            array_keys(self::COLORS),
            static fn(string $color): bool => $color !== ($cf["type"] ?? "")
        ));

        $form = new SimpleForm(function (Player $player, $data) use ($uuid, $cf, $colors): void {
            if ($data === null || !isset($colors[$data])) {
                return;
            }

            $session = Loader::getPlayerManager()->getSession($player);
            if ($session === null || $session->getBalance() < (int) $cf["wager"]) {
                $player->sendMessage(C::colorize("&r&cYou don't have enough balance."));
                return;
            }

            $session->removeBalance((int) $cf["wager"]);
            self::startCoinFlip((string) $cf["username"], $player->getName(), (string) $cf["type"], $colors[$data], (int) $cf["wager"]);
        });

        $form->setTitle(C::colorize("&r&8Pick a coin flip color"));
        foreach ($colors as $color) {
            $form->addButton(C::colorize("&r&l" . self::COLORS[$color] . $color . "\n&r&8Click to pick"));
        }

        $player->sendForm($form);
    }

    public static function startCoinFlip(string $p1Name, string $p2Name, string $p1Color, string $p2Color, int $amount): void
    {
        $p1 = PlayerUtils::getPlayerByPrefix($p1Name);
        $p2 = PlayerUtils::getPlayerByPrefix($p2Name);
        if ($p1 === null || $p2 === null) {
            return;
        }

        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new CoinFlipTask($p1, $p2, $p1Color, $p2Color, $amount), 1);
        $p1->sendMessage(C::colorize("&r&6Your coinflip against &f" . $p2->getName() . " &6has started."));
        $p2->sendMessage(C::colorize("&r&6Your coinflip against &f" . $p1->getName() . " &6has started."));
    }

    public static function addCoinFlip(Player $player, string $type, int $wager): void
    {
        $uuid = $player->getUniqueId()->toString();
        $username = $player->getName();

        $args = [
            "uuid" => $uuid,
            "username" => $username,
            "type" => $type,
            "money" => $wager,
        ];

        Loader::getDatabase()->executeInsert(QueryStmts::COINFLIP_CREATE, $args);
        self::$coinFlips[$uuid] = [
            "uuid" => $uuid,
            "username" => $username,
            "type" => $type,
            "wager" => $wager
        ];
    }

    public static function removeCoinFlip(string $uuid): void
    {
        Loader::getDatabase()->executeChange(QueryStmts::COINFLIP_DELETE, ["uuid" => $uuid]);
        unset(self::$coinFlips[$uuid]);
    }

    public static function getRollTaskTickInterval(): int
    {
        return 10;
    }

    public function getCoinFlipData(string $uuid): ?array
    {
        return self::$coinFlips[$uuid] ?? null;
    }

    public static function hasSubmittedCoinFlip(Player $player): bool
    {
        return isset(self::$coinFlips[$player->getUniqueId()->toString()]);
    }
}
