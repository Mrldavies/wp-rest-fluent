<?php

use Mrldavies\WpRestFluent\Rest;

function makeRoute(array $overrides = []): Rest
{
    $route = new Rest();

    $defaults = array_merge([
        'status' => 200,
        'headers' => [],
        'mapDataKey' => 'data',
        'mapStatusKey' => 'status',
        'mapHeadersKey' => 'headers',
    ], $overrides);

    foreach ($defaults as $prop => $value) {
        $rp = new ReflectionProperty(Rest::class, $prop);
        $rp->setAccessible(true);
        $rp->setValue($route, $value);
    }

    return $route;
}

function normalize($result, Rest $route): array
{
    $rm = new ReflectionMethod(Rest::class, 'normalizeHandlerResult');
    $rm->setAccessible(true);

    return $rm->invoke(null, $result, $route);
}

it('normalizes array with data status and headers', function () {
    $route = makeRoute([
        'status' => 202,
        'headers' => ['X-Base' => 'base'],
    ]);

    $result = [
        'data' => ['ok' => true],
        'status' => 201,
        'headers' => ['X-Test' => '1'],
    ];

    $out = normalize($result, $route);

    expect($out['body'])->toBe(['ok' => true]);
    expect($out['status'])->toBe(201);
    expect($out['headers'])->toBe([
        'X-Base' => 'base',
        'X-Test' => '1',
    ]);
});

it('normalizes object with data status and headers', function () {
    $route = makeRoute();

    $result = (object) [
        'data' => (object) ['id' => 123],
        'status' => 404,
        'headers' => ['X-Test' => '1'],
    ];

    $out = normalize($result, $route);

    expect($out['body'])->toEqual((object) ['id' => 123]);
    expect($out['status'])->toBe(404);
});

it('falls back to route default status when missing', function () {
    $route = makeRoute(['status' => 202]);

    $result = ['data' => ['ok' => true]];

    $out = normalize($result, $route);

    expect($out['status'])->toBe(202);
});

it('supports custom mapping keys', function () {
    $route = makeRoute([
        'mapDataKey' => 'payload',
        'mapStatusKey' => 'code',
        'mapHeadersKey' => 'hdrs',
    ]);

    $result = [
        'payload' => ['msg' => 'teapot'],
        'code' => 418,
        'hdrs' => ['X-Test' => '1'],
    ];

    $out = normalize($result, $route);

    expect($out['body'])->toBe(['msg' => 'teapot']);
    expect($out['status'])->toBe(418);
});

it('supports body and response fallback keys', function () {
    $route = makeRoute();

    $out1 = normalize(['body' => ['a' => 1], 'status' => 200], $route);
    $out2 = normalize(['response' => ['b' => 2], 'status' => 200], $route);

    expect($out1['body'])->toBe(['a' => 1]);
    expect($out2['body'])->toBe(['b' => 2]);
});

it('supports scalar result', function () {
    $route = makeRoute(['status' => 200]);

    $out = normalize('hello', $route);

    expect($out['body'])->toBe('hello');
    expect($out['status'])->toBe(200);
});
