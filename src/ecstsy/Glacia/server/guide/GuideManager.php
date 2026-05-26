<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\guide;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;

/**
 * Loads resources/guide.yml — categories, topic pages, and command aliases.
 */
final class GuideManager {

    private static string $mainMenuTitle = "";
    private static string $mainMenuContent = "";

    /** @var string[] */
    private static array $order = [];

    /** @var array<string, array{title: string, summary: string, pages: array<string, array{title: string, summary: string, lines: string[], commands: string[]}>}> */
    private static array $guide = [];

    /** @var array<string, string> alias (lowercase) → category id */
    private static array $aliases = [];

    public static function load(?Loader $loader = null): void {
        $loader ??= Loader::getInstance();
        GeneralUtils::reloadConfiguration($loader, "guide.yml");
        $cfg = GeneralUtils::getConfiguration($loader, "guide.yml");
        if ($cfg === null) {
            self::$mainMenuTitle = "&l&bGuide";
            self::$mainMenuContent = "&7Configuration missing.";
            self::$order = [];
            self::$guide = [];
            self::$aliases = [];
            return;
        }

        self::$mainMenuTitle = (string) $cfg->get("main_menu_title", "&l&bGuide");
        self::$mainMenuContent = (string) $cfg->get("main_menu_content", "");

        $guideRoot = (array) $cfg->get("guide", []);
        $declaredOrder = (array) $cfg->get("order", []);

        self::$guide = [];
        foreach ($guideRoot as $id => $block) {
            $id = (string) $id;
            $block = (array) $block;
            $title = (string) ($block["title"] ?? $id);
            $summary = (string) ($block["summary"] ?? "");
            $pagesRaw = (array) ($block["pages"] ?? []);
            $pages = [];
            foreach ($pagesRaw as $pageId => $pageBlock) {
                $pageId = (string) $pageId;
                $pageBlock = (array) $pageBlock;
                $lines = [];
                foreach ((array) ($pageBlock["lines"] ?? []) as $line) {
                    $lines[] = (string) $line;
                }
                $commands = [];
                foreach ((array) ($pageBlock["commands"] ?? []) as $cmd) {
                    $commands[] = (string) $cmd;
                }
                $pages[$pageId] = [
                    "title" => (string) ($pageBlock["title"] ?? $pageId),
                    "summary" => (string) ($pageBlock["summary"] ?? ""),
                    "lines" => $lines,
                    "commands" => $commands,
                ];
            }
            self::$guide[$id] = ["title" => $title, "summary" => $summary, "pages" => $pages];
        }

        self::$order = [];
        foreach ($declaredOrder as $cid) {
            $cid = (string) $cid;
            if (isset(self::$guide[$cid])) {
                self::$order[] = $cid;
            }
        }
        foreach (array_keys(self::$guide) as $cid) {
            if (!in_array($cid, self::$order, true)) {
                self::$order[] = $cid;
            }
        }

        self::$aliases = [];
        foreach ((array) $cfg->get("aliases", []) as $alias => $target) {
            self::$aliases[strtolower((string) $alias)] = (string) $target;
        }
    }

    public static function getMainMenuTitle(): string {
        return self::$mainMenuTitle;
    }

    public static function getMainMenuContent(): string {
        return self::$mainMenuContent;
    }

    /**
     * @return string[]
     */
    public static function getCategoryOrder(): array {
        return self::$order;
    }

    public static function hasCategory(string $id): bool {
        return isset(self::$guide[$id]);
    }

    /**
     * @return array{title: string, summary: string, pages: array<string, array{title: string, summary: string, lines: string[], commands: string[]}>}|null
     */
    public static function getCategory(string $id): ?array {
        return self::$guide[$id] ?? null;
    }

    public static function resolveCategory(string $input): ?string {
        $k = strtolower(trim($input));
        if ($k === "") {
            return null;
        }
        if (isset(self::$guide[$k])) {
            return $k;
        }
        $viaAlias = self::$aliases[$k] ?? null;
        if ($viaAlias !== null && isset(self::$guide[$viaAlias])) {
            return $viaAlias;
        }
        return null;
    }

    public static function resolvePage(string $categoryId, string $input): ?string {
        $cat = self::$guide[$categoryId] ?? null;
        if ($cat === null) {
            return null;
        }
        $pages = $cat["pages"];
        $norm = self::normalizePageKey($input);
        foreach (array_keys($pages) as $pid) {
            if (self::normalizePageKey($pid) === $norm) {
                return $pid;
            }
        }
        $normCompact = str_replace("_", "", $norm);
        foreach (array_keys($pages) as $pid) {
            if (str_replace("_", "", self::normalizePageKey($pid)) === $normCompact) {
                return $pid;
            }
        }
        return null;
    }

    private static function normalizePageKey(string $s): string {
        return strtolower(str_replace([" ", "-"], "_", trim($s)));
    }
}
