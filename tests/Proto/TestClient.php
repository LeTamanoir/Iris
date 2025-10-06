<?php

declare(strict_types=1);

namespace Tests\Proto;

use Iris\Interceptor;
use Iris\UnaryCall;

class TestClient extends \Iris\Client
{
    public function GetDataTypes(DataTypes $request, DataTypes|null &$reply, Interceptor ...$its): UnaryCall
    {
        $reply ??= new DataTypes();
        return $this->invoke('/test.TestService/GetDataTypes', $request, $reply, ...$its);
    }

    public function GetEmpty(PBEmpty $request, PBEmpty|null &$reply, Interceptor ...$its): UnaryCall
    {
        $reply ??= new PBEmpty();
        return $this->invoke('/test.TestService/GetEmpty', $request, $reply, ...$its);
    }

    public function GetDelayRequest(DelayRequest $request, PBEmpty|null &$reply, Interceptor ...$its): UnaryCall
    {
        $reply ??= new PBEmpty();
        return $this->invoke('/test.TestService/GetDelayRequest', $request, $reply, ...$its);
    }

    public function GetFailurePattern(
        FailurePatternRequest $request,
        PBEmpty|null &$reply,
        Interceptor ...$its,
    ): UnaryCall {
        $reply ??= new PBEmpty();
        return $this->invoke('/test.TestService/GetFailurePattern', $request, $reply, ...$its);
    }
}
