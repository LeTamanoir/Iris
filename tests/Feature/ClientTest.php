<?php

declare(strict_types=1);

use Iris\CallInfo;
use Iris\CallOption;
use Iris\Code;
use Iris\Duration;
use Iris\Error;
use Tests\Proto\DataTypes;
use Tests\Proto\DelayRequest;
use Tests\Proto\PBEmpty;

describe('data transfer', function () {
    test('returns correct data', function () {
        $request = new DataTypes();
        $request->setStrTest('test');
        $request->setIntTest(42);
        $request->setBoolTest(true);
        $request->setFloatTest(3.14);
        $request->setDoubleTest(2.71);
        $request->setBytesTest('bytes');
        $request->setMapTest(['key' => 'value']);

        $response = testClient()->GetDataTypes($request);

        expect($response)->not->toBeInstanceOf(Error::class);
        expect(serializeMsg($response))->toBe(serializeMsg($request));
    });
});

describe('call options', function () {
    test('global options are applied to all calls', function () {
        $client = testClient();

        $calledCount = 0;

        $client->globalOpts(new class($calledCount) extends CallOption {
            public function __construct(
                private int &$calledCount,
            ) {}

            public function before(CallInfo $info): null|Error
            {
                $this->calledCount++;
                return null;
            }
        });

        $request = new PBEmpty();

        $client->GetEmpty($request);
        $client->GetEmpty($request);

        expect($calledCount)->toBe(2);
    });

    test('local options are applied to the call', function () {
        $client = testClient();

        $calledCount = 0;

        $request = new PBEmpty();

        $client->GetEmpty($request, new class($calledCount) extends CallOption {
            public function __construct(
                private int &$calledCount,
            ) {}

            public function before(CallInfo $info): null|Error
            {
                $this->calledCount++;
                return null;
            }
        });

        $client->GetEmpty($request);

        expect($calledCount)->toBe(1);
    });

    test('global and local call options are applied to the call', function () {
        $client = testClient();

        $calledCount = 0;

        $client->globalOpts(new class($calledCount) extends CallOption {
            public function __construct(
                private int &$calledCount,
            ) {}

            public function before(CallInfo $info): null|Error
            {
                $this->calledCount++;
                return null;
            }
        });

        $request = new PBEmpty();

        $client->GetEmpty($request, new class($calledCount) extends CallOption {
            public function __construct(
                private int &$calledCount,
            ) {}

            public function before(CallInfo $info): null|Error
            {
                $this->calledCount++;
                return null;
            }
        });

        expect($calledCount)->toBe(2);
    });

    describe('timeout', function () {
        test('times out after the specified timeout', function () {
            $client = testClient();

            $request = new DelayRequest();
            $request->setMs(100);

            $data = $client->GetDelayRequest($request, timeout(50));

            expect($data)->toBeInstanceOf(Error::class);
            expect($data->code)->toBe(Code::DeadlineExceeded);
        });

        test('does not time out before the specified timeout', function () {
            $client = testClient();

            $request = new DelayRequest();
            $request->setMs(100);

            $data = $client->GetDelayRequest($request, timeout(150));

            expect($data)->not->toBeInstanceOf(Error::class);
            expect($data)->toBeInstanceOf(PBEmpty::class);
        });
    });
});
