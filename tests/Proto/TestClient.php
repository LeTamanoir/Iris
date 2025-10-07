<?php

declare(strict_types=1);

namespace Tests\Proto;

use Iris\Interceptor;
use Iris\UnaryCall;

class GetDataTypesResponse extends UnaryCall
{
    public function __construct(
        public DataTypes $data = new DataTypes(),
    ) {}
}

class GetEmptyResponse extends UnaryCall
{
    public function __construct(
        public PBEmpty $data = new PBEmpty(),
    ) {}
}

class TestClient extends \Iris\Client
{
    public function GetDataTypes(DataTypes $request, Interceptor ...$its): GetDataTypesResponse
    {
        return $this->invoke('/test.TestService/GetDataTypes', $request, new GetDataTypesResponse(), ...$its);
    }

    public function GetEmpty(PBEmpty $request, Interceptor ...$its): GetEmptyResponse
    {
        return $this->invoke('/test.TestService/GetEmpty', $request, new GetEmptyResponse(), ...$its);
    }

    public function GetDelayRequest(DelayRequest $request, Interceptor ...$its): GetEmptyResponse
    {
        return $this->invoke('/test.TestService/GetDelayRequest', $request, new GetEmptyResponse(), ...$its);
    }

    public function GetFailurePattern(FailurePatternRequest $request, Interceptor ...$its): GetEmptyResponse
    {
        return $this->invoke('/test.TestService/GetFailurePattern', $request, new GetEmptyResponse(), ...$its);
    }
}
