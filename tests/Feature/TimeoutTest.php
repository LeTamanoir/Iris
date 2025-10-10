<?php

use Iris\CallOptions;
use Iris\Code;
use Tests\Proto\DelayRequest;
use Tests\Proto\TestService;

test('times out after the specified timeout', function () {
    $conn = testConn();

    $request = new DelayRequest();
    $request->setMs(100);

    $call = TestService::GetDelayRequest($request, new CallOptions(timeout: 10));
    $conn->invoke($call);

    expect($call->code)->toBe(Code::DeadlineExceeded);
});

test('does not time out before the specified timeout', function () {
    $conn = testConn();

    $request = new DelayRequest();
    $request->setMs(100);

    $call = TestService::GetDelayRequest($request, new CallOptions(timeout: 150));
    $conn->invoke($call);

    expect($call->code)->toBe(Code::OK);
});
