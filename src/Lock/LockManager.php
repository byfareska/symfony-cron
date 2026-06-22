<?php declare(strict_types=1);

namespace Byfareska\Cron\Lock;

use Byfareska\Cron\Task\LockableScheduledTask;
use Byfareska\Cron\Task\LockableScheduledTaskWithTimeout;

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
        file_put_contents($this->toLockPath($task), time() . ' ' . (function_exists('getmypid') ? getmypid() : 0));
    }

    public function unlock(LockableScheduledTask|string $task): void
    {
        unlink($this->toLockPath($task));
    }

    private function resolveMaxAge(LockableScheduledTask|string $task, int $maxAgeSeconds): int
    {
        if ($task instanceof LockableScheduledTaskWithTimeout) {
            return $task->lockMaxAgeSeconds();
        }

        return $maxAgeSeconds;
    }

    public function isStale(LockableScheduledTask|string $task, int $maxAgeSeconds = 3600): bool
    {
        $path = $this->toLockPath($task);
        if (!file_exists($path)) {
            return false;
        }

        $maxAgeSeconds = $this->resolveMaxAge($task, $maxAgeSeconds);
        $parts = explode(' ', trim((string) file_get_contents($path)));
        $timestamp = (int) ($parts[0] ?? 0);
        $pid = (int) ($parts[1] ?? 0);

        if ($pid > 0 && function_exists('posix_getpgid')) {
            if (posix_getpgid($pid) === false) {
                return true;
            }
            // PID alive but may be reused — verify with timestamp
            return (time() - $timestamp) > $maxAgeSeconds;
        }

        return (time() - $timestamp) > $maxAgeSeconds;
    }

    public function isLockedThenLock(LockableScheduledTask|string $task, int $maxAgeSeconds = 3600): bool
    {
        $path = $this->toLockPath($task);
        $isLocked = file_exists($path) && !$this->isStale($task, $maxAgeSeconds);
        if (!$isLocked) {
            file_put_contents($path, time() . ' ' . (function_exists('getmypid') ? getmypid() : 0));
        }

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