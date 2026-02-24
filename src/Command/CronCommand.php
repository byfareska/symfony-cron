<?php declare(strict_types=1);

namespace Byfareska\Cron\Command;

use Byfareska\Cron\Lock\LockManager;
use Byfareska\Cron\Task\LockableScheduledTask;
use Byfareska\Cron\Task\ScheduledTask;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'cron:run', description: 'Run crontab, you can use -t option to run specific task.')]
final class CronCommand extends Command
{
    /**
     * @param iterable<ScheduledTask> $tasks
     */
    public function __construct(
        private iterable $tasks,
        private LockManager $lockManager,
        private ?LoggerInterface $logger = null,
    )
    {
        parent::__construct();
        $this->addOption('task', 't', InputOption::VALUE_OPTIONAL, 'Force to execute specific task (ignore schedule). You should put as argument value task class name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new DateTimeImmutable();
        $i = 0;
        $hasTaskOption = !empty($input->getOption('task'));
        $forcedTaskNames = explode(',', $input->getOption('task') ?? '');

        $tasks = $hasTaskOption
            ? array_filter([...$this->tasks], static fn(ScheduledTask $task) => in_array(get_class($task), $forcedTaskNames, true))
            : $this->tasks;

        $tasksCount = count($tasks);

        if ($tasksCount === 0) {
            $output->writeln('No scheduled tasks found.');
        }

        foreach ($tasks as $task) {
            $output->write(sprintf('[%d/%d] Calling %s... ', ++$i, $tasksCount, get_class($task)));
            $started = round(microtime(true) * 1000);
            $hasLock = $task instanceof LockableScheduledTask && $task->skipIfLocked();

            try {
                if ($hasLock && $this->lockManager->isLockedThenLock($task)) {
                    $output->writeln('skipped (locked).');
                    continue;
                }

                if ($task->cronInvoke($now, $hasTaskOption, $output)) {
                    $output->writeln(sprintf(
                        'Executed in %ss.',
                        number_format(round((microtime(true) * 1000) - $started) / 1000, 2, ',', ' ')
                    ));
                } else {
                    $output->writeln('skipped.');
                }
            } catch (Throwable $e) {
                $output->writeln(sprintf(
                    'an exception occurred: %s: %s',
                    get_class($e),
                    $e->getMessage(),
                ));

                $this->logger?->error("[CRON] {$e->getMessage()}", ['details' => $e]);
            }

            $task instanceof LockableScheduledTask && $this->lockManager->unlock($task);
        }

        return Command::SUCCESS;
    }
}
