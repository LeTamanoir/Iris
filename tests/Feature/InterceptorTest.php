<?php

declare(strict_types=1);

use Iris\Code;
use Iris\Interceptor\LoggingInterceptor;
use Iris\Interceptor\RetryInterceptor;
use Tests\Proto\DataTypes;
use Tests\Proto\DelayRequest;
use Tests\Proto\FailurePatternRequest;

use function Iris\timeout;

describe('logging', function () {
    test('logs the call success', function () {
        $client = testClient();

        $logger = new class() extends \Psr\Log\AbstractLogger {
            public array $logs = [];

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = $message;
            }
        };

        $client->interceptors(new LoggingInterceptor($logger));

        $client->GetDataTypes(new DataTypes());

        expect($logger->logs)->toBe([
            'gRPC call started',
            'gRPC call completed',
        ]);
    });

    test('logs the call failure', function () {
        $client = testClient();

        $logger = new class() extends \Psr\Log\AbstractLogger {
            public array $logs = [];

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = $message;
            }
        };

        $client->interceptors(new LoggingInterceptor($logger));

        $client->GetDelayRequest(new DelayRequest(['ms' => 100]), timeout(1));

        expect($logger->logs)->toBe([
            'gRPC call started',
            'gRPC call failed',
        ]);
    });

    test('with multiple loggers', function () {
        $client = testClient();
        $logs = [];

        $logger = fn(int $i, array &$logs) => new class($i, $logs) extends \Psr\Log\AbstractLogger {
            public function __construct(
                private int $i,
                public array &$logs,
            ) {}

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = $this->i . ' ' . $message;
            }
        };

        $client->interceptors(new LoggingInterceptor($logger(1, $logs)), new LoggingInterceptor($logger(2, $logs)));

        $client->GetDataTypes(new DataTypes());

        expect($logs)->toBe([
            '1 gRPC call started',
            '2 gRPC call started',
            '2 gRPC call completed',
            '1 gRPC call completed',
        ]);
    });
});

describe('retry', function () {
    test('succeeds after transient failures', function () {
        $client = testClient();
        $client->interceptors(new RetryInterceptor(maxAttempts: 3));

        $result = $client->GetFailurePattern(
            (new FailurePatternRequest())
                ->setFailTimes(2)
                ->setErrorCode(Code::Unavailable->value)
                ->setKey('test-' . uniqid()),
        );

        expect($result)->toBeInstanceOf(\Tests\Proto\PBEmpty::class);
    });

    test('respects max attempts', function () {
        $client = testClient();
        $client->interceptors(new RetryInterceptor(maxAttempts: 2));

        $result = $client->GetFailurePattern(
            (new FailurePatternRequest())
                ->setFailTimes(5)
                ->setErrorCode(Code::Unavailable->value)
                ->setKey('test-' . uniqid()),
        );

        expect($result)->not->toBeNull();
        expect($result->code)->toBe(Code::Unavailable);
    });

    test('does not retry non-retryable codes', function () {
        $client = testClient();
        $client->interceptors(new RetryInterceptor(maxAttempts: 3));

        $result = $client->GetFailurePattern(
            (new FailurePatternRequest())
                ->setFailTimes(1)
                ->setErrorCode(Code::InvalidArgument->value)
                ->setKey('test-' . uniqid()),
        );

        expect($result)->not->toBeNull();
        expect($result->code)->toBe(Code::InvalidArgument);
    });

    test('works with custom retryable codes', function () {
        $client = testClient();
        $client->interceptors(new RetryInterceptor(
            maxAttempts: 3,
            retryableCodes: [Code::Internal],
        ));

        $result = $client->GetFailurePattern(
            (new FailurePatternRequest())
                ->setFailTimes(2)
                ->setErrorCode(Code::Internal->value)
                ->setKey('test-' . uniqid()),
        );

        expect($result)->toBeInstanceOf(\Tests\Proto\PBEmpty::class);
    });

    test('works with other interceptors', function () {
        $client = testClient();
        $logs = [];

        $logger = new class($logs) extends \Psr\Log\AbstractLogger {
            public function __construct(
                public array &$logs,
            ) {}

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = $message;
            }
        };

        $client->interceptors(new LoggingInterceptor($logger), new RetryInterceptor(maxAttempts: 3));

        $result = $client->GetFailurePattern(
            (new FailurePatternRequest())
                ->setFailTimes(2)
                ->setErrorCode(Code::Unavailable->value)
                ->setKey('test-' . uniqid()),
        );

        // LoggingInterceptor only sees the final result after all retries
        expect($logs)->toBe([
            'gRPC call started',
            'gRPC call completed',
        ]);
        expect($result)->toBeInstanceOf(\Tests\Proto\PBEmpty::class);
    });

    test('uses exponential backoff', function () {
        $client = testClient();
        $client->interceptors(new RetryInterceptor(
            maxAttempts: 3,
            delayMs: 50,
            multiplier: 2.0,
        ));

        $start = microtime(true);
        $client->GetFailurePattern(
            (new FailurePatternRequest())
                ->setFailTimes(2)
                ->setErrorCode(Code::Unavailable->value)
                ->setKey('test-' . uniqid()),
        );
        $duration = (microtime(true) - $start) * 1000;

        // Should have at least 2 retries with delays:
        // Attempt 1: 50ms * 2^0 = 50ms
        // Attempt 2: 50ms * 2^1 = 100ms
        // So at least more than 150ms
        expect($duration)->toBeGreaterThan(150);
    });
});
