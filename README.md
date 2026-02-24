# Cron bundle

## Requirements

- PHP >= 8.2
- Symfony 6.1, 7.x, or 8.x

## Fast setup

1. Add to cron `* * * * * php bin/console cron:run`
2. Create a class that implements `\Byfareska\Cron\Task\ScheduledTask`, for example:
```php
final class DeleteFileEveryHourTask implements ScheduledTask {

    public function cronInvoke(DateTimeInterface $now, bool $forceRun, OutputInterface $output): bool
    {
        if($forceRun || $now->format('i') === '0'){
            $this();
            return true;
        }
        
        return false;
    }
    
    public function __invoke(): void
    {
        unlink('/var/example');
    }
}
```
## Useful commands

### Force to run some tasks
```
php bin/console cron:run --task=App\\Task\\DeleteFileEveryHourTask,App\\Task\\AnotherTask
```

### List all registered tasks
```
php bin/console debug:container --tag=cron.task
```