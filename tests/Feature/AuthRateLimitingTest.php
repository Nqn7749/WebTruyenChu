<?php

use Illuminate\Support\Facades\Route;

test('sensitive authentication endpoints have explicit rate limits', function () {
    $limits = [
        'register.store' => 'throttle:5,1',
        'password.email' => 'throttle:5,1',
        'password.store' => 'throttle:10,1',
        'password.confirm.store' => 'throttle:6,1',
        'password.update' => 'throttle:6,1',
    ];

    foreach ($limits as $routeName => $expectedMiddleware) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain($expectedMiddleware);
    }
});
