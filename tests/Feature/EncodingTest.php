<?php

use Iris\CallCtx;
use Iris\Code;
use Iris\Encoding;
use Iris\Interceptor;
use Iris\UnaryCall;

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

    $client = testClient()->interceptors(new class($identitySize, $gzipSize) extends Interceptor {
        public function __construct(
            private int &$identitySize,
            private int &$gzipSize,
        ) {}

        public function interceptUnary(CallCtx $ctx, UnaryCall $reply, callable $invoker): UnaryCall
        {
            $call = $invoker($ctx, $reply);
            if ($ctx->enc === Encoding::Identity) {
                $this->identitySize = $call->curlInfo['request_size'];
            } else {
                $this->gzipSize = $call->curlInfo['request_size'];
            }
            return $call;
        }
    });

    expect($client->encoding(Encoding::Gzip)->GetDataTypes($request)->code)->toBe(Code::OK);
    expect($client->encoding(Encoding::Identity)->GetDataTypes($request)->code)->toBe(Code::OK);

    expect($identitySize)->toBeGreaterThan(0);
    expect($gzipSize)->toBeGreaterThan(0);
    expect($gzipSize)->toBeLessThan($identitySize);
});
