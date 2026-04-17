<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\utils\SingletonTrait;

final class GraceInstance {
    use SingletonTrait;

    private bool $enabled = false;
    private int $endTime = 0;

    public function __construct() {
        self::setInstance($this);

        $this->load();
    }

    /**
     * Loads grace values from the database.
     */
    private function load(): void {
        $db = Loader::getDatabase();
        $db->executeSelect(QueryStmts::SERVER_GLACIA_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                switch ($row['key'] ?? '') {
                    case GlaciaServerKeys::GRACE_ENABLED:
                        $this->enabled = boolval($row['value']);
                        break;
                    case GlaciaServerKeys::GRACE_END:
                        $this->endTime = (int)$row['value'];
                        break;
                }
            }
        });
    }

    public function isGraceActive(): bool {
        return $this->enabled && time() < $this->endTime;
    }

    public function getRemaining(): int {
        return max(0, $this->endTime - time());
    }

    public function getEndTime(): int {
        return $this->endTime;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function enable(int $durationSeconds): void {
        if ($durationSeconds <= 0) return;
        $this->enabled = true;
        $this->endTime = time() + $durationSeconds;
        $this->save(GlaciaServerKeys::GRACE_ENABLED, '1');
        $this->save(GlaciaServerKeys::GRACE_END, (string)$this->endTime);
    }

    public function extend(int $extraSeconds): void {
        if ($extraSeconds <= 0) return;
        $this->endTime = max(time(), $this->endTime) + $extraSeconds;
        $this->save(GlaciaServerKeys::GRACE_END, (string)$this->endTime);
        if (!$this->enabled && $this->endTime > time()) {
            $this->enabled = true;
            $this->save(GlaciaServerKeys::GRACE_ENABLED, '1');
        }
    }

    public function disable(): void {
        $this->enabled = false;
        $this->endTime = 0;
        $this->save(GlaciaServerKeys::GRACE_ENABLED, '0');
        $this->save(GlaciaServerKeys::GRACE_END, '0');
    }

    private function save(string $key, string $value): void {
        Loader::getDatabase()->executeChange(QueryStmts::SERVER_GLACIA_CREATE, [
            'key' => $key,
            'value' => $value
        ]);
    }
}