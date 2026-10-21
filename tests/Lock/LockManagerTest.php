<?php declare(strict_types=1);

namespace Byfareska\Cron\Tests\Lock;

use Byfareska\Cron\Lock\LockManager;
use PHPUnit\Framework\TestCase;

final class LockManagerTest extends TestCase
{
    private const TASK = 'App\\Task\\ImportTask';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/cron-lock-test-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $dir = $this->projectDir . '/var/cron-lock';
        foreach (glob($dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        is_dir($dir) && rmdir($dir);
        is_dir($this->projectDir . '/var') && rmdir($this->projectDir . '/var');
        rmdir($this->projectDir);
    }

    public function testLockAndUnlockInSameProcess(): void
    {
        $manager = new LockManager($this->projectDir);

        self::assertFalse($manager->isLockedThenLock(self::TASK));
        self::assertTrue($manager->isLockedThenLock(self::TASK));
        self::assertTrue($manager->isLocked(self::TASK));

        $manager->unlock(self::TASK);

        self::assertFalse($manager->isLocked(self::TASK));
    }

    public function testUnlockWithoutLockIsNoop(): void
    {
        $manager = new LockManager($this->projectDir);

        $manager->unlock(self::TASK);

        self::assertFalse($manager->isLocked(self::TASK));
    }

    public function testLockIsReleasedWhenHoldingProcessIsKilled(): void
    {
        $script = sprintf(
            '<?php require %s; $m = new %s(%s); $m->lock(%s); echo "ready\n"; fgets(STDIN);',
            var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true),
            '\\' . LockManager::class,
            var_export($this->projectDir, true),
            var_export(self::TASK, true),
        );
        $scriptPath = $this->projectDir . '/child.php';
        file_put_contents($scriptPath, $script);

        $proc = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'error_reporting=E_ALL&~E_DEPRECATED', $scriptPath], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($proc);

        try {
            $line = fgets($pipes[1]);
            if ($line !== "ready\n") {
                proc_terminate($proc, 9);
                self::fail('Child failed: ' . $line . stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]));
            }

            $manager = new LockManager($this->projectDir);
            self::assertTrue($manager->isLocked(self::TASK));
            self::assertTrue($manager->isLockedThenLock(self::TASK));
        } finally {
            proc_terminate($proc, 9);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($proc);
            unlink($scriptPath);
        }

        self::assertFalse($manager->isLocked(self::TASK));
        self::assertFalse($manager->isLockedThenLock(self::TASK));
        $manager->unlock(self::TASK);
    }

    public function testDifferentTasksDoNotBlockEachOther(): void
    {
        $manager = new LockManager($this->projectDir);
        $other = new LockManager($this->projectDir);

        self::assertFalse($manager->isLockedThenLock(self::TASK));
        self::assertFalse($other->isLockedThenLock('App\\Task\\OtherTask'));
        self::assertTrue($other->isLocked(self::TASK));
        self::assertTrue($manager->isLocked('App\\Task\\OtherTask'));

        $manager->unlock(self::TASK);
        $other->unlock('App\\Task\\OtherTask');
    }
}
