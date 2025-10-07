<?php

declare(strict_types=1);

use Iris\CallCtx;
use Iris\Code;
use Iris\Interceptor;
use Iris\UnaryCall;
use Tests\Proto\DataTypes;
use Tests\Proto\PBEmpty;

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

        $call = testClient()->GetDataTypes($request);

        expect($call->code)->toBe(Code::OK);
        expect(serializeMsg($call->data))->toBe(serializeMsg($request));
    });
});

describe('interceptors', function () {
    test('global interceptors are applied to all calls', function () {
        $calledCount = 0;

        $client = testClient()->interceptors(new class($calledCount) extends Interceptor {
            public function __construct(
                private int &$calledCount,
            ) {}

            public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
            {
                $this->calledCount++;
                return $invoker($ctx, $reply);
            }
        });

        $request = new PBEmpty();

        $client->GetEmpty($request);
        $client->GetEmpty($request);

        expect($calledCount)->toBe(2);
    });

    test('local interceptors are applied to the call', function () {
        $client = testClient();

        $calledCount = 0;

        $request = new PBEmpty();

        $client->interceptors(new class($calledCount) extends Interceptor {
            public function __construct(
                private int &$calledCount,
            ) {}

            public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
            {
                $this->calledCount++;
                return $invoker($ctx, $reply);
            }
        })->GetEmpty($request);

        $client->GetEmpty($request);

        expect($calledCount)->toBe(1);
    });

    test('global and local interceptors are applied to the call', function () {
        $calledCount = 0;

        $client = testClient()->interceptors(new class($calledCount) extends Interceptor {
            public function __construct(
                private int &$calledCount,
            ) {}

            public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
            {
                $this->calledCount++;
                return $invoker($ctx, $reply);
            }
        });

        $request = new PBEmpty();

        $client->interceptors(new class($calledCount) extends Interceptor {
            public function __construct(
                private int &$calledCount,
            ) {}

            public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
            {
                $this->calledCount++;
                return $invoker($ctx, $reply);
            }
        })->GetEmpty($request);

        expect($calledCount)->toBe(2);
    });
});
