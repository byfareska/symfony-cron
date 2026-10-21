<?php declare(strict_types=1);

namespace Byfareska\Cron\Task;

/**
 * @deprecated since 1.5.0, locks are released by the OS when the holding process dies,
 *             so a stale-lock timeout is no longer needed. lockMaxAgeSeconds() is ignored.
 */
interface LockableScheduledTaskWithTimeout extends LockableScheduledTask
{
    /**
     * @return int Ignored since 1.5.0.
     */
    public function lockMaxAgeSeconds(): int;
}
