<?php

use Iris\CallOptions;
use Iris\Code;
use Iris\Encoding;
use Iris\Interceptor;
use Iris\UnaryCall;
use Tests\Proto\TestService;

test('supports gzip encoding', function () {
    $request = new Tests\Proto\DataTypes();
    $request->setStrTest('test');
    $request->setIntTest(1);
    $request->setBoolTest(true);
    $request->setFloatTest(1.0);
    $request->setDoubleTest(1.0);
    $request->setBytesTest(str_repeat('a', 1024)); // easy to compress
    $request->setMapTest(['test' => 'test']);

    $identitySize = 0;
    $gzipSize = 0;

    $conn = testConn(new CallOptions(interceptors: [
        new class($identitySize, $gzipSize) extends Interceptor {
            public function __construct(
                private int &$identitySize,
                private int &$gzipSize,
            ) {}

            public function interceptUnary(UnaryCall $call, callable $invoker): UnaryCall
            {
                $call = $invoker($call);
                if ($call->options->encoding === Encoding::Identity) {
                    $this->identitySize = $call->curlInfo['request_size'];
                } else {
                    $this->gzipSize = $call->curlInfo['request_size'];
                }
                return $call;
            }
        },
    ]));

    $gzip = TestService::GetDataTypes($request, new CallOptions(encoding: Encoding::Gzip));
    $identity = TestService::GetDataTypes($request, new CallOptions(encoding: Encoding::Identity));

    $conn->invoke($gzip, $identity);

    expect($gzip->code)->toBe(Code::OK);
    expect($identity->code)->toBe(Code::OK);

    expect($identitySize)->toBeGreaterThan(0);
    expect($gzipSize)->toBeGreaterThan(0);
    expect($gzipSize)->toBeLessThan($identitySize);
});
