<?php

namespace App\Http\Controllers;

use App\Auth\StaffSession;
use App\Services\R007Api\ApiResponse;
use App\Services\R007Api\R007ApiClient;
use App\Support\Fetch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    public function __construct(protected R007ApiClient $api, protected StaffSession $staff) {}

    /**
     * Redirect after a write, making an accepted-but-not-yet-approved outcome
     * (HTTP 202 / PENDING_APPROVAL) impossible to mistake for "done".
     */
    protected function done(ApiResponse $res, string $route, string $successMessage, string $pendingMessage, array $params = []): RedirectResponse
    {
        $redirect = redirect()->route($route, $params);

        if ($res->isPendingApproval()) {
            $ref = $res->approvalId();

            return $redirect->with('pending', $pendingMessage.($ref ? " Reference {$ref}." : ''))
                ->with('pending_link', $this->staff->canApproveAnything() ? route('approvals') : null);
        }

        return $redirect->with('success', $successMessage);
    }

    /**
     * Read every page of a cursor-paginated list (the API caps a page at 200 rows), up to $pages pages.
     *
     * @param  array<string, mixed>  $query
     * @param  array{0: string, 1: string}|null  $endpoint  [method, path template] checked against the contract
     */
    protected function all(string $path, array $query = [], ?array $endpoint = null, int $pages = 5): Fetch
    {
        return Fetch::of(function () use ($path, $query, $pages) {
            $items = [];
            $cursor = null;
            $body = [];
            for ($i = 0; $i < $pages; $i++) {
                $body = $this->api->get($path, $query + ['limit' => 200, 'cursor' => $cursor]);
                array_push($items, ...array_values((array) ($body['items'] ?? [])));
                $cursor = $body['nextCursor'] ?? null;
                if (! is_string($cursor) || $cursor === '') {
                    $cursor = null;
                    break;
                }
            }

            return ['items' => $items, 'nextCursor' => $cursor];
        }, $endpoint ?? ['GET', '/'.ltrim($path, '/')]);
    }

    /** Rows per page chosen in the table toolbar (?per=), within what the API allows. */
    protected function perPage(Request $request, int $default = 100): int
    {
        $n = (int) $request->query('per', $default);

        return in_array($n, [25, 50, 100, 200], true) ? $n : $default;
    }

    /** @param  array<string, mixed>  $data */
    protected function only(array $data, array $keys): array
    {
        return array_filter(array_intersect_key($data, array_flip($keys)), fn ($v) => $v !== null && $v !== '');
    }
}
