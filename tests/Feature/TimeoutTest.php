<?php

use Tests\Proto\DelayRequest;

// TODO: add back timeout
// describe('timeout', function () {
//     test('times out after the specified timeout', function () {
//         $client = testClient();

//         $request = new DelayRequest();

//         $request->setMs(100);

//         $data = $client->GetDelayRequest($request, timeout(50));

//         expect($data)->toBeInstanceOf(Error::class);
//         expect($data->code)->toBe(Code::DeadlineExceeded);
//     });

//     test('does not time out before the specified timeout', function () {
//         $client = testClient();

//         $request = new DelayRequest();

//         $request->setMs(100);

//         $data = $client->GetDelayRequest($request, timeout(150));

//         expect($data)->not->toBeInstanceOf(Error::class);
//         expect($data)->toBeInstanceOf(PBEmpty::class);
//     });
// });
