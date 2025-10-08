<?php

declare(strict_types=1);

namespace Tests\Proto;

use Iris\CallOptions;
use Iris\UnaryCall;

class GetDataTypesCall extends UnaryCall
{
    public DataTypes $data;
}

class GetEmptyCall extends UnaryCall
{
    public PBEmpty $data;
}

class GetMetaCall extends UnaryCall
{
    public PBEmpty $data;
}

class TestService
{
    public static function GetDataTypes(DataTypes $request, CallOptions $options = new CallOptions()): GetDataTypesCall
    {
        $call = new GetDataTypesCall();
        $call->args = $request;
        $call->method = '/test.TestService/GetDataTypes';
        $call->options = $options;
        return $call;
    }

    public static function GetEmpty(PBEmpty $request, CallOptions $options = new CallOptions()): GetEmptyCall
    {
        $call = new GetEmptyCall();
        $call->args = $request;
        $call->method = '/test.TestService/GetEmpty';
        $call->options = $options;
        return $call;
    }

    public static function GetDelayRequest(
        DelayRequest $request,
        CallOptions $options = new CallOptions(),
    ): GetEmptyCall {
        $call = new GetEmptyCall();
        $call->args = $request;
        $call->method = '/test.TestService/GetDelayRequest';
        $call->options = $options;
        return $call;
    }

    public static function GetFailurePattern(
        FailurePatternRequest $request,
        CallOptions $options = new CallOptions(),
    ): GetEmptyCall {
        $call = new GetEmptyCall();
        $call->args = $request;
        $call->method = '/test.TestService/GetFailurePattern';
        $call->options = $options;
        return $call;
    }

    public static function GetMeta(PBEmpty $request, CallOptions $options = new CallOptions()): GetMetaCall
    {
        $response = new GetMetaCall();
        $response->args = $request;
        $response->method = '/test.TestService/GetMeta';
        $response->options = $options;
        return $response;
    }
}
