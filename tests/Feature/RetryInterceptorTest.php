<?php

declare(strict_types=1);

use Iris\Code;
use Iris\Interceptor\LoggingInterceptor;
use Iris\Interceptor\RetryInterceptor;
use Tests\Proto\FailurePatternRequest;
use Tests\Proto\PBEmpty;

test('succeeds after transient failures', function () {
    $client = testClient();
    $client->interceptors(new RetryInterceptor(maxAttempts: 3));

    $reply = new PBEmpty();

    $result = $client->GetFailurePattern(
        (new FailurePatternRequest())
            ->setFailTimes(2)
            ->setErrorCode(Code::Unavailable->value)
            ->setKey('test-' . uniqid()),
        $reply,
    );

    expect($result->code)->toBe(Code::OK);
});

test('respects max attempts', function () {
    $client = testClient();
    $client->interceptors(new RetryInterceptor(maxAttempts: 2));

    $reply = new PBEmpty();

    $result = $client->GetFailurePattern(
        (new FailurePatternRequest())
            ->setFailTimes(5)
            ->setErrorCode(Code::Unavailable->value)
            ->setKey('test-' . uniqid()),
        $reply,
    );

    expect($result->code)->not->toBeNull();
    expect($result->code)->toBe(Code::Unavailable);
});

test('does not retry non-retryable codes', function () {
    $client = testClient();
    $client->interceptors(new RetryInterceptor(maxAttempts: 3));

    $reply = new PBEmpty();

    $result = $client->GetFailurePattern(
        (new FailurePatternRequest())
            ->setFailTimes(1)
            ->setErrorCode(Code::InvalidArgument->value)
            ->setKey('test-' . uniqid()),
        $reply,
    );

    expect($result->code)->not->toBeNull();
    expect($result->code)->toBe(Code::InvalidArgument);
});

test('works with custom retryable codes', function () {
    $client = testClient();
    $client->interceptors(new RetryInterceptor(
        maxAttempts: 3,
        retryableCodes: [Code::Internal],
    ));

    $reply = new PBEmpty();

    $result = $client->GetFailurePattern(
        (new FailurePatternRequest())
            ->setFailTimes(2)
            ->setErrorCode(Code::Internal->value)
            ->setKey('test-' . uniqid()),
        $reply,
    );

    expect($result->code)->toBe(Code::OK);
});

test('works with other interceptors', function () {
    $client = testClient();
    $reply = new PBEmpty();

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
        $reply,
    );

    // LoggingInterceptor only sees the final result after all retries
    expect($logs)->toBe([
        'gRPC call started',
        'gRPC call completed',
    ]);
    expect($result->code)->toBe(Code::OK);
});

test('uses exponential backoff', function () {
    $client = testClient();
    $client->interceptors(new RetryInterceptor(
        maxAttempts: 3,
        delayMs: 50,
        multiplier: 2.0,
    ));

    $reply = new PBEmpty();

    $start = microtime(true);
    $client->GetFailurePattern(
        (new FailurePatternRequest())
            ->setFailTimes(2)
            ->setErrorCode(Code::Unavailable->value)
            ->setKey('test-' . uniqid()),
        $reply,
    );
    $duration = (microtime(true) - $start) * 1000;

    // Should have at least 2 retries with delays:
    // Attempt 1: 50ms * 2^0 = 50ms
    // Attempt 2: 50ms * 2^1 = 100ms
    // So at least more than 150ms
    expect($duration)->toBeGreaterThan(150);
});
