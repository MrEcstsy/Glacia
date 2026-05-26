<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\guide;

use ecstsy\Glacia\server\guide\GuideManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;
use function count;
use function implode;
use function strlen;
use function substr;
use function trim;

final class GuideScreen {

    public static function openMain(Player $player): void {
        $order = GuideManager::getCategoryOrder();
        if ($order === []) {
            $player->sendMessage(C::colorize("&r&cThe guide has no categories. Ask staff to check &fplugins/Glacia/guide.yml&c."));
            return;
        }

        $form = new SimpleForm(function (Player $player, ?int $data) use ($order): void {
            if ($data === null) {
                return;
            }
            $categoryId = $order[$data] ?? null;
            if ($categoryId === null) {
                return;
            }
            self::openCategory($player, $categoryId);
        });

        $form->setTitle(C::colorize(GuideManager::getMainMenuTitle()));
        $content = GuideManager::getMainMenuContent();
        $form->setContent($content !== "" ? C::colorize($content) : C::colorize("&r&7Choose a category."));

        foreach ($order as $cid) {
            $cat = GuideManager::getCategory($cid);
            if ($cat === null) {
                continue;
            }

            $summary = trim((string) ($cat["summary"] ?? ""));
            if ($summary === "") {
                $summary = count($cat["pages"]) . " topics";
            }

            $form->addButton(C::colorize($cat["title"] . "\n&0" . self::fitButtonSubtitle($summary)));
        }

        $player->sendForm($form);
    }

    public static function openCategory(Player $player, string $categoryId): void {
        $cat = GuideManager::getCategory($categoryId);
        if ($cat === null) {
            $player->sendMessage(C::colorize("&r&cUnknown guide section."));
            return;
        }

        $pageIds = array_keys($cat["pages"]);
        if ($pageIds === []) {
            $player->sendMessage(C::colorize("&r&cThis section has no pages yet."));
            return;
        }

        $form = new SimpleForm(function (Player $player, ?int $data) use ($categoryId, $pageIds): void {
            if ($data === null) {
                return;
            }

            $total = count($pageIds);
            if ($data === $total) {
                self::openMain($player);
                return;
            }

            $pageId = $pageIds[$data] ?? null;
            if ($pageId === null) {
                return;
            }

            self::openPage($player, $categoryId, $pageId);
        });

        $form->setTitle(C::colorize($cat["title"]));
        $summary = trim((string) ($cat["summary"] ?? ""));
        $form->setContent(C::colorize($summary !== ""
            ? $summary
            : "&r&7Pick a topic. This section explains the system, not every item."));

        foreach ($pageIds as $pid) {
            $page = $cat["pages"][$pid];
            $pageSummary = trim((string) ($page["summary"] ?? ""));
            if ($pageSummary === "") {
                $pageSummary = trim((string) ($page["lines"][0] ?? ""));
            }
            if ($pageSummary === "") {
                $pageSummary = "Open topic";
            }

            $form->addButton(C::colorize($page["title"] . "\n&0" . self::fitButtonSubtitle($pageSummary)));
        }

        $form->addButton(C::colorize("&r&8Main Menu\n&0Return to guide home"));
        $player->sendForm($form);
    }

    public static function openPage(Player $player, string $categoryId, string $pageId): void {
        $cat = GuideManager::getCategory($categoryId);
        if ($cat === null) {
            return;
        }

        $page = $cat["pages"][$pageId] ?? null;
        if ($page === null) {
            self::openCategory($player, $categoryId);
            return;
        }

        $body = self::buildPageBody($page, $categoryId);

        $form = new SimpleForm(function (Player $player, ?int $data) use ($categoryId): void {
            if ($data === null) {
                return;
            }

            if ($data === 0) {
                self::openCategory($player, $categoryId);
                return;
            }

            self::openMain($player);
        });

        $form->setTitle(C::colorize($page["title"]));
        $form->setContent(C::colorize($body));
        $form->addButton(C::colorize("&r&aBack to Topics\n&0Return to this section"));
        $form->addButton(C::colorize("&r&8Main Menu\n&0Return to guide home"));

        $player->sendForm($form);
    }

    /**
     * @param array{title: string, summary: string, lines: string[], commands: string[]} $page
     */
    private static function buildPageBody(array $page, string $categoryId): string {
        $parts = ["&r"];

        $summary = trim((string) ($page["summary"] ?? ""));
        if ($summary !== "") {
            $parts[] = "&bWhy this matters:";
            $parts[] = "&f" . $summary;
            $parts[] = "";
        }

        foreach ($page["lines"] as $line) {
            $parts[] = $line;
        }

        if (($page["commands"] ?? []) !== []) {
            $parts[] = "";
            $parts[] = "&7Useful commands:";
            foreach ($page["commands"] as $cmd) {
                $parts[] = $cmd;
            }
        }

        $parts[] = "";
        $parts[] = "&8-";
        $parts[] = "&7Reopen this section: &f/guide " . $categoryId;
        return implode("\n", $parts);
    }

    private static function fitButtonSubtitle(string $text): string {
        $plain = trim(C::clean($text));
        if (strlen($plain) <= 28) {
            return $plain;
        }

        return rtrim(substr($plain, 0, 25)) . "...";
    }
}
