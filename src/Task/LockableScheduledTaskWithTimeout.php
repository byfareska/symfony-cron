<?php declare(strict_types=1);

namespace Byfareska\Cron\Task;

/**
 * Extends LockableScheduledTask with a custom stale-lock timeout.
 *
 * By default, LockManager considers a lock stale after 3600 seconds.
 * Implement this interface to override that threshold per task — useful for
 * tasks that legitimately run longer (or shorter) than one hour.
 *
 * When posix_getpgid() is available, the PID stored in the lock file is checked
 * first: a dead process means the lock is stale regardless of age. The timeout
 * is then used as a safety net against PID reuse.
 */
interface LockableScheduledTaskWithTimeout extends LockableScheduledTask
{
    /**
     * @return int Maximum number of seconds a lock is considered valid.
     *             Locks older than this are treated as leftovers from a crashed process.
     */
    public function lockMaxAgeSeconds(): int;
}
