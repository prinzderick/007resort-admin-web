<?php

namespace App\Http\Controllers\Website;

use App\Services\R007Api\R007ApiException;
use App\Support\Cms\Cms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gallery: albums, and inside an album a grid of photos you can upload into, reorder, caption and flag.
 * One "Save gallery" writes the album details, every changed photo (`PATCH /gallery/items/{id}`) and the order (`items/reorder`).
 */
class GalleryController extends WebsiteController
{
    public function index(Request $request)
    {
        $query = ['limit' => $this->perPage($request, 50), 'cursor' => $request->query('cursor')];
        foreach (['q', 'status'] as $k) {
            if (is_string($request->query($k)) && $request->query($k) !== '') {
                $query[$k] = $request->query($k);
            }
        }
        $list = $this->cms->fetch('gallery/albums', $query);
        $summary = $this->cms->fetch('summary');
        $row = $summary->ok() ? (array) ($summary->data['albums'] ?? []) : [];

        return view('pages.website.gallery.index', [
            'list' => $list, 'items' => Cms::items((array) $list->data), 'next' => $list->next(), 'chips' => ContentController::STATUS_CHIPS,
            'counts' => array_filter(['DRAFT' => $row['draft'] ?? null, 'PUBLISHED' => $row['published'] ?? null], fn ($v) => $v !== null),
            'canManage' => $this->can(Cms::MANAGE), 'canPublish' => $this->can(Cms::PUBLISH),
        ]);
    }

    public function show(string $id)
    {
        $album = $this->cms->get("gallery/albums/{$id}");
        $res = $this->cms->fetch("gallery/albums/{$id}/items", ['limit' => 200]);
        $items = Cms::items((array) $res->data);
        usort($items, fn ($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));
        $map = (array) ($res->data['media'] ?? []);
        foreach ($items as &$i) {
            $i['_media'] = Cms::media($i['media'] ?? ($i['mediaId'] ?? null), $map);
        }
        unset($i);

        return view('pages.website.gallery.show', [
            'album' => $album, 'itemsRes' => $res, 'items' => $items, 'status' => Cms::status($album), 'cover' => is_array($album['cover'] ?? null) ? $album['cover'] : $this->media($album['coverMediaId'] ?? null),
            'canManage' => $this->can(Cms::MANAGE), 'canPublish' => $this->can(Cms::PUBLISH), 'canUpload' => $this->can(Cms::MEDIA) && $this->can(Cms::MANAGE), 'maxMb' => 8,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:1000']]);
        $album = $this->cms->post('gallery/albums', array_filter(['title' => trim($data['title']), 'description' => trim((string) ($data['description'] ?? ''))], fn ($v) => $v !== ''));

        return redirect()->route('website.gallery.show', $album['id'])->with('success', 'Album created. Add photos below, then publish it when it is ready.');
    }

    public function update(Request $request, string $id)
    {
        $album = $this->cms->patch("gallery/albums/{$id}", array_filter([
            'title' => trim((string) $request->input('title')),
            'slug' => trim((string) $request->input('slug')),
            'description' => trim((string) $request->input('description')),
            'sortOrder' => is_numeric($request->input('sortOrder')) ? (int) $request->input('sortOrder') : null,
        ], fn ($v) => $v !== null && $v !== ''), $request->input('rowVersion'));

        $current = collect(Cms::items($this->cms->get("gallery/albums/{$id}/items", ['limit' => 200])))->keyBy('id');
        $rows = (array) $request->input('items', []);
        $changed = 0;
        foreach ($rows as $itemId => $row) {
            $cur = $current->get($itemId);
            if (! $cur) {
                continue;
            }
            $want = [
                'caption' => ($c = trim((string) ($row['caption'] ?? ''))) === '' ? null : $c,
                'altText' => ($a = trim((string) ($row['altText'] ?? ''))) === '' ? null : $a,
                'tags' => array_values(array_unique(array_filter(array_map(fn ($t) => strtolower(trim($t)), explode(',', (string) ($row['tags'] ?? ''))), fn ($t) => $t !== ''))),
                'featured' => filter_var($row['featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
            $have = ['caption' => ($cur['caption'] ?? '') === '' ? null : $cur['caption'], 'altText' => ($cur['altText'] ?? '') === '' ? null : $cur['altText'], 'tags' => array_values((array) ($cur['tags'] ?? [])), 'featured' => (bool) ($cur['featured'] ?? false)];
            if ($want !== $have) {
                $this->cms->patch("gallery/items/{$itemId}", $want, null);
                $changed++;
            }
        }
        $ids = array_values(array_filter(array_keys($rows), fn ($k) => $current->has($k)));
        $currentOrder = $current->sortBy('sortOrder')->keys()->values()->all();
        $reordered = false;
        if ($ids !== [] && $ids !== $currentOrder) {
            $this->cms->post("gallery/albums/{$id}/items/reorder", ['items' => array_map(fn ($itemId, $i) => ['id' => $itemId, 'sortOrder' => ($i + 1) * 10], $ids, array_keys($ids))]);
            $reordered = true;
        }
        $bits = array_filter([$album ? 'album details' : null, $changed ? $changed.' photo'.($changed === 1 ? '' : 's') : null, $reordered ? 'the order' : null]);

        return back()->with('success', 'Gallery saved ('.implode(', ', $bits).').');
    }

    public function transition(string $id, string $action)
    {
        $this->cms->post("gallery/albums/{$id}/{$action}");
        $word = ['publish' => 'published: visitors can see it now', 'unpublish' => 'unpublished and moved back to drafts', 'archive' => 'archived'][$action];

        return back()->with('success', "Album {$word}.");
    }

    public function destroy(string $id)
    {
        $this->cms->delete("gallery/albums/{$id}");

        return redirect()->route('website.gallery')->with('success', 'Album deleted. The pictures stay in the media library.');
    }

    /** JSON: upload one file to the media library and put it in this album. */
    public function upload(Request $request, string $id): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']]);
        try {
            $media = $this->cms->upload($request->file('file'))->body;
            $item = $this->cms->post("gallery/albums/{$id}/items", ['mediaId' => $media['id']]);
            if (! ($this->cms->get("gallery/albums/{$id}")['coverMediaId'] ?? null)) {
                $this->cms->patch("gallery/albums/{$id}", ['coverMediaId' => $media['id']], null);
            }

            return response()->json(['item' => $item, 'media' => $media], 201);
        } catch (R007ApiException $e) {
            if ($e->isUnauthenticated()) {
                return response()->json(['message' => 'Your session has ended. Please sign in again.'], 401);
            }

            return response()->json(['message' => $e->detail ?: $e->title, 'code' => $e->problemCode(), 'errors' => $e->errors()], $e->status >= 400 ? $e->status : 502);
        }
    }

    /** Add a picture that is already in the library. */
    public function add(Request $request, string $id)
    {
        $request->validate(['mediaId' => ['required', 'string']]);
        $this->cms->post("gallery/albums/{$id}/items", ['mediaId' => $request->input('mediaId')]);

        return back()->with('success', 'Picture added to the album.');
    }

    public function cover(Request $request, string $id)
    {
        $request->validate(['mediaId' => ['required', 'string']]);
        $this->cms->patch("gallery/albums/{$id}", ['coverMediaId' => $request->input('mediaId')], null);

        return back()->with('success', 'Album cover changed.');
    }

    public function removeItem(string $id, string $item)
    {
        $this->cms->delete("gallery/items/{$item}");

        return back()->with('success', 'Photo removed from the album. It is still in the media library.');
    }
}
