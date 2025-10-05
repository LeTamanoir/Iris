<?php

declare(strict_types=1);

namespace Tests\Proto;

use Iris\CallOption;

class TestClient extends \Iris\Client
{
    public function GetDataTypes(DataTypes $request, CallOption ...$opts): DataTypes|\Iris\Error
    {
        return $this->invoke('/test.TestService/GetDataTypes', $request, new DataTypes(), ...$opts);
    }

    public function GetEmpty(PBEmpty $request, CallOption ...$opts): PBEmpty|\Iris\Error
    {
        return $this->invoke('/test.TestService/GetEmpty', $request, new PBEmpty(), ...$opts);
    }

    public function GetDelayRequest(DelayRequest $request, CallOption ...$opts): PBEmpty|\Iris\Error
    {
        return $this->invoke('/test.TestService/GetDelayRequest', $request, new PBEmpty(), ...$opts);
    }
}
