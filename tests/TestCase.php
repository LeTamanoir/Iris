<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static null|int $serverPid = null;

    private static function logf(string $message, ...$args): void
    {
        if ((bool) getenv('TEST_VERBOSE')) {
            fprintf(STDERR, $message, ...$args);
        }
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $port = getenv('TEST_SERVER_PORT');
        $bin = realpath(__DIR__ . '/../test-server/testserver');

        if (filter_var($port, FILTER_VALIDATE_INT) === false) {
            fprintf(STDERR, "Test server port is not a valid integer\n");
            exit(1);
        }

        if (!$bin) {
            fprintf(STDERR, "Test server binary not found, run `composer build-test-server` to build it\n");
            exit(1);
        }

        // Kill any existing process on port
        exec("lsof -ti:{$port} | xargs kill -9 2>/dev/null", result_code: $code);
        if ($code !== 0) {
            fprintf(STDERR, "Failed to kill existing process on port {$port} (code: {$code})\n");
            exit(1);
        }

        // Start the test server in background
        $process = proc_open("{$bin} -port {$port}", [], $_);

        if ($process === false) {
            fprintf(STDERR, "Failed to start test server\n");
            exit(1);
        }

        $status = proc_get_status($process);

        self::$serverPid = $status['pid'];
        self::logf("Test server started (pid: %d, port: %d)\n", self::$serverPid, $port);

        // Wait for server to be ready
        $maxAttempts = 20;
        $attempt = 0;
        while ($attempt < $maxAttempts) {
            $connection = @fsockopen('localhost', $port, $errno, $errstr, 1);
            if ($connection !== false) {
                self::logf("Test server is ready (pid: %d, port: %d)\n", self::$serverPid, $port);
                fclose($connection);
                break;
            }
            self::logf("Test server not ready (attempt: %d, port: %d)\n", $attempt, $port);
            usleep(100000); // 100ms
            $attempt++;
        }

        if ($attempt === $maxAttempts) {
            self::logf("Test server failed to start within timeout (port: %d)\n", $port);
            self::tearDownAfterClass();
            exit(1);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverPid !== null) {
            self::logf("Killing test server (pid: %d)\n", self::$serverPid);
            posix_kill(self::$serverPid, SIGKILL);
            self::$serverPid = null;
        }

        parent::tearDownAfterClass();
    }
}
