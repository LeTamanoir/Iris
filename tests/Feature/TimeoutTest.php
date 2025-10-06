<?php

use Iris\Code;
use Tests\Proto\DelayRequest;
use Tests\Proto\PBEmpty;

test('times out after the specified timeout', function () {
    $client = testClient();

    $request = new DelayRequest();

    $request->setMs(100);

    $call = $client->timeout(10)->GetDelayRequest($request, $reply);

    expect($call->code)->toBe(Code::DeadlineExceeded);
});

test('does not time out before the specified timeout', function () {
    $client = testClient();

    $request = new DelayRequest();

    $request->setMs(100);

    $call = $client->timeout(150)->GetDelayRequest($request, $reply);

    expect($call->code)->toBe(Code::OK);
    expect($reply)->toBeInstanceOf(PBEmpty::class);
});
