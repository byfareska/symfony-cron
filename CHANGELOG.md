# Changelog

## v1.5.0

- `LockManager` now uses Symfony Lock with `FlockStore` (`var/cron-lock/`). The lock is released
  by the OS when the holding process dies, so a crashed task no longer stays locked forever.
  No TTL, long-running tasks are safe.
- New dependency: `symfony/lock`.
- `LockManager::unlock()` is a no-op when this process does not hold the lock (previously a warning
  from `unlink()`).
- Deprecated: `LockManager::toLockPath()` (the file no longer locks anything), `LockManager::isStale()`
  (always `false`), the `$maxAgeSeconds` argument of `isLockedThenLock()` and
  `LockableScheduledTaskWithTimeout` (ignored). Public API is otherwise unchanged.
- Old `var/cron-*.lock` files can be deleted.
- Locks are per host: shared `var/` between containers on one host is fine, NFS is not.
- Upgrade note: a task still running from the previous version (file lock) is not seen by the new
  version (flock). Deploy when long-running tasks are not in progress.
