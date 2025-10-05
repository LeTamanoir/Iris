<?php

declare(strict_types=1);

namespace Tests\Proto;

use Iris\CallOption;

class TestClient extends \Iris\Client
{
    public function GetTest(GetTestRequest $request, CallOption ...$opts): GetTestRequest|\Iris\Error
    {
        return $this->invoke('/test.TestService/GetTest', $request, new GetTestRequest(), ...$opts);
    }

    public function EchoFast(EchoRequest $request, CallOption ...$opts): EchoResponse|\Iris\Error
    {
        return $this->invoke('/test.TestService/EchoFast', $request, new EchoResponse(), ...$opts);
    }

    public function EchoSlow(EchoRequest $request, CallOption ...$opts): EchoResponse|\Iris\Error
    {
        return $this->invoke('/test.TestService/EchoSlow', $request, new EchoResponse(), ...$opts);
    }

    public function ReturnsInvalidArgument(PBEmpty $request, CallOption ...$opts): PBEmpty|\Iris\Error
    {
        return $this->invoke('/test.TestService/ReturnsInvalidArgument', $request, new PBEmpty(), ...$opts);
    }

    public function ReturnsNotFound(PBEmpty $request, CallOption ...$opts): PBEmpty|\Iris\Error
    {
        return $this->invoke('/test.TestService/ReturnsNotFound', $request, new PBEmpty(), ...$opts);
    }

    public function ReturnsPermissionDenied(PBEmpty $request, CallOption ...$opts): PBEmpty|\Iris\Error
    {
        return $this->invoke('/test.TestService/ReturnsPermissionDenied', $request, new PBEmpty(), ...$opts);
    }

    public function ReturnsUnavailable(PBEmpty $request, CallOption ...$opts): PBEmpty|\Iris\Error
    {
        return $this->invoke('/test.TestService/ReturnsUnavailable', $request, new PBEmpty(), ...$opts);
    }
}
