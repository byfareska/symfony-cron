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

## Locking

A task implementing `\Byfareska\Cron\Task\LockableScheduledTask` whose `skipIfLocked()` returns `true`
is skipped while another process holds its lock. You can also use `\Byfareska\Cron\Lock\LockManager`
directly (`isLocked()`, `lock()`, `unlock()`, `isLockedThenLock()`), e.g. to share the lock between
a cron task and a CLI command.

Locks use `flock()` (Symfony Lock `FlockStore`) with files in `var/cron-lock/`:

- the lock is released automatically when the holding process dies (fatal error, memory limit,
  `kill -9`, container restart), so a crashed task never stays locked,
- there is no TTL, a task may run for hours,
- `sf.*.lock` files stay on disk after the lock is released, that is expected,
- `flock()` works within a single host: a `var/` shared between containers on the same host is fine,
  a `var/` on NFS is not.
