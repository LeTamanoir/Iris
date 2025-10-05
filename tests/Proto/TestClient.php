<?php

declare(strict_types=1);

namespace Tests\Proto;

class TestClient extends \Iris\Client
{
    public function GetTest(GetTestRequest $request): GetTestRequest|\Iris\Error
    {
        return $this->invoke('/test.TestService/GetTest', $request, new GetTestRequest());
    }

    public function EchoFast(EchoRequest $request): EchoResponse|\Iris\Error
    {
        return $this->invoke('/test.TestService/EchoFast', $request, new EchoResponse());
    }

    public function EchoSlow(EchoRequest $request): EchoResponse|\Iris\Error
    {
        return $this->invoke('/test.TestService/EchoSlow', $request, new EchoResponse());
    }

    public function ReturnsInvalidArgument(PBEmpty $request): PBEmpty|\Iris\Error
    {
        return $this->invoke('/test.TestService/ReturnsInvalidArgument', $request, new PBEmpty());
    }

    public function ReturnsNotFound(PBEmpty $request): PBEmpty|\Iris\Error
    {
        return $this->invoke('/test.TestService/ReturnsNotFound', $request, new PBEmpty());
    }

    public function ReturnsPermissionDenied(PBEmpty $request): PBEmpty|\Iris\Error
    {
        return $this->invoke('/test.TestService/ReturnsPermissionDenied', $request, new PBEmpty());
    }

    public function ReturnsUnavailable(PBEmpty $request): PBEmpty|\Iris\Error
    {
        return $this->invoke('/test.TestService/ReturnsUnavailable', $request, new PBEmpty());
    }
}
