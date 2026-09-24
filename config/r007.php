<?php

/*
|--------------------------------------------------------------------------
| 007 Resort & Spa API client configuration
|--------------------------------------------------------------------------
|
| This application is a UI / backend-for-frontend over the 007 Resort & Spa
| API. The API is the single owner of business data and business rules.
| Nothing in this file may contain secrets: every value is read from the
| environment.
|
*/

return [

    // Logical name of this application, sent to the API for tracing.
    'service' => '007resort-admin-web',

    // Timezone used ONLY when rendering dates. The API stores and returns UTC
    // timestamps and the application itself runs in UTC.
    'display_timezone' => env('R007_DISPLAY_TIMEZONE', 'Africa/Lagos'),

    // Which node this admin instance fronts: "local" (on-site, LAN) or "cloud"
    // (remote). Same code, different config: cloud enforces MFA and always
    // treats site-originated figures as "as of last sync".
    'instance' => env('R007_INSTANCE', 'local'),

    // Mock API mode: serve fixture data in-process so the UI runs with no
    // backend (demo, design review, CI smoke). NEVER enable in production.
    'mock' => (bool) env('R007_MOCK', false),
    // normal | stale (site online but sync behind) | offline (site unreachable)
    'mock_scenario' => env('R007_MOCK_SCENARIO', 'normal'),

    // Seconds between re-reading /auth/me to refresh the permission set that
    // drives navigation (login always reads it).
    'me_ttl' => (int) env('R007_ME_TTL', 300),

    // MFA hook (docs/architecture 06 s4, 17 s1). Enforced on the cloud
    // instance for staff holding any of the listed sensitive permissions.
    'mfa' => [
        'enforce' => (bool) env('R007_MFA_ENFORCE', false),
        'permissions' => [
            'config.manage', 'device.register', 'staff.manage', 'refund.approve',
            'payment.reversal.approve', 'finance.report.view', 'report.view.all',
        ],
    ],

    'api' => [
        // Base URL of the 007 Resort & Spa API (on-site server or cloud
        // instance), e.g. http://r007-api.site.local:5080 or
        // https://api.example.com
        'base_url' => env('R007_API_BASE_URL', 'http://127.0.0.1:5080'),

        // Versioned path prefix; all calls go to {base_url}/api/v1/...
        'prefix' => env('R007_API_PREFIX', '/api/v1'),

        // Request timeout and connect timeout, in seconds.
        'timeout' => (int) env('R007_API_TIMEOUT', 10),
        'connect_timeout' => (int) env('R007_API_CONNECT_TIMEOUT', 3),

        // Public identifier of this client application as registered in the
        // API. This is NOT a secret. Client secrets (if ever required) must
        // come from the environment / secret store and never be committed.
        'client_id' => env('R007_API_CLIENT_ID', '007resort-admin-web'),

        // Session key under which the API-issued access token is stored
        // server-side. Tokens are never exposed to the browser.
        'session_token_key' => 'r007.api_token',
        'session_refresh_key' => 'r007.refresh_token',
    ],

];
