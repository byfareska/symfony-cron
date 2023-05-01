<?php declare(strict_types=1);

namespace Byfareska\Cron\Task;

use DateTimeInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface ScheduledTask
{
    /**
     * @return bool Tells if task has been executed
     */
    public function cronInvoke(DateTimeInterface $now, bool $forceRun, OutputInterface $output): bool;
}