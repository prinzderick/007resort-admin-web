<?php

namespace App\Services\Cms;

use App\Services\R007Api\ApiResponse;
use App\Services\R007Api\R007ApiClient;
use App\Support\Fetch;
use Illuminate\Http\UploadedFile;

/**
 * The Website CMS admin API (docs/CMS_API.md section 4, `/api/v1/admin/cms/*`) in one place.
 *
 * Editing rules the API enforces and this class carries for callers: creating POSTs carry an Idempotency-Key (the client adds one),
 * edits of settings/pages/posts/events send `If-Match: "<rowVersion>"` (428 when missing, 412 concurrency_conflict when stale),
 * media upload is multipart. No business rules live here; all validation is the API's and its 422 `errors` reach the form fields.
 */
class CmsApi
{
    public const BASE = 'admin/cms';

    public function __construct(private readonly R007ApiClient $api) {}

    /** @return array<string, mixed> */
    public function get(string $path, array $query = []): array
    {
        return $this->api->get(self::BASE.'/'.ltrim($path, '/'), $query);
    }

    /** A read a screen can degrade around (unreachable / forbidden / not built). */
    public function fetch(string $path, array $query = []): Fetch
    {
        return Fetch::of(fn () => $this->get($path, $query));
    }

    /** @return array<string, mixed> */
    public function post(string $path, array $data = []): array
    {
        return $this->api->post(self::BASE.'/'.ltrim($path, '/'), $data);
    }

    /**
     * Edit with optimistic concurrency. $rowVersion null sends no If-Match (allowed for entities that accept it optionally).
     *
     * @return array<string, mixed>
     */
    public function patch(string $path, array $data, int|string|null $rowVersion = null): array
    {
        return $this->api->request('PATCH', self::BASE.'/'.ltrim($path, '/'), [], $data, $this->ifMatch($rowVersion))->body;
    }

    /** @return array<string, mixed> */
    public function put(string $path, array $data, int|string|null $rowVersion = null): array
    {
        return $this->api->request('PUT', self::BASE.'/'.ltrim($path, '/'), [], $data, $this->ifMatch($rowVersion))->body;
    }

    /** @return array<string, mixed> */
    public function delete(string $path): array
    {
        return $this->api->delete(self::BASE.'/'.ltrim($path, '/'));
    }

    public function upload(UploadedFile $file, array $fields = []): ApiResponse
    {
        return $this->api->upload(self::BASE.'/media', $file->getRealPath(), $file->getClientOriginalName(), (string) ($file->getMimeType() ?: 'application/octet-stream'), $fields);
    }

    /**
     * Raw text/csv body of an export.
     *
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function csv(string $path, array $query = []): array
    {
        return $this->api->raw('GET', self::BASE.'/'.ltrim($path, '/'), $query);
    }

    /**
     * Read every page of a cursor list (200 per page), up to $pages pages.
     *
     * @return array{items: list<array<string, mixed>>, nextCursor: ?string, counts: array<string, mixed>, media: array<string, mixed>}
     */
    public function all(string $path, array $query = [], int $pages = 5): array
    {
        $items = [];
        $cursor = null;
        $counts = [];
        $media = [];
        for ($i = 0; $i < $pages; $i++) {
            $body = $this->get($path, $query + ['limit' => 200, 'cursor' => $cursor]);
            array_push($items, ...array_values(array_filter((array) ($body['items'] ?? []), 'is_array')));
            $counts = (array) ($body['counts'] ?? $counts);
            $media = (array) ($body['media'] ?? $media) + $media;
            $cursor = $body['nextCursor'] ?? null;
            if (! is_string($cursor) || $cursor === '') {
                $cursor = null;
                break;
            }
        }

        return ['items' => $items, 'nextCursor' => $cursor, 'counts' => $counts, 'media' => $media];
    }

    /** @return array<string, string> */
    private function ifMatch(int|string|null $rowVersion): array
    {
        return $rowVersion === null || $rowVersion === '' ? [] : ['If-Match' => '"'.trim((string) $rowVersion, '"').'"'];
    }
}
