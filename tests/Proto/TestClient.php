<?php

declare(strict_types=1);

namespace Tests\Proto;

use Iris\UnaryCall;

class GetDataTypesResponse extends UnaryCall
{
    public DataTypes $data;
}

class GetEmptyResponse extends UnaryCall
{
    public PBEmpty $data;
}

class TestClient extends \Iris\Client
{
    public function GetDataTypes(DataTypes $request): GetDataTypesResponse
    {
        return $this->invoke('/test.TestService/GetDataTypes', $request, new GetDataTypesResponse());
    }

    public function GetEmpty(PBEmpty $request): GetEmptyResponse
    {
        return $this->invoke('/test.TestService/GetEmpty', $request, new GetEmptyResponse());
    }

    public function GetDelayRequest(DelayRequest $request): GetEmptyResponse
    {
        return $this->invoke('/test.TestService/GetDelayRequest', $request, new GetEmptyResponse());
    }

    public function GetFailurePattern(FailurePatternRequest $request): GetEmptyResponse
    {
        return $this->invoke('/test.TestService/GetFailurePattern', $request, new GetEmptyResponse());
    }
}
