<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('sensitive authentication endpoints have explicit rate limits', function () {
    $limits = [
        'register.store' => 'throttle:5,1',
        'password.email' => 'throttle:10,1',
        'password.store' => 'throttle:10,1',
        'password.confirm.store' => 'throttle:10,1',
        'password.update' => 'throttle:10,1',
    ];

    foreach ($limits as $routeName => $expectedMiddleware) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain($expectedMiddleware);
    }
});

test('registration endpoint enforces its five request per minute limit', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('register.store'), [])->assertUnprocessable();
    }

    $this->postJson(route('register.store'), [])->assertTooManyRequests();
});

test('password reset link endpoint enforces its ten request per minute limit', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('password.email'), [])->assertUnprocessable();
    }

    $this->postJson(route('password.email'), [])->assertTooManyRequests();
});

test('password reset submission endpoint enforces its ten request per minute limit', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('password.store'), [])->assertUnprocessable();
    }

    $this->postJson(route('password.store'), [])->assertTooManyRequests();
});

test('password confirmation endpoint enforces its ten request per minute limit', function () {
    $this->actingAs(User::factory()->create());

    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('password.confirm.store'), [
            'password' => 'incorrect-password',
        ])->assertUnprocessable();
    }

    $this->postJson(route('password.confirm.store'), [
        'password' => 'incorrect-password',
    ])->assertTooManyRequests();
});

test('password update endpoint enforces its ten request per minute limit', function () {
    $this->actingAs(User::factory()->create());

    foreach (range(1, 10) as $attempt) {
        $this->putJson(route('password.update'), [])->assertUnprocessable();
    }

    $this->putJson(route('password.update'), [])->assertTooManyRequests();
});
