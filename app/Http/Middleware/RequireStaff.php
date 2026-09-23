<?php

namespace App\Http\Middleware;

use App\Auth\AuthService;
use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gate: signed in via the API, MFA done, permission set reasonably fresh. */
class RequireStaff
{
    public function __construct(private readonly StaffSession $staff, private readonly AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->staff->check()) {
            return $this->toLogin($request);
        }

        if ($this->staff->mfaPending()) {
            return redirect()->route('mfa');
        }

        if ($this->staff->meStale()) {
            try {
                $this->auth->refreshMe();
            } catch (R007ApiException $e) {
                if ($e->isUnauthenticated()) {
                    $this->staff->clear();

                    return $this->toLogin($request, 'Your session has ended. Please sign in again.');
                }
                // API unreachable: keep working with the last known permissions.
            }
        }

        return $next($request);
    }

    private function toLogin(Request $request, ?string $message = null): Response
    {
        if ($request->expectsJson()) {
            abort(401);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('login')->with('status', $message);
    }
}
