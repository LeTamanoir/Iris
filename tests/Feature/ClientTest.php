<?php

declare(strict_types=1);

use Iris\CallOptions;
use Iris\Code;
use Iris\Connection;
use Iris\Encoding;
use Iris\Interceptor;
use Iris\Interceptor\LoggingInterceptor;
use Iris\Interceptor\RetryInterceptor;
use Iris\UnaryCall;
use Tests\Proto\DataTypes;
use Tests\Proto\DelayRequest;
use Tests\Proto\FailurePatternRequest;
use Tests\Proto\GetDataTypesResponse;
use Tests\Proto\PBEmpty;
use Tests\Proto\TestService;

test('async client', function () {
    // $client = new \Tests\Proto\TestClient('[::1]:50051');
    // $client = new

    $options = new CallOptions(interceptors: [
        // new LoggingInterceptor(new class extends \Psr\Log\AbstractLogger {
        //     public function log($level, string|Stringable $message, array $context = []): void
        //     {
        //         delta('2: ' . $message);
        //     }
        // }),
        // new LoggingInterceptor(new class extends \Psr\Log\AbstractLogger {
        //     public function log($level, string|Stringable $message, array $context = []): void
        //     {
        //         delta('1: ' . $message);
        //     }
        // }),
    ]);

    $calls = [];

    // $calls[] = TestService::GetDelayRequest(new DelayRequest()->setMs(100));
    // $calls[] = TestService::GetFailurePattern(new FailurePatternRequest()->setErrorCode(Code::Unavailable->value));
    // $calls[] = TestService::GetDelayRequest(new DelayRequest()->setMs(2000));
    // $calls[] = TestService::GetDelayRequest(new DelayRequest()->setMs(250));
    // $calls[] = TestService::GetDelayRequest(new DelayRequest()->setMs(500));

    $connection = new Connection('[::1]:50051', $options);

    // $connection->invoke(...$calls);

    $options = new CallOptions(interceptors: [
        // new LoggingInterceptor(new class extends \Psr\Log\AbstractLogger {
        //     public function log($level, string|Stringable $message, array $context = []): void
        //     {
        //         delta('[OUTSIDE] ' . $message);
        //     }
        // }),
        new RetryInterceptor(
            maxAttempts: 3,
            delayMs: 100,
            multiplier: 2,
            retryableCodes: [Code::Unavailable],
        ),
        // new LoggingInterceptor(new class extends \Psr\Log\AbstractLogger {
        //     public function log($level, string|Stringable $message, array $context = []): void
        //     {
        //         delta('[INSIDE] ' . $message);
        //     }
        // }),
    ]);

    $expectedCodes = [];
    for ($i = 0; $i < 100; $i++) {
        $calls[] = TestService::GetFailurePattern(
            new FailurePatternRequest()
                ->setErrorCode(Code::Unavailable->value)
                ->setFailTimes(2),
            $options,
        );
        $expectedCodes[] = Code::OK;
    }

    $connection->invoke(...$calls);

    dd($connection);

    // dd($retry);

    expect(array_column($calls, 'code'))->toBe($expectedCodes);
})->only();

// describe('data transfer', function () {
//     test('returns correct data', function () {
//         $request = new DataTypes();
//         $request->setStrTest('test');
//         $request->setIntTest(42);
//         $request->setBoolTest(true);
//         $request->setFloatTest(3.14);
//         $request->setDoubleTest(2.71);
//         $request->setBytesTest('bytes');
//         $request->setMapTest(['key' => 'value']);

//         $call = testClient()->GetDataTypes($request);

//         expect($call->code)->toBe(Code::OK);
//         expect(serializeMsg($call->data))->toBe(serializeMsg($request));
//     });
// });

// describe('interceptors', function () {
//     test('global interceptors are applied to all calls', function () {
//         $calledCount = 0;

//         $client = testClient()->interceptors(new class($calledCount) extends Interceptor {
//             public function __construct(
//                 private int &$calledCount,
//             ) {}

//             public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
//             {
//                 $this->calledCount++;
//                 return $invoker($ctx, $reply);
//             }
//         });

//         $request = new PBEmpty();

//         $client->GetEmpty($request);
//         $client->GetEmpty($request);

//         expect($calledCount)->toBe(2);
//     });

//     test('local interceptors are applied to the call', function () {
//         $client = testClient();

//         $calledCount = 0;

//         $request = new PBEmpty();

//         $client->interceptors(new class($calledCount) extends Interceptor {
//             public function __construct(
//                 private int &$calledCount,
//             ) {}

//             public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
//             {
//                 $this->calledCount++;
//                 return $invoker($ctx, $reply);
//             }
//         })->GetEmpty($request);

//         $client->GetEmpty($request);

//         expect($calledCount)->toBe(1);
//     });

//     test('global and local interceptors are applied to the call', function () {
//         $calledCount = 0;

//         $client = testClient()->interceptors(new class($calledCount) extends Interceptor {
//             public function __construct(
//                 private int &$calledCount,
//             ) {}

//             public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
//             {
//                 $this->calledCount++;
//                 return $invoker($ctx, $reply);
//             }
//         });

//         $request = new PBEmpty();

//         $client->interceptors(new class($calledCount) extends Interceptor {
//             public function __construct(
//                 private int &$calledCount,
//             ) {}

//             public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
//             {
//                 $this->calledCount++;
//                 return $invoker($ctx, $reply);
//             }
//         })->GetEmpty($request);

//         expect($calledCount)->toBe(2);
//     });
// });
