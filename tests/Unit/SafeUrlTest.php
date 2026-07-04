<?php

use App\Support\SafeUrl;

it('collapses equivalent URLs to one canonical form', function (string $input, string $expected) {
    expect(SafeUrl::normalize($input))->toBe($expected);
})->with([
    'root without slash' => ['https://abrar.pro', 'https://abrar.pro/'],
    'root with slash' => ['https://abrar.pro/', 'https://abrar.pro/'],
    'uppercase host' => ['https://ABRAR.pro/Articles', 'https://abrar.pro/Articles'],
    'trailing slash on path' => ['https://abrar.pro/articles/', 'https://abrar.pro/articles'],
    'fragment stripped' => ['https://abrar.pro/x#section', 'https://abrar.pro/x'],
    'default port removed' => ['https://abrar.pro:443/x', 'https://abrar.pro/x'],
]);

it('treats a non-http-literal safe target correctly', function () {
    expect(SafeUrl::hasSafeTarget('https://abrar.pro/x'))->toBeTrue()
        ->and(SafeUrl::hasSafeTarget('http://127.0.0.1/x'))->toBeFalse();
});
