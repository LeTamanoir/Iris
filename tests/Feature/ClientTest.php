<?php

declare(strict_types=1);

use Iris\CallOptions;
use Iris\Code;
use Iris\Connection;
use Iris\Encoding;
use Iris\Interceptor;
use Iris\Interceptor\LoggingInterceptor;
use Iris\Interceptor\RetryInterceptor;
use Iris\Interceptor\ThrottleInterceptor;
use Iris\UnaryCall;
use Tests\Proto\DataTypes;
use Tests\Proto\DelayRequest;
use Tests\Proto\FailurePatternRequest;
use Tests\Proto\GetDataTypesResponse;
use Tests\Proto\PBEmpty;
use Tests\Proto\TestService;

describe('data transfer', function () {
    test('returns correct data', function () {
        $request = new DataTypes();
        $request->setStrTest('test');
        $request->setIntTest(42);
        $request->setBoolTest(true);
        $request->setFloatTest(3.14);
        $request->setDoubleTest(2.71);
        $request->setBytesTest('bytes');
        $request->setMapTest(['key' => 'value']);

        $conn = testConn();

        $call = TestService::GetDataTypes($request);
        $conn->invoke($call);

        expect($call->code)->toBe(Code::OK);
        expect(serializeMsg($call->data))->toBe(serializeMsg($request));
    });
});

describe('interceptors', function () {
    test('global interceptors are applied to all calls', function () {
        $calledCount = 0;

        $conn = testConn(new CallOptions(interceptors: [new class($calledCount) extends Interceptor {
            public function __construct(
                private int &$calledCount,
            ) {}

            public function interceptUnary(UnaryCall $call, callable $invoker): UnaryCall
            {
                $this->calledCount++;
                return $invoker($call);
            }
        }]));

        $calls = [];

        $calls[] = TestService::GetEmpty(new PBEmpty());
        $calls[] = TestService::GetEmpty(new PBEmpty());

        $conn->invoke(...$calls);

        expect($calledCount)->toBe(2);
    });

    test('local interceptors are applied to the call', function () {
        $conn = testConn();

        $calledCount = 0;

        $request = new PBEmpty();

        $calls = [];

        $calls[] = TestService::GetEmpty($request, new CallOptions(interceptors: [
            new class($calledCount) extends Interceptor {
                public function __construct(
                    private int &$calledCount,
                ) {}

                public function interceptUnary(UnaryCall $call, callable $invoker): UnaryCall
                {
                    $this->calledCount++;
                    return $invoker($call);
                }
            },
        ]));

        $calls[] = TestService::GetEmpty($request);

        $conn->invoke(...$calls);

        expect($calledCount)->toBe(1);
    });

    test('global and local interceptors are applied to the call', function () {
        $calledCount = 0;

        $conn = testConn(new CallOptions(interceptors: [
            new class($calledCount) extends Interceptor {
                public function __construct(
                    private int &$calledCount,
                ) {}

                public function interceptUnary(UnaryCall $call, callable $invoker): UnaryCall
                {
                    $this->calledCount++;
                    return $invoker($call);
                }
            },
        ]));

        $request = new PBEmpty();

        $call = TestService::GetEmpty($request, new CallOptions(interceptors: [
            new class($calledCount) extends Interceptor {
                public function __construct(
                    private int &$calledCount,
                ) {}

                public function interceptUnary(UnaryCall $call, callable $invoker): UnaryCall
                {
                    $this->calledCount++;
                    return $invoker($call);
                }
            },
        ]));

        $conn->invoke($call);

        expect($calledCount)->toBe(2);
    });
});
