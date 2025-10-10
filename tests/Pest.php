<?php

/*
 |--------------------------------------------------------------------------
 | Test Case
 |--------------------------------------------------------------------------
 |
 | The closure you provide to your test functions is always bound to a specific PHPUnit test
 | case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
 | need to change it using the "pest()" function to bind a different classes or traits.
 |
 */

pest()->extend(Tests\TestCase::class)->in('Feature');

/*
 |--------------------------------------------------------------------------
 | Expectations
 |--------------------------------------------------------------------------
 |
 | When you're writing tests, you often need to check that values meet certain conditions. The
 | "expect()" function gives you access to a set of "expectations" methods that you can use
 | to assert different things. Of course, you may extend the Expectation API at any time.
 |
 */

// expect()->extend('toBeOne', function () {
//     return $this->toBe(1);
// });

/*
 |--------------------------------------------------------------------------
 | Functions
 |--------------------------------------------------------------------------
 |
 | While Pest is very powerful out-of-the-box, you may have some testing code specific to your
 | project that you don't want to repeat in every file. Here you can also expose helpers as
 | global functions to help you to reduce the number of lines of code in your test files.
 |
 */

function serializeMsg(\Google\Protobuf\Internal\Message $msg): string
{
    return json_encode(
        json_decode($msg->serializeToJsonString(\Google\Protobuf\PrintOptions::PRESERVE_PROTO_FIELD_NAMES)),
        JSON_PRETTY_PRINT,
    );
}

function testConn(): \Iris\Connection
{
    $port = getenv('TEST_SERVER_PORT');
    return new \Iris\Connection("[::1]:{$port}");
}

function delta(string $message): void
{
    static $lastTime = hrtime(true) / 1e9;
    $time = hrtime(true) / 1e9;
    $delta = $time - $lastTime;
    $lastTime = $time;
    dump(sprintf('%8.2fms: %s', $delta * 1000, $message));
}
