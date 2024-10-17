<?php declare(strict_types=1);

namespace Byfareska\Cron\Task;

interface LockableScheduledTask extends ScheduledTask
{
    /**
     * @return bool If true will be skipped if lock detected
     */
    public function skipIfLocked(): bool;
}