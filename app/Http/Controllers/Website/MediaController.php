<?php

namespace App\Http\Controllers\Website;

use App\Services\R007Api\R007ApiException;
use App\Support\Cms\Cms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Media library, and the JSON endpoints behind the shared image picker (list, upload, usage). */
class MediaController extends WebsiteController
{
    public function index(Request $request)
    {
        $query = ['limit' => 60, 'cursor' => $request->query('cursor')];
        foreach (['q', 'tag'] as $k) {
            if (is_string($request->query($k)) && $request->query($k) !== '') {
                $query[$k] = $request->query($k);
            }
        }
        $list = $this->cms->fetch('media', $query);
        $items = array_map([$this, 'norm'], Cms::items((array) $list->data));
        $tags = $this->allTags();

        return view('pages.website.media', [
            'list' => $list, 'items' => $items, 'next' => $list->next(), 'tags' => $tags,
            'canUpload' => $this->can(Cms::MEDIA), 'maxMb' => 8,
        ]);
    }

    /** JSON for the picker dialog. */
    public function picker(Request $request): JsonResponse
    {
        try {
            $query = ['limit' => 48, 'cursor' => $request->query('cursor')];
            foreach (['q', 'tag'] as $k) {
                if (is_string($request->query($k)) && $request->query($k) !== '') {
                    $query[$k] = $request->query($k);
                }
            }
            $body = $this->cms->get('media', $query);
            $items = array_map([$this, 'norm'], Cms::items($body));

            return response()->json(['items' => $items, 'nextCursor' => $body['nextCursor'] ?? null, 'tags' => $this->tagsOf($items)]);
        } catch (R007ApiException $e) {
            return $this->jsonError($e);
        }
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file'], 'alt' => ['nullable', 'string', 'max:200']]);
        try {
            $fields = array_filter(['alt' => $request->input('alt'), 'credit' => $request->input('credit')], fn ($v) => $v !== null && $v !== '');
            $res = $this->cms->upload($request->file('file'), $fields);

            return response()->json(['item' => $this->norm($res->body)], 201);
        } catch (R007ApiException $e) {
            return $this->jsonError($e);
        }
    }

    public function usage(string $id): JsonResponse
    {
        try {
            return response()->json($this->cms->get("media/{$id}/usage"));
        } catch (R007ApiException $e) {
            return $this->jsonError($e);
        }
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate(['alt' => ['nullable', 'string', 'max:200'], 'credit' => ['nullable', 'string', 'max:200'], 'sourceUrl' => ['nullable', 'url', 'max:500'], 'tags' => ['nullable', 'array', 'max:12'], 'tags.*' => ['string', 'max:32']]);
        $this->cms->patch("media/{$id}", [
            'alt' => (string) ($data['alt'] ?? ''), 'credit' => (string) ($data['credit'] ?? ''), 'sourceUrl' => ($data['sourceUrl'] ?? '') ?: null,
            'tags' => array_values(array_unique(array_map(fn ($t) => strtolower(trim($t)), $data['tags'] ?? []))),
        ]);

        return back()->with('success', 'Picture details saved.');
    }

    public function destroy(string $id)
    {
        try {
            $this->cms->delete("media/{$id}");
        } catch (R007ApiException $e) {
            if ($e->problemCode() === 'media_in_use') {
                $blockers = collect((array) ($e->extensions['usage'] ?? []))->map(fn ($u) => ucfirst(strtolower(Str::headline((string) ($u['type'] ?? '')))).': '.($u['label'] ?? ''))->filter()->values()->all();

                return back()->with('error', 'This picture is still used on the website, so it cannot be deleted. Replace it in these places first:')->with('blockers', $blockers)->with('error_code', 'media_in_use');
            }
            throw $e;
        }

        return back()->with('success', 'Picture deleted.');
    }

    // ------------------------------------------------------------------

    /**
     * One picture in the shape the browser scripts use, tolerant of missing fields.
     *
     * @param  array<string, mixed>  $m
     * @return array<string, mixed>
     */
    private function norm(array $m): array
    {
        return [
            'id' => (string) ($m['id'] ?? ''), 'url' => (string) ($m['url'] ?? ''), 'thumbUrl' => Cms::thumb($m, 480), 'alt' => (string) ($m['alt'] ?? ''), 'credit' => (string) ($m['credit'] ?? ''),
            'tags' => array_values((array) ($m['tags'] ?? [])), 'width' => (int) ($m['width'] ?? 0), 'height' => (int) ($m['height'] ?? 0), 'sizeBytes' => (int) ($m['sizeBytes'] ?? 0),
            'originalName' => (string) ($m['originalName'] ?? ''), 'usageCount' => (int) ($m['usageCount'] ?? 0), 'sourceUrl' => (string) ($m['sourceUrl'] ?? ''), 'createdAt' => (string) ($m['createdAt'] ?? ''),
        ];
    }

    /** @param  list<array<string, mixed>>  $items  @return list<string> */
    private function tagsOf(array $items): array
    {
        return collect($items)->flatMap(fn ($i) => $i['tags'])->unique()->sort()->values()->all();
    }

    /** Every tag in use (from the latest 200 pictures). @return list<string> */
    private function allTags(): array
    {
        $f = $this->cms->fetch('media', ['limit' => 200]);

        return $this->tagsOf(array_map([$this, 'norm'], Cms::items((array) $f->data)));
    }

    private function jsonError(R007ApiException $e): JsonResponse
    {
        if ($e->isUnauthenticated()) {
            return response()->json(['message' => 'Your session has ended. Please sign in again.'], 401);
        }

        return response()->json(['message' => $e->detail ?: $e->title, 'code' => $e->problemCode(), 'errors' => $e->errors()], $e->status >= 400 ? $e->status : 502);
    }
}
