<?php

use Iris\CallAttempt;
use Iris\CallInfo;
use Iris\CallOption;
use Iris\Encoding;
use Iris\Error;

use function Iris\encoding;

describe('encoding', function () {
    test('supports gzip encoding', function () {
        $client = testClient();

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

        $client->globalOpts(new class($identitySize, $gzipSize) extends CallOption {
            public function __construct(
                private int &$identitySize,
                private int &$gzipSize,
            ) {}

            public function after(CallInfo $info, CallAttempt $attempt): void
            {
                if ($info->enc === Encoding::Identity) {
                    $this->identitySize = $attempt->curlInfo['request_size'];
                } else {
                    $this->gzipSize = $attempt->curlInfo['request_size'];
                }
            }
        });

        expect($client->GetDataTypes($request, encoding(Encoding::Gzip)))->not->toBeInstanceOf(Error::class);
        expect($client->GetDataTypes($request, encoding(Encoding::Identity)))->not->toBeInstanceOf(Error::class);
        
        expect($identitySize)->toBeGreaterThan(0);
        expect($gzipSize)->toBeGreaterThan(0);
        expect($gzipSize)->toBeLessThan($identitySize);
    });
});
