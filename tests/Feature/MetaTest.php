<?php

use Iris\CallOptions;
use Tests\Proto\PBEmpty;
use Tests\Proto\TestService;

test('send and receive meta', function () {
    $conn = testConn();

    $call = TestService::GetMeta(
        new PBEmpty(),
        new CallOptions(meta: [
            'x-test' => ['Hello world'],
            'x-test-bin' => ['Hello world'],
        ]),
    );

    $conn->invoke($call);

    expect($call->meta['x-test'])->toBe(['Hello world']);
    expect($call->meta['x-test-bin'])->toBe(['Hello world']);
});

test('send and receive multiple meta', function () {
    $conn = testConn();

    $call = TestService::GetMeta(
        new PBEmpty(),
        new CallOptions(meta: [
            'x-test' => ['Hello world', 'Hello world2'],
            'x-test-bin' => ['Hello world', 'Hello world2'],
        ]),
    );

    $conn->invoke($call);

    expect($call->meta['x-test'])->toBe(['Hello world', 'Hello world2']);
    expect($call->meta['x-test-bin'])->toBe(['Hello world', 'Hello world2']);
});
