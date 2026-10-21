<?php declare(strict_types=1);

namespace Byfareska\Cron\Lock;

use Byfareska\Cron\Task\LockableScheduledTask;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * Locks are flock()-based (Symfony Lock FlockStore in %projectDir%/var/cron-lock),
 * so the OS releases them automatically when the holding process dies.
 * There is no TTL, a lock is held as long as the process lives or until unlock().
 */
final class LockManager
{
    /** @var array<string, Lock> Locks acquired by this process; the Lock object must stay alive to keep the lock. */
    private array $held = [];

    private ?FlockStore $store = null;

    public function __construct(
        private string $projectDir,
    )
    {
    }

    public function isLocked(LockableScheduledTask|string $task): bool
    {
        $name = $this->toLockName($task);
        if (isset($this->held[$name])) {
            return true;
        }

        $lock = $this->createLock($name);
        if (!$lock->acquire(false)) {
            return true;
        }

        $lock->release();

        return false;
    }

    public function lock(LockableScheduledTask|string $task): void
    {
        $this->isLockedThenLock($task);
    }

    public function unlock(LockableScheduledTask|string $task): void
    {
        $name = $this->toLockName($task);
        if (!isset($this->held[$name])) {
            return;
        }

        $this->held[$name]->release();
        unset($this->held[$name]);
    }

    /**
     * @deprecated since 1.5.0, locks are released by the OS when the process dies, so they are never stale.
     */
    public function isStale(LockableScheduledTask|string $task, int $maxAgeSeconds = 3600): bool
    {
        return false;
    }

    /**
     * @param int $maxAgeSeconds Ignored since 1.5.0, kept for backward compatibility.
     */
    public function isLockedThenLock(LockableScheduledTask|string $task, int $maxAgeSeconds = 3600): bool
    {
        $name = $this->toLockName($task);
        if (isset($this->held[$name])) {
            return true;
        }

        $lock = $this->createLock($name);
        if (!$lock->acquire(false)) {
            return true;
        }

        $this->held[$name] = $lock;

        return false;
    }

    /**
     * @deprecated since 1.5.0, the file at this path no longer locks anything.
     */
    public function toLockPath(LockableScheduledTask|string $task): string
    {
        return sprintf('%s/var/cron-%s.lock', $this->projectDir, $this->toLockName($task));
    }

    private function toLockName(LockableScheduledTask|string $task): string
    {
        return preg_replace('/[^A-Za-z0-9 ]/', '_', is_string($task) ? $task : $task::class);
    }

    private function createLock(string $name): Lock
    {
        return new Lock(new Key($name), $this->getStore(), null, false);
    }

    private function getStore(): FlockStore
    {
        if ($this->store === null) {
            $dir = $this->projectDir . '/var/cron-lock';
            is_dir($dir) || @mkdir($dir, 0777, true) || is_dir($dir);
            $this->store = new FlockStore($dir);
        }

        return $this->store;
    }
}
