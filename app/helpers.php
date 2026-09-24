<?php

use App\Auth\StaffSession;

if (! function_exists('auth_staff')) {
    /** The signed-in staff member (API session) for views. */
    function auth_staff(): StaffSession
    {
        return app(StaffSession::class);
    }
}
