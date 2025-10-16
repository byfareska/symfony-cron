<?php declare(strict_types=1);

namespace Byfareska\Cron\Lock;

use Byfareska\Cron\Task\LockableScheduledTask;

final class LockManager
{
    public function __construct(
        private string $projectDir,
    )
    {
    }

    public function isLocked(LockableScheduledTask|string $task): bool
    {
        return file_exists($this->toLockPath($task));
    }

    public function lock(LockableScheduledTask|string $task): void
    {
        file_put_contents($this->toLockPath($task), time());
    }

    public function unlock(LockableScheduledTask|string $task): void
    {
        unlink($this->toLockPath($task));
    }

    public function isLockedThenLock(LockableScheduledTask|string $task): bool
    {
        $path = $this->toLockPath($task);
        $isLocked = file_exists($path);
        file_put_contents($path, time());

        return $isLocked;
    }

    public function toLockPath(LockableScheduledTask|string $task): string
    {
        return sprintf(
            '%s/var/cron-%s.lock',
            $this->projectDir,
            preg_replace('/[^A-Za-z0-9 ]/', '_', is_string($task) ? $task : $task::class)
        );
    }
}