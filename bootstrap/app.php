<?php

use App\Auth\StaffSession;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireStaff;
use App\Services\R007Api\R007ApiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff' => RequireStaff::class,
            'permit' => RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // One place turns API failures into UI: expired session -> login,
        // rejected form -> back with the API's message, otherwise an error page.
        $exceptions->render(function (R007ApiException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            if ($e->isUnauthenticated() && $request->routeIs('login') === false) {
                app(StaffSession::class)->clear();

                return redirect()->route('login')->with('status', 'Your session has ended. Please sign in again.');
            }

            // The API's detail is written for administrators ("3 open orders must be settled first"): show it as is, title only as a fallback.
            $message = $e->detail ?: $e->title;
            $blockers = collect((array) ($e->extensions['blockers'] ?? []))->map(fn ($b) => is_array($b) ? (string) ($b['message'] ?? '') : (string) $b)->filter()->values()->all();

            if (! $request->isMethod('GET') && ! $e->isUnreachable() && $e->status < 500) {
                $back = back()->withInput($request->except(['password', 'secret', 'pin', 'code']))->with('error', $message)->with('blockers', $blockers)->with('error_code', $e->problemCode());

                return $e->errors() !== [] ? $back->withErrors($e->errors()) : $back;
            }

            return response()->view('errors.api', ['e' => $e, 'message' => $message], $e->isUnreachable() ? 503 : ($e->status >= 400 ? $e->status : 502));
        });
    })->create();
