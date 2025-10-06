<?php

declare(strict_types=1);

use Iris\LoggingInterceptor;
use Tests\Proto\DataTypes;
use Tests\Proto\DelayRequest;

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
