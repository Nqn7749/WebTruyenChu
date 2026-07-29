<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'role'])
    ->prefix('admin')
    ->as('admin.')
    ->group(base_path('routes/admin.php'));