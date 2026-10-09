<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('send-newsletters exits immediately when another run holds the lock', function (): void {
    $lockPath = __DIR__ . '/../data/send-newsletters.lock';
    $lock = \fopen($lockPath, mode: 'c');
    \assert(\is_resource($lock));
    \flock($lock, \LOCK_EX | \LOCK_NB);

    try {
        $startedAt = \microtime(true);
        $process = new Process(['php', __DIR__ . '/../bin/send-newsletters.php']);
        $process->setTimeout(10);
        $process->run();
        $duration = \microtime(true) - $startedAt;

        // Skipped run: silent success, no delivery attempt, fast exit.
        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toBe('')
            ->and($duration)->toBeLessThan(5.0);
    } finally {
        \flock($lock, \LOCK_UN);
        \fclose($lock);
    }
});
