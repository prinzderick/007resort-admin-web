<?php

namespace App\Http\Middleware;

use App\Auth\StaffSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `permit:a,b` passes when the staff member holds ANY listed permission. */
class RequirePermission
{
    public function __construct(private readonly StaffSession $staff) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if ($permissions !== [] && ! $this->staff->canAny(...$permissions)) {
            abort(403, 'Your account does not have access to this area.');
        }

        return $next($request);
    }
}
