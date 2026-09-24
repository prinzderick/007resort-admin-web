<?php

namespace Tests;

use App\Auth\StaffSession;
use App\Support\Contract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    public const API = 'https://api.r007.test/api/v1';

    protected function setUp(): void
    {
        parent::setUp();
        Contract::flush();
    }

    /**
     * Put a signed-in staff member (as /auth/me would report them) into the
     * server-side session, so a feature test can hit a screen directly.
     *
     * @param  list<string>  $permissions
     */
    protected function signIn(array $permissions = [], array $roles = ['tester'], string $name = 'Test Staff'): static
    {
        return $this->withSession([
            StaffSession::K_TOKEN => 'test-access-token',
            StaffSession::K_REFRESH => 'test-refresh-token',
            StaffSession::K_STAFF => ['id' => '0192f6a0-7b1c-7d2e-9a3b-000000000201', 'displayName' => $name, 'roles' => $roles, 'permissions' => $permissions],
            StaffSession::K_SESSION => ['id' => '0192f6a0-7b1c-7d2e-9a3b-000000009999'],
            StaffSession::K_ME_AT => time(),
        ]);
    }

    /**
     * Fake the API. Keys are "METHOD /path" (fnmatch wildcards allowed, matched
     * on the path below /api/v1, query ignored, first match wins); values are a
     * body array, or [status, body, headers]. Anything unmapped answers like a
     * route the API has not built (404, no problem code) so screens degrade.
     *
     * @param  array<string, mixed>  $routes
     */
    protected function fakeApi(array $routes): void
    {
        Http::swap(new Factory);   // drop earlier fakes: the first matching stub wins
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($routes) {
            $path = '/'.ltrim((string) preg_replace('#^/api/v1#', '', parse_url($request->url(), PHP_URL_PATH)), '/');
            $key = $request->method().' '.$path;
            foreach ($routes as $pattern => $response) {
                if (fnmatch($pattern, $key)) {
                    if ($response instanceof \Closure) {
                        $response = $response($request);
                    }
                    [$status, $body, $headers] = array_is_list($response) && is_int($response[0] ?? null) ? [$response[0], $response[1] ?? [], $response[2] ?? []] : [200, $response, []];

                    return Http::response($body, $status, $headers + ($status >= 400 ? ['Content-Type' => 'application/problem+json'] : []));
                }
            }

            return Http::response(['message' => 'Not Found'], 404);
        });
    }

    /** RFC 7807 problem body with a stable code. */
    protected function problem(int $status, string $code, string $detail = 'Detail'): array
    {
        return [$status, ['type' => 'about:blank', 'title' => ucfirst(str_replace('_', ' ', $code)), 'status' => $status, 'detail' => $detail, 'code' => $code]];
    }

    protected function url(string $path): string
    {
        return self::API.'/'.ltrim($path, '/');
    }

    /** The last recorded API request to a method + path (query ignored), or null. */
    protected function lastSent(string $method, string $path): ?Request
    {
        $found = null;
        foreach (Http::recorded() as [$request]) {
            if ($request->method() === strtoupper($method) && rtrim(parse_url($request->url(), PHP_URL_PATH), '/') === '/api/v1/'.trim($path, '/')) {
                $found = $request;
            }
        }

        return $found;
    }

    /** True when a recorded outgoing API request matches. */
    protected function sentTo(string $method, string $path, ?callable $extra = null): bool
    {
        foreach (Http::recorded() as [$request]) {
            if ($request->method() === strtoupper($method) && str_starts_with($request->url(), $this->url($path)) && ($extra === null || $extra($request))) {
                return true;
            }
        }

        return false;
    }
}
