<?php

use Tests\Proto\PBEmpty;

test('send and receive meta', function () {
    $client = testClient();

    $call = $client->meta([
        'x-test' => ['Hello world'],
        'x-test-bin' => ['Hello world'],
    ])->GetMeta(new PBEmpty());

    expect($call->meta['x-test'])->toBe(['Hello world']);
    expect($call->meta['x-test-bin'])->toBe(['Hello world']);
});

test('send and receive multiple meta', function () {
    $client = testClient();

    $call = $client->meta([
        'x-test' => ['Hello world', 'Hello world2'],
        'x-test-bin' => ['Hello world', 'Hello world2'],
    ])->GetMeta(new PBEmpty());

    expect($call->meta['x-test'])->toBe(['Hello world', 'Hello world2']);
    expect($call->meta['x-test-bin'])->toBe(['Hello world', 'Hello world2']);
});
