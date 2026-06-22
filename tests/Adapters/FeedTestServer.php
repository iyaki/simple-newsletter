<?php

declare(strict_types=1);

namespace Tests\Adapters;

use Symfony\Component\Process\Process;

const FEED_TEST_SERVER_PORT = 9995;

final class FeedTestServer
{
    private static ?Process $server = null;

    /**
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @throws \Symfony\Component\Process\Exception\ProcessStartFailedException
     */
    public static function start(): void
    {
        $feedDir = '/tmp/feedtest';
        if (! \is_dir($feedDir)) {
            \mkdir($feedDir, 0777, true);
            \file_put_contents($feedDir . '/valid.xml', <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <rss version="2.0">
                <channel>
                <title>Test Blog</title>
                <link>https://example.com</link>
                <item>
                <title>First Post</title>
                <link>https://example.com/post1</link>
                </item>
                </channel>
                </rss>
                XML);
            \file_put_contents(filename: $feedDir . '/invalid.txt', data: 'not xml');
        }

        $cmd = ['php', '-S', '0.0.0.0:' . FEED_TEST_SERVER_PORT, '-t', $feedDir];
        self::$server = new Process($cmd, null, ['PHP_CLI_SERVER_WORKERS' => '1'], null, null);
        self::$server->disableOutput();
        self::$server->start();

        for ($i = 0; $i < 15; $i++) {
            $errorCode = 0;
            $errorString = '';
            $sock = \fsockopen('127.0.0.1', FEED_TEST_SERVER_PORT, $errorCode, $errorString, timeout: 1);
            if (\is_resource($sock)) {
                \fclose($sock);
                break;
            }
            \usleep(100_000);
        }
    }

    public static function stop(): void
    {
        if (self::$server !== null && self::$server->isRunning()) {
            self::$server->stop();
            self::$server = null;
        }
    }
}
