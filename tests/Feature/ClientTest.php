<?php

use Iris\CallCtx;
use Iris\CallInfo;
use Iris\CallOption;
use Iris\Error;
use Tests\Proto\EchoRequest;
use Tests\Proto\GetTestRequest;

describe('data transfer', function () {
    test('returns correct data', function () {
        $request = new GetTestRequest();
        $request->setStrTest('test');
        $request->setIntTest(42);
        $request->setBoolTest(true);
        $request->setFloatTest(3.14);
        $request->setDoubleTest(2.71);
        $request->setBytesTest('bytes');
        $request->setMapTest(['key' => 'value']);

        $response = testClient()->GetTest($request);

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

        $request = new EchoRequest();

        $client->EchoFast($request);
        $client->EchoFast($request);

        expect($calledCount)->toBe(2);
    });

    test('local options are applied to the call', function () {
        $client = testClient();

        $calledCount = 0;

        $request = new EchoRequest();

        $client->EchoFast($request, new class($calledCount) extends CallOption {
            public function __construct(
                private int &$calledCount,
            ) {}

            public function before(CallInfo $info): null|Error
            {
                $this->calledCount++;
                return null;
            }
        });

        $client->EchoFast($request);

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

        $request = new EchoRequest();

        $client->EchoFast($request, new class($calledCount) extends CallOption {
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
});
