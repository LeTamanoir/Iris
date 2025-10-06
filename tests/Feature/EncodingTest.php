<?php

use Google\Protobuf\Internal\Message;
use Iris\CallCtx;
use Iris\Encoding;
use Iris\Interceptor;
use Iris\UnaryCall;

// TODO: add back encoding
// describe('encoding', function () {
//     test('supports gzip encoding', function () {
//         $client = testClient();

//         $request = new Tests\Proto\DataTypes();
//         $request->setStrTest('test');
//         $request->setIntTest(1);
//         $request->setBoolTest(true);
//         $request->setFloatTest(1.0);
//         $request->setDoubleTest(1.0);
//         $request->setBytesTest(str_repeat('a', 1024)); // easy to compress
//         $request->setMapTest(['test' => 'test']);

//         $identitySize = 0;
//         $gzipSize = 0;

//         $client->interceptors(new class($identitySize, $gzipSize) extends Interceptor {
//             public function __construct(
//                 private int &$identitySize,
//                 private int &$gzipSize,
//             ) {}

//             public function interceptUnary(CallCtx $ctx, Message $reply, callable $invoker): UnaryCall
//             {
//                 $call = $invoker($ctx, $reply);
//                 if ($ctx->enc === Encoding::Identity) {
//                     $this->identitySize = $call->curlInfo['request_size'];
//                 } else {
//                     $this->gzipSize = $call->curlInfo['request_size'];
//                 }
//                 return $call;
//             }
//         });

//         // expect($client->GetDataTypes($request, encoding(Encoding::Gzip)))->not->toBeInstanceOf(Error::class);
//         // expect($client->GetDataTypes($request, encoding(Encoding::Identity)))->not->toBeInstanceOf(Error::class);

//         expect($identitySize)->toBeGreaterThan(0);
//         expect($gzipSize)->toBeGreaterThan(0);
//         expect($gzipSize)->toBeLessThan($identitySize);
//     });
// });
