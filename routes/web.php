<?php

use Illuminate\Support\Facades\Route;

/*
| Liveness probe for load balancers / the on-site service monitor.
| (Laravel's built-in /up endpoint is also available.)
*/
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => '007resort-admin-web',
]))->name('health');

/*
| Phase 0 placeholder. Staff sign-in (via the 007 Resort & Spa API) and API-issued
| permission checks will guard this route in a later phase.
*/
Route::view('/', 'dashboard')->name('dashboard');
