<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\anticheat;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use ecstsy\Glacia\server\staff\anticheat\AntiCheatProfile;
use ecstsy\Glacia\server\staff\anticheat\GuardLagResult;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use function count;
use function floor;
use function max;
use function sprintf;

final class AntiCheatInspectMenu {

    private const SIZE = 36;
    private const SLOT_PROFILE = 4;
    private const SLOT_COMBAT = 10;
    private const SLOT_MOVE = 11;
    private const SLOT_MISC = 12;
    private const SLOT_NETWORK = 13;
    private const SLOT_VIOLATIONS = 14;
    private const SLOT_ALERTS = 19;
    private const SLOT_UI_TOGGLE = 20;
    private const SLOT_REFRESH = 21;
    private const SLOT_FORM = 22;
    private const SLOT_RESET = 23;

    private InvMenu $menu;

    public function __construct(
        private readonly Player $viewer,
        private readonly Player $target,
    ) {
        $this->menu = CustomSizedInvMenu::create(self::SIZE);
        $this->menu->setName(C::colorize("&r&8Guard Panel &8- &f" . $target->getName()));
        $this->menu->setListener(function(InvMenuTransaction $transaction): InvMenuTransactionResult {
            $actor = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();

            if ($slot === self::SLOT_REFRESH) {
                return $transaction->discard()->then(function(Player $player): void {
                    $player->removeCurrentWindow();
                    self::openChest($this->viewer, $this->target);
                });
            }

            if ($slot === self::SLOT_RESET && $actor->getName() === $this->viewer->getName()) {
                return $transaction->discard()->then(function(Player $player): void {
                    AntiCheatManager::getInstance()->reset($this->target);
                    $player->sendMessage(C::colorize("&r&aGuard &8> &7Wiped seasonal Guard data for &f" . $this->target->getName() . "&7."));
                    $player->removeCurrentWindow();
                    self::openChest($this->viewer, $this->target);
                });
            }

            if ($slot === self::SLOT_ALERTS && $actor->getName() === $this->viewer->getName()) {
                return $transaction->discard()->then(function(Player $player): void {
                    $on = AntiCheatManager::getInstance()->toggleAlerts($player);
                    $player->sendMessage(C::colorize($on ? "&r&aGuard alerts enabled." : "&r&cGuard alerts disabled."));
                });
            }

            if ($slot === self::SLOT_UI_TOGGLE && $actor->getName() === $this->viewer->getName()) {
                return $transaction->discard()->then(function(Player $player): void {
                    $session = Loader::getPlayerManager()->getSession($player);
                    if ($session !== null) {
                        $session->toggleSetting("guard_inspect_use_form");
                        $form = (bool) $session->getSetting("guard_inspect_use_form");
                        $player->sendMessage(C::colorize($form
                            ? "&r&7Default Guard UI set to &fform&7."
                            : "&r&7Default Guard UI set to &fchest&7."));
                    }
                    $player->removeCurrentWindow();
                    self::openChest($this->viewer, $this->target);
                });
            }

            if ($slot === self::SLOT_FORM && $actor->getName() === $this->viewer->getName()) {
                return $transaction->discard()->then(function(Player $player): void {
                    $player->removeCurrentWindow();
                    AntiCheatInspectForm::open($this->viewer, $this->target);
                });
            }

            return $transaction->discard();
        });

        $this->render();
    }

    public static function open(Player $viewer, Player $target): void {
        $session = Loader::getPlayerManager()->getSession($viewer);
        $useForm = $session !== null && (bool) $session->getSetting("guard_inspect_use_form");
        if ($useForm) {
            AntiCheatInspectForm::open($viewer, $target);
            return;
        }

        self::openChest($viewer, $target);
    }

    public static function openChest(Player $viewer, Player $target): void {
        (new self($viewer, $target))->send();
    }

    private function send(): void {
        $this->menu->send($this->viewer);
    }

    private function render(): void {
        $manager = AntiCheatManager::getInstance();
        $profile = $manager->getLiveProfile($this->target);
        $violations = $profile->getViolations();
        $lag = $manager->assessLag($this->target);
        $risk = AntiCheatInspectPresenter::riskSnapshot($profile, $violations);

        $inv = $this->menu->getInventory();
        $inv->clearAll();

        $filler = static fn() => VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY())->asItem()->setCustomName(C::colorize("&r "));
        foreach ([0, 1, 2, 3, 5, 6, 7, 8, 9, 15, 16, 17, 18, 24, 25, 26] as $slot) {
            $inv->setItem($slot, $filler());
        }
        for ($slot = 27; $slot <= 35; ++$slot) {
            $inv->setItem($slot, $filler());
        }

        $inv->setItem(self::SLOT_PROFILE, self::makeProfilePaper($this->target, $profile, $risk, $manager));
        $inv->setItem(self::SLOT_COMBAT, self::makeCombatItem($profile, $violations));
        $inv->setItem(self::SLOT_MOVE, self::makeMovementItem($violations));
        $inv->setItem(self::SLOT_MISC, self::makeMiscItem($violations));
        $inv->setItem(self::SLOT_NETWORK, self::makeNetworkItem($this->target, $lag));
        $inv->setItem(self::SLOT_VIOLATIONS, self::makeViolationBook($violations));

        $session = Loader::getPlayerManager()->getSession($this->viewer);
        $formDefault = $session !== null && (bool) $session->getSetting("guard_inspect_use_form");

        $inv->setItem(self::SLOT_ALERTS, VanillaItems::BOOK()
            ->setCustomName(C::colorize("&r&eAlert Feed"))
            ->setLore([
                C::colorize("&r&7Toggle your live Guard broadcasts."),
                C::colorize("&r&8Perm: &fglacia.anticheat.alerts"),
            ]));

        $inv->setItem(self::SLOT_UI_TOGGLE, VanillaItems::NAME_TAG()
            ->setCustomName(C::colorize("&r&dDefault UI"))
            ->setLore([
                C::colorize("&r&7Current: &f" . ($formDefault ? "Form" : "Chest")),
                C::colorize("&r&7Changes future /ac inspect opens."),
            ]));

        $inv->setItem(self::SLOT_REFRESH, VanillaItems::DYE()->setColor(DyeColor::LIME())
            ->setCustomName(C::colorize("&r&aRefresh"))
            ->setLore([
                C::colorize("&r&7Reload the latest Guard snapshot."),
                C::colorize("&r&7Target: &f" . $this->target->getName()),
            ]));

        $inv->setItem(self::SLOT_FORM, VanillaItems::WRITABLE_BOOK()
            ->setCustomName(C::colorize("&r&bForm View"))
            ->setLore([
                C::colorize("&r&7Open the cleaner button-based form."),
                C::colorize("&r&7Best for mobile staff use."),
            ]));

        $inv->setItem(self::SLOT_RESET, VanillaItems::REDSTONE_DUST()
            ->setCustomName(C::colorize("&r&cWipe Guard Data"))
            ->setLore([
                C::colorize("&r&7Wipes this player's stored Guard data."),
                C::colorize("&r&8Violations, memory, and fingerprints reset."),
            ]));
    }

    /**
     * @param array{risk:int, trust:int, band:string, bandCol:string, confidence:string, confDetail:string, liveRisk:int, historyRisk:int, historyWeight:float, historyResetDaysLeft:int} $risk
     */
    private static function makeProfilePaper(Player $target, AntiCheatProfile $profile, array $risk, AntiCheatManager $manager): Item {
        $ping = $target->getNetworkSession()->getPing();
        $sessionMin = (int) floor(max(0.0, microtime(true) - $profile->getJoinWallTime()) / 60.0);
        $top = AntiCheatInspectPresenter::topViolations($profile->getViolations(), 3);

        $lines = [
            C::colorize("&r" . $risk["bandCol"] . "&l" . $risk["band"] . " &8- &f" . $risk["risk"] . "/100"),
            C::colorize("&r&7Live risk: &f" . $risk["liveRisk"] . "/100"),
            C::colorize("&r&7Season memory: &f" . $risk["historyRisk"] . "/100"),
            C::colorize("&r&7Trust-style: &f" . $risk["trust"] . "/100"),
            C::colorize("&r&7Confidence: &f" . $risk["confidence"]),
            C::colorize("&r&8" . $risk["confDetail"]),
            C::colorize("&r"),
            C::colorize("&r&7World: &f" . $target->getWorld()->getFolderName()),
            C::colorize("&r&7Ping: &f" . $ping . "ms"),
            C::colorize("&r&7Session: &f~{$sessionMin}m"),
            C::colorize("&r&7AC enabled: " . ($manager->isAnticheatEnabled() ? "&aYes" : "&cNo")),
            C::colorize("&r"),
        ];

        if ($top === []) {
            $lines[] = C::colorize("&r&8No active top counters.");
        } else {
            foreach ($top as [$check, $vl]) {
                $lines[] = C::colorize("&r&7- &f" . $check . "&7: &f" . $vl);
            }
        }

        return VanillaItems::PAPER()
            ->setCustomName(C::colorize("&r&b&l" . $target->getName() . " &r&8- &fSnapshot"))
            ->setLore($lines);
    }

    /**
     * @param array<string, int> $violations
     */
    private static function makeCombatItem(AntiCheatProfile $profile, array $violations): Item {
        $ratio = $profile->getHitCount() <= 0 ? "-" : sprintf("%.2f", $profile->swingHitRatio());

        return VanillaItems::IRON_SWORD()
            ->setCustomName(C::colorize("&r&cCombat"))
            ->setLore([
                C::colorize("&r&7Reach VL: &f" . ($violations["Reach"] ?? 0)),
                C::colorize("&r&7AutoClicker VL: &f" . ($violations["AutoClicker"] ?? 0)),
                C::colorize("&r&7Velocity VL: &f" . ($violations["Velocity"] ?? 0)),
                C::colorize("&r&7Swing/hit ratio: &f" . $ratio),
                C::colorize("&r&7Logged hits: &f" . $profile->getHitCount()),
            ]);
    }

    /**
     * @param array<string, int> $violations
     */
    private static function makeMovementItem(array $violations): Item {
        return VanillaItems::LEATHER_BOOTS()
            ->setCustomName(C::colorize("&r&bMovement"))
            ->setLore([
                C::colorize("&r&7Speed VL: &f" . ($violations["Speed"] ?? 0)),
                C::colorize("&r&7Timer VL: &f" . ($violations["Timer"] ?? 0)),
                C::colorize("&r&7BadPackets VL: &f" . ($violations["BadPackets"] ?? 0)),
                C::colorize("&r&8Always compare with lag tier."),
            ]);
    }

    /**
     * @param array<string, int> $violations
     */
    private static function makeMiscItem(array $violations): Item {
        return VanillaItems::FEATHER()
            ->setCustomName(C::colorize("&r&dMisc"))
            ->setLore([
                C::colorize("&r&7Velocity VL: &f" . ($violations["Velocity"] ?? 0)),
                C::colorize("&r&8Secondary context, not solo proof."),
            ]);
    }

    private static function makeNetworkItem(Player $target, GuardLagResult $lag): Item {
        $tierLabel = match ($lag->tier) {
            GuardLagResult::TIER_HARD => "&cUnstable",
            GuardLagResult::TIER_SOFT => "&eMild stress",
            default => "&aStable",
        };

        return self::parseMenuIconItem([
            "comparator",
            "redstone_comparator",
            "repeater",
            "redstone_repeater",
        ])->setCustomName(C::colorize("&r&3Network"))
            ->setLore([
                C::colorize("&r&7Lag tier: " . $tierLabel),
                C::colorize("&r&7Reach multiplier: &fx" . sprintf("%.2f", $lag->multiplier)),
                C::colorize("&r&7Jitter est.: &f" . sprintf("%.1f", $lag->jitterMs) . "ms"),
                C::colorize("&r&7Ping EWMA: &f" . sprintf("%.0f", $lag->pingEwmaMs) . "ms"),
                C::colorize("&r&7Raw ping: &f" . $target->getNetworkSession()->getPing() . "ms"),
            ]);
    }

    /**
     * @param list<string> $ids
     */
    private static function parseMenuIconItem(array $ids): Item {
        $parser = StringToItemParser::getInstance();
        foreach ($ids as $id) {
            $item = $parser->parse($id);
            if ($item !== null) {
                return clone $item;
            }
        }

        return clone ($parser->parse("clock") ?? VanillaItems::PAPER());
    }

    /**
     * @param array<string, int> $violations
     */
    private static function makeViolationBook(array $violations): Item {
        if ($violations === []) {
            return VanillaItems::BOOK()
                ->setCustomName(C::colorize("&r&aViolations"))
                ->setLore([
                    C::colorize("&r&7No active VL counters."),
                    C::colorize("&r&8Decay applies over time."),
                ]);
        }

        $rows = AntiCheatInspectPresenter::topViolations($violations, 12);
        $lines = [];
        foreach ($rows as [$check, $vl]) {
            $lines[] = C::colorize("&r" . self::vlColor($vl) . $check . " &8> &f" . $vl);
        }
        if (count($violations) > count($rows)) {
            $lines[] = C::colorize("&r&8+" . (count($violations) - count($rows)) . " more...");
        }

        return VanillaItems::BOOK()
            ->setCustomName(C::colorize("&r&eViolations"))
            ->setLore($lines);
    }

    private static function vlColor(int $vl): string {
        return match (true) {
            $vl >= 14 => "&c",
            $vl >= 8 => "&6",
            $vl >= 4 => "&e",
            default => "&7",
        };
    }
}
