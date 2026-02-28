<?php

use Mrldavies\WpRestFluent\Rest;

function formatRoute(string $endpoint): string
{
    $rest = new Rest();

    $rm = new ReflectionMethod(Rest::class, 'formatRouteArgs');
    $rm->setAccessible(true);

    return $rm->invoke($rest, $endpoint);
}

it('converts int param', function () {
    expect(formatRoute('/product/{id:int}'))
        ->toBe('/product/(?P<id>[0-9]+)');
});

it('converts alpha param', function () {
    expect(formatRoute('/user/{name:alpha}'))
        ->toBe('/user/(?P<name>[a-zA-Z]+)');
});

it('converts default param', function () {
    expect(formatRoute('/invoice/{ref}'))
        ->toBe('/invoice/(?P<ref>[a-zA-Z0-9-+_]+)');
});

it('converts optional param', function () {
    expect(formatRoute('/category/{slug?}'))
        ->toBe('/category(?:/(?P<slug>[a-zA-Z0-9-+_]+))?');
});

it('converts multiple params without double slashes', function () {
    $out = formatRoute('/demo/plain/{name:alpha}/age/{age:int}');

    expect($out)->toBe('/demo/plain/(?P<name>[a-zA-Z]+)/age/(?P<age>[0-9]+)');
    expect($out)->not->toContain('//');
});

it('leaves raw wordpress regex routes untouched', function () {
    $raw = '/legacy/age(?:/(?P<id>[0-9]+))';

    expect(formatRoute($raw))->toBe($raw);
});
