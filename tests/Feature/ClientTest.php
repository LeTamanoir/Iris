<?php

use Iris\Error;
use Tests\Proto\GetTestRequest;

test('GetTest returns echoed request', function () {
    $request = new GetTestRequest();
    $request->setStrTest('test');
    $request->setIntTest(42);
    $request->setBoolTest(true);
    $request->setFloatTest(3.14);
    $request->setDoubleTest(2.71);
    $request->setBytesTest('bytes');
    $request->setMapTest(['key' => 'value']);

    $response = client()->GetTest($request);

    expect($response)->not->toBeInstanceOf(Error::class);
    expect(serializeMsg($response))->toBe(serializeMsg($request));
});
