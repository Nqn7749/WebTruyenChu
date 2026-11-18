<?php

use Illuminate\\Support\\Facades\\Route;

test('sensitive authentication endpoints have explicit rate limits', function () {
    $limits = [
        'register' => 'throttle:5,1',
        'password.email' => 'throttle:5,1',
        'password.store' => 'throttle:10,1',
        'password.confirm' => 'throttle:6,1',
        'password.update' => 'throttle:6,1',
    ];

    foreach ($limits as $routeName => $expectedMiddleware) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain($expectedMiddleware);
    }
});

test('login keeps its dedicated credential throttle implementation', function () {
    $request = app(\\App\\Http\\Requests\\Auth\\LoginRequest::class);

    expect(method_exists($request, 'ensureIsNotRateLimited'))->toBeTrue()
        ->and(method_exists($request, 'throttleKey'))->toBeTrue();
});
