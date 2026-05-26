<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\CoinFlipInstance;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\sound\XpLevelUpSound;
use xenialdan\apibossbar\BarColor;
use xenialdan\apibossbar\BossBar;

final class CoinFlipTask extends Task
{
    private const ANIMATION_FRAMES = [
        "&e>&a>>>> &7Winner: {winner} &a<<<<&e<",
        "&a>&e>&a>>> &7Winner: {winner} &a<<<&e<&a<",
        "&a>>&e>&a>> &7Winner: {winner} &a<<&e<&a<<",
        "&a>>>&e>&a> &7Winner: {winner} &a<&e<&a<<<",
        "&a>>>>&e> &7Winner: {winner} &e<&a<<<<",
    ];

    private Player $p1;
    private Player $p2;
    private string $color1;
    private string $color2;
    private string $color1Name;
    private string $color2Name;
    private float $progress = 0.0;
    private int $amount;
    private BossBar $bar;
    private int $ticksElapsed = 0;
    private int $animationStep = 0;
    private bool $winnerAnnounced = false;
    private bool $animationStarted = false;
    private ?Player $winner = null;

    public function __construct(Player $p1, Player $p2, string $color1, string $color2, int $amount)
    {
        $this->p1 = $p1;
        $this->p2 = $p2;
        $this->color1Name = $color1;
        $this->color2Name = $color2;
        $this->color1 = CoinFlipInstance::getColorCode($color1);
        $this->color2 = CoinFlipInstance::getColorCode($color2);
        $this->amount = $amount;

        $this->bar = new BossBar();
        $this->bar->setColor(BarColor::PURPLE);
        $this->bar->setPercentage(0.0);
        $this->bar->addPlayers([$p1, $p2]);
    }

    public function onRun(): void
    {
        $this->ticksElapsed++;

        if ($this->progress < 1.0) {
            $this->updateProgress();
        } elseif (!$this->animationStarted) {
            $this->startAnimation();
        } elseif (!$this->winnerAnnounced) {
            $this->updateAnimation();
        }
    }

    private function updateProgress(): void
    {
        $this->progress = min($this->progress + 0.01, 1.0);
        $this->bar->setPercentage($this->progress);

        if ($this->ticksElapsed % 3 === 0) {
            $this->p1->getWorld()->addSound($this->p1->getPosition(), new ClickSound());
            $this->p2->getWorld()->addSound($this->p2->getPosition(), new ClickSound());
        }

        $colors = [$this->color1, $this->color2];
        $currentColor = $colors[intdiv($this->ticksElapsed, 3) % count($colors)];
        $this->bar->setTitle(C::colorize(
            "&6&l   " . $this->p1->getName() . " &7[" . CoinFlipInstance::getColoredBox($currentColor) . "&7] &6" . $this->p2->getName()
        ));
    }

    private function startAnimation(): void
    {
        $this->animationStarted = true;
        $this->winner = rand(0, 1) === 1 ? $this->p1 : $this->p2;
        $this->bar->setTitle(C::colorize(
            "&6&l   " . $this->p1->getName() . " &7[" . CoinFlipInstance::getColoredBox($this->color1) . "&7] &6" . $this->p2->getName()
        ));
    }

    private function updateAnimation(): void
    {
        if ($this->ticksElapsed % 10 === 0 && $this->winner !== null) {
            $this->animationStep = min($this->animationStep + 1, count(self::ANIMATION_FRAMES) - 1);
            $frame = str_replace("{winner}", $this->winner->getName(), self::ANIMATION_FRAMES[$this->animationStep]);
            $this->bar->setTitle(C::colorize($frame));
            $this->p1->getWorld()->addSound($this->p1->getPosition(), new ClickSound(), [$this->p1]);
            $this->p2->getWorld()->addSound($this->p2->getPosition(), new ClickSound(), [$this->p2]);
        }

        if ($this->ticksElapsed >= 150) {
            $this->p1->getWorld()->addSound($this->p1->getPosition(), new XpLevelUpSound(1000));
            $this->p2->getWorld()->addSound($this->p2->getPosition(), new XpLevelUpSound(1000));
            $this->announceWinner();
        }
    }

    private function announceWinner(): void
    {
        if ($this->winner === null) {
            $this->getHandler()?->cancel();
            return;
        }

        $this->winnerAnnounced = true;
        $loser = $this->winner === $this->p1 ? $this->p2 : $this->p1;
        $grossPrize = $this->amount * 2;

        CoinFlipInstance::removeCoinFlip($this->p1->getUniqueId()->toString());
        CoinFlipInstance::removeCoinFlip($this->p2->getUniqueId()->toString());

        $winnerSession = Loader::getPlayerManager()->getSession($this->winner);
        if ($winnerSession === null) {
            $this->getHandler()?->cancel();
            return;
        }

        $taxPercent = CoinFlipInstance::getTaxPercentFor($this->winner);
        $taxAmount = (int) round($grossPrize * ($taxPercent / 100), 0);
        $netPrize = max(0, $grossPrize - $taxAmount);
        $winnerSession->addBalance($netPrize);

        $winnerColor = $this->winner === $this->p1 ? $this->color1 : $this->color2;
        $winnerColorName = $this->winner === $this->p1 ? $this->color1Name : $this->color2Name;
        $loserColor = $this->winner === $this->p1 ? $this->color2 : $this->color1;
        $loserColorName = $this->winner === $this->p1 ? $this->color2Name : $this->color1Name;
        $headToHead = CoinFlipInstance::recordResult($this->winner, $loser, $netPrize, $this->amount);

        $taxSuffix = $taxAmount > 0 ? " &7(after &c$" . number_format($taxAmount) . " &7tax)" : "";
        $resultMessage = GlaciaUtils::format("coinflip.result.player", [
            "winner" => $this->winner->getName(),
            "loser" => $loser->getName(),
            "winner_color" => $winnerColor,
            "winner_color_name" => $winnerColorName,
            "loser_color" => $loserColor,
            "loser_color_name" => $loserColorName,
            "net_prize" => number_format($netPrize),
            "tax_suffix" => $taxSuffix,
        ]);
        $this->winner->sendMessage($resultMessage);
        $loser->sendMessage($resultMessage);

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $player->sendMessage(GlaciaUtils::format("coinflip.broadcast.win", [
                "winner" => $this->winner->getName(),
                "loser" => $loser->getName(),
                "winner_vs_loser_wins" => (string) ($headToHead["winner_vs_loser"]["wins"] ?? 0),
                "loser_vs_winner_wins" => (string) ($headToHead["loser_vs_winner"]["wins"] ?? 0),
                "winner_box" => CoinFlipInstance::getColoredBox($winnerColor),
                "loser_box" => CoinFlipInstance::getColoredBox($loserColor),
                "winner_color" => $winnerColor,
                "winner_color_name" => $winnerColorName,
                "loser_color" => $loserColor,
                "loser_color_name" => $loserColorName,
                "net_prize" => number_format($netPrize),
                "tax_broadcast_suffix" => $taxAmount > 0 ? " &8(&c$" . number_format($taxAmount) . " tax&8)" : "",
            ]));
        }

        $this->winner->getWorld()->addSound($this->winner->getPosition(), new XpLevelUpSound(1000));
        $loser->getWorld()->addSound($loser->getPosition(), new XpLevelUpSound(1000));

        $this->bar->setPercentage(1.0);

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new class($this->bar, $this->p1, $this->p2) extends Task {
            private BossBar $bar;
            private Player $p1;
            private Player $p2;

            public function __construct(BossBar $bar, Player $p1, Player $p2)
            {
                $this->bar = $bar;
                $this->p1 = $p1;
                $this->p2 = $p2;
            }

            public function onRun(): void
            {
                $this->bar->hideFrom([$this->p1, $this->p2]);
            }
        }, 40);

        $this->getHandler()?->cancel();
    }
}
