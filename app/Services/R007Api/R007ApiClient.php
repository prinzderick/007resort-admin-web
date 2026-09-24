<?php

namespace App\Services\R007Api;

use App\Services\R007Api\Mock\MockBackend;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin HTTP client for the 007 Resort & Spa API (/api/v1/...).
 *
 * All business operations (payments, refunds, inventory, tickets, bookings,
 * memberships, order state, ...) MUST go through this client. This app never
 * writes business data to a database of its own.
 *
 * - JSON in / JSON out.
 * - Bearer token is read from the server-side session (never the browser).
 *   An expired access token is refreshed once with the session's refresh token.
 * - Every mutating request carries an Idempotency-Key so the API can safely
 *   de-duplicate retries. Pass an explicit key when retrying the same logical
 *   operation (e.g. a form re-submitted after a timeout).
 * - Error responses (RFC 7807 problem details) become R007ApiException.
 * - Monetary amounts arrive as decimal strings: never cast them to float.
 * - Mock mode (R007_MOCK=true) answers from an in-process fixture backend so
 *   the UI runs without the real API.
 *
 * NOTE: duplicated in 007resort-admin-web and 007resort-booking-web during
 * Phase 0. To be extracted into a shared private Composer package once
 * stable.
 */
class R007ApiClient
{
    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    /**
     * @param  array<string, mixed>  $config  The "r007.api" config array (plus `mock`).
     */
    public function __construct(
        private readonly array $config,
        private readonly ?Session $session = null,
    ) {}

    public function isMock(): bool
    {
        return (bool) ($this->config['mock'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query)->body;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public function post(string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->request('POST', $path, [], $data, [], $idempotencyKey)->body;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public function put(string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->request('PUT', $path, [], $data, [], $idempotencyKey)->body;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public function patch(string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->request('PATCH', $path, [], $data, [], $idempotencyKey)->body;
    }

    /**
     * @return array<mixed>
     */
    public function delete(string $path, ?string $idempotencyKey = null): array
    {
        return $this->request('DELETE', $path, [], [], [], $idempotencyKey)->body;
    }

    /**
     * Non-JSON exchange (CSV export / import): returns the raw response body and its headers. The body, when given, is sent as-is with
     * the given content type. Failures are RFC 7807 problems like everywhere else.
     *
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function raw(string $method, string $path, array $query = [], ?string $body = null, string $contentType = 'text/csv', array $headers = []): array
    {
        $method = strtoupper($method);
        if ($this->isMock()) {
            throw new R007ApiException(501, 'Not available in mock mode');
        }
        $request = $this->pendingRequest()->withHeaders($headers)->accept('text/csv, application/json, application/problem+json');
        if ($method !== 'GET') {
            $request->withHeaders([self::IDEMPOTENCY_HEADER => (string) Str::uuid()]);
        }
        if ($body !== null) {
            $request->withBody($body, $contentType);
        }
        try {
            $response = $request->send($method, ltrim($path, '/'), ['query' => array_filter($query, fn ($v) => $v !== null && $v !== '')]);
        } catch (ConnectionException $e) {
            throw R007ApiException::unreachable($e);
        }
        if ($response->failed()) {
            throw R007ApiException::fromResponse($response);
        }
        $flat = [];
        foreach ($response->headers() as $name => $values) {
            $flat[strtolower((string) $name)] = (string) ($values[0] ?? '');
        }

        return ['status' => $response->status(), 'body' => $response->body(), 'headers' => $flat];
    }

    /**
     * Multipart file upload (CMS media). `$fields` are plain form fields (a list value posts as `name[]`). Mock mode hands the file's
     * metadata to the fixture backend instead. Failures are RFC 7807 problems like everywhere else.
     *
     * @param  array<string, string|int|bool|list<string>>  $fields
     */
    public function upload(string $path, string $filePath, string $fileName, string $mime, array $fields = [], ?string $idempotencyKey = null): ApiResponse
    {
        $key = $idempotencyKey ?? (string) Str::uuid();
        if ($this->isMock()) {
            return $this->mock()->handle('POST', '/'.ltrim($path, '/'), [], $fields + ['_file' => ['path' => $filePath, 'name' => $fileName, 'mime' => $mime, 'size' => (int) @filesize($filePath)]], $this->token());
        }
        $request = $this->pendingRequest()->withHeaders([self::IDEMPOTENCY_HEADER => $key])->asMultipart();
        $request->attach('file', (string) file_get_contents($filePath), $fileName, ['Content-Type' => $mime]);
        foreach ($fields as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $v) {
                $request->attach(is_array($value) ? $name.'[]' : $name, is_bool($v) ? ($v ? '1' : '0') : (string) $v);
            }
        }
        try {
            $response = $request->post(ltrim($path, '/'));
        } catch (ConnectionException $e) {
            throw R007ApiException::unreachable($e);
        }
        if ($response->failed()) {
            throw R007ApiException::fromResponse($response);
        }
        $flat = [];
        foreach ($response->headers() as $name => $values) {
            $flat[strtolower((string) $name)] = (string) ($values[0] ?? '');
        }

        return new ApiResponse($response->status(), (array) $response->json(), $flat);
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->config['base_url'], '/').'/'.trim((string) ($this->config['prefix'] ?? '/api/v1'), '/');
    }

    /**
     * Full-fidelity call: returns status, body and headers (ETag, 202 detection).
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers  extra headers (If-Match, X-Step-Up-Token, ...)
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        array $headers = [],
        ?string $idempotencyKey = null,
        bool $allowRefresh = true,
    ): ApiResponse {
        $method = strtoupper($method);
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');

        if ($method !== 'GET' && $idempotencyKey === null) {
            $idempotencyKey = (string) Str::uuid();
        }

        try {
            $response = $this->dispatch($method, $path, $query, $body, $headers, $idempotencyKey);
        } catch (R007ApiException $e) {
            if ($e->status === 401 && $allowRefresh && ! str_starts_with(ltrim($path, '/'), 'auth/') && $this->refreshTokens()) {
                return $this->request($method, $path, $query, $body, $headers, $idempotencyKey, false);
            }

            throw $e;
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    protected function dispatch(string $method, string $path, array $query, array $body, array $headers, ?string $idempotencyKey): ApiResponse
    {
        if ($this->isMock()) {
            return $this->mock()->handle($method, '/'.ltrim($path, '/'), $query, $body, $this->token());
        }

        $request = $this->pendingRequest()->withHeaders($headers);

        if ($idempotencyKey !== null && $method !== 'GET') {
            $request->withHeaders([self::IDEMPOTENCY_HEADER => $idempotencyKey]);
        }

        $options = $method === 'GET' ? ['query' => $query] : ['json' => $body, 'query' => $query];

        try {
            $response = $request->send($method, ltrim($path, '/'), $options);
        } catch (ConnectionException $e) {
            throw R007ApiException::unreachable($e);
        }

        if ($response->failed()) {
            throw R007ApiException::fromResponse($response);
        }

        $json = $response->json();
        if ($json === null && ! $this->isMock() && preg_match('/^\s*<br\s*\/?>\s*<b>(Notice|Warning|Deprecated)<\/b>/i', $response->body()) && ($at = strpos($response->body(), "\n{")) !== false) {
            // PHP's development server prints a notice into the body when its log pipe is closed. Read the JSON that follows it rather than failing the screen.
            $json = json_decode(substr($response->body(), $at + 1), true);
        }
        $flat = [];
        foreach ($response->headers() as $name => $values) {
            $flat[strtolower((string) $name)] = (string) ($values[0] ?? '');
        }

        return new ApiResponse($response->status(), is_array($json) ? $json : [], $flat);
    }

    protected function refreshTokens(): bool
    {
        $refresh = $this->session?->get((string) ($this->config['session_refresh_key'] ?? 'r007.refresh_token'));

        if (! is_string($refresh) || $refresh === '') {
            return false;
        }

        try {
            $result = $this->dispatchRefresh($refresh);
        } catch (R007ApiException) {
            return false;
        }

        $this->session->put((string) ($this->config['session_token_key'] ?? 'r007.api_token'), $result['accessToken'] ?? null);
        $this->session->put((string) ($this->config['session_refresh_key'] ?? 'r007.refresh_token'), $result['refreshToken'] ?? $refresh);

        return isset($result['accessToken']);
    }

    /**
     * @return array<mixed>
     */
    private function dispatchRefresh(string $refresh): array
    {
        if ($this->isMock()) {
            return $this->mock()->handle('POST', '/auth/staff/refresh', [], ['refreshToken' => $refresh], null)->body;
        }

        try {
            $response = $this->pendingRequest(withToken: false)
                ->withHeaders([self::IDEMPOTENCY_HEADER => (string) Str::uuid()])
                ->post('auth/staff/refresh', ['refreshToken' => $refresh]);
        } catch (ConnectionException $e) {
            throw R007ApiException::unreachable($e);
        }

        if ($response->failed()) {
            throw R007ApiException::fromResponse($response);
        }

        return (array) $response->json();
    }

    protected function mock(): MockBackend
    {
        return app(MockBackend::class);
    }

    protected function pendingRequest(bool $withToken = true): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Accept' => 'application/json, application/problem+json',
                'X-R007-Client' => (string) ($this->config['client_id'] ?? ''),
                'X-Request-Id' => (string) Str::uuid(),
                'X-Correlation-Id' => (string) Str::uuid(),
            ])
            ->timeout((int) ($this->config['timeout'] ?? 10))
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 3));

        $token = $withToken ? $this->token() : null;

        if ($token !== null && $token !== '') {
            $request->withToken($token);
        }

        return $request;
    }

    protected function token(): ?string
    {
        $key = (string) ($this->config['session_token_key'] ?? 'r007.api_token');
        $token = $this->session?->get($key);

        return is_string($token) ? $token : null;
    }
}
