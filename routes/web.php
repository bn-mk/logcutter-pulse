<?php

use Illuminate\Support\Facades\Route;
use Logcutter\LogPulse\Http\Controllers\Debug\LogBugController;
use Logcutter\LogPulse\Http\Controllers\LogPulseController;

if (! (bool) config('logpulse.routes.enabled', true)) {
    return;
}

$middleware = config('logpulse.routes.middleware', ['web', 'auth', 'verified']);
if (is_string($middleware)) {
    $middleware = [$middleware];
}

if (! is_array($middleware)) {
    $middleware = ['web', 'auth', 'verified'];
}

$prefix = (string) config('logpulse.routes.prefix', 'logpulse');
$namePrefix = (string) config('logpulse.routes.name_prefix', 'logpulse.');

Route::middleware($middleware)
    ->prefix($prefix)
    ->name($namePrefix)
    ->controller(LogPulseController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/issues', 'issues')->name('issues');
    });

if ((bool) config('logpulse.debug_routes.enabled', false) && app()->environment(['local', 'testing'])) {
    $debugPrefix = (string) config('logpulse.debug_routes.prefix', '_debug/log-bugs');
    $debugNamePrefix = (string) config('logpulse.debug_routes.name_prefix', 'debug.log-bugs.');

    Route::middleware($middleware)
        ->prefix($debugPrefix)
        ->name($debugNamePrefix)
        ->controller(LogBugController::class)
        ->group(function (): void {
            Route::get('/null-property-crash', 'nullPropertyCrash')->name('null-property-crash');
            Route::get('/explicit-exception', 'explicitException')->name('explicit-exception');
        });
}
