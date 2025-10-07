<?php

declare(strict_types=1);

use Iris\Code;
use Iris\Interceptor\LoggingInterceptor;
use Tests\Proto\DataTypes;
use Tests\Proto\FailurePatternRequest;

test('logs the call success', function () {
    $logger = new class() extends \Psr\Log\AbstractLogger {
        public array $logs = [];

        public function log($level, $message, array $context = []): void
        {
            $this->logs[] = $message;
        }
    };

    $client = testClient()->interceptors(new LoggingInterceptor($logger));

    $client->GetDataTypes(new DataTypes());

    expect($logger->logs)->toBe([
        'gRPC call started',
        'gRPC call completed',
    ]);
});

test('logs the call failure', function () {
    $logger = new class() extends \Psr\Log\AbstractLogger {
        public array $logs = [];

        public function log($level, $message, array $context = []): void
        {
            $this->logs[] = $message;
        }
    };

    $client = testClient()->interceptors(new LoggingInterceptor($logger));

    $client->GetFailurePattern(new FailurePatternRequest()->setErrorCode(Code::Unavailable->value));

    expect($logger->logs)->toBe([
        'gRPC call started',
        'gRPC call failed',
    ]);
});

test('with multiple loggers', function () {
    $logs = [];
    $ids = [];

    $logger = fn(int $i, array &$logs, array &$ids) => new class($i, $logs, $ids) extends \Psr\Log\AbstractLogger {
        public function __construct(
            private int $i,
            public array &$logs,
            public array &$ids,
        ) {}

        public function log($level, $message, array $context = []): void
        {
            $this->logs[] = $this->i . ' ' . $message;
            $this->ids[] = $context['call_id'];
        }
    };

    $client = testClient()->interceptors(
        new LoggingInterceptor($logger(1, $logs, $ids)),
        new LoggingInterceptor($logger(2, $logs, $ids)),
    );

    $client->GetDataTypes(new DataTypes());

    expect(count(array_unique($ids)))->toBe(1);

    expect($logs)->toBe([
        '1 gRPC call started',
        '2 gRPC call started',
        '2 gRPC call completed',
        '1 gRPC call completed',
    ]);
});
