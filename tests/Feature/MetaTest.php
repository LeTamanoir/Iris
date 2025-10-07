<?php

use Tests\Proto\PBEmpty;

test('send and receive meta', function () {
    $client = testClient();

    $call = $client->meta(['x-test' => ['Hello world']])->GetMeta(new PBEmpty());

    expect($call->meta['x-test'])->toBe(['Hello world']);
});

test('send and receive multiple meta', function () {
    $client = testClient();

    $call = $client->meta(['x-test' => ['Hello world', 'Hello world2']])->GetMeta(new PBEmpty());

    expect($call->meta['x-test'])->toBe(['Hello world', 'Hello world2']);
});
