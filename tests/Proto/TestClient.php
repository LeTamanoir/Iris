<?php

declare(strict_types=1);

namespace Tests\Proto;

use Iris\CallOption;

class TestClient extends \Iris\Client
{
    public function GetDataTypes(DataTypes $request, CallOption ...$opts): DataTypes|\Iris\Error
    {
        $reply = new DataTypes();
        return $this->invoke('/test.TestService/GetDataTypes', $request, $reply, ...$opts) ?? $reply;
    }

    public function GetEmpty(PBEmpty $request, CallOption ...$opts): PBEmpty|\Iris\Error
    {
        $reply = new PBEmpty();
        return $this->invoke('/test.TestService/GetEmpty', $request, $reply, ...$opts) ?? $reply;
    }

    public function GetDelayRequest(DelayRequest $request, CallOption ...$opts): PBEmpty|\Iris\Error
    {
        $reply = new PBEmpty();
        return $this->invoke('/test.TestService/GetDelayRequest', $request, $reply, ...$opts) ?? $reply;
    }

    public function GetFailurePattern(FailurePatternRequest $request, CallOption ...$opts): PBEmpty|\Iris\Error
    {
        $reply = new PBEmpty();
        return $this->invoke('/test.TestService/GetFailurePattern', $request, $reply, ...$opts) ?? $reply;
    }
}
