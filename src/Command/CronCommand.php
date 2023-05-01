<?php declare(strict_types=1);

namespace Byfareska\Cron\Command;

use Byfareska\Cron\Task\ScheduledTask;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: self::DEFAULT_NAME, description: 'Run crontab, you can use -t option to run specific task.')]
final class CronCommand extends Command
{
    private const DEFAULT_NAME = 'cron:run';
    protected static $defaultName = self::DEFAULT_NAME;
    private iterable $tasks;
    private ?LoggerInterface $logger;

    /**
     * @param iterable<ScheduledTask> $tasks
     */
    public function __construct(
        iterable $tasks,
        ?LoggerInterface $logger = null,
        string $name = null
    )
    {
        $this->logger = $logger;
        $this->tasks = $tasks;
        parent::__construct($name);
        $this->addOption('task', 't', InputOption::VALUE_OPTIONAL, 'Force to execute specific task (ignore schedule). You should put as argument value task class name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new DateTimeImmutable();
        $i = 0;
        $hasTaskOption = !empty($input->getOption('task'));
        $forcedTaskNames = explode(',', $input->getOption('task') ?? '');

        $tasks = $hasTaskOption
            ? array_filter($this->tasks, static fn(ScheduledTask $task) => in_array(get_class($task), $forcedTaskNames, true))
            : $this->tasks;

        $tasksCount = count($tasks);

        if ($tasksCount === 0) {
            $output->writeln('No scheduled tasks found.');
        }

        foreach ($tasks as $task) {
            $output->write(sprintf('[%d/%d] Calling %s... ', ++$i, $tasksCount, get_class($task)));
            $started = round(microtime(true) * 1000);

            try {
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

                $this->logger->error("[CRON] {$e->getMessage()}", ['details' => $e]);
            }
        }

        return Command::SUCCESS;
    }
}