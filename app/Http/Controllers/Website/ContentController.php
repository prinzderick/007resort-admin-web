<?php

namespace App\Http\Controllers\Website;

use App\Support\Cms\Cms;
use App\Support\Cms\Resources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pages, blog posts, events and blog categories: one list screen and one editor driven by App\Support\Cms\Resources.
 * Publish state changes are separate API calls (`publish`, `unpublish`, `archive`); "Save and publish" is a save followed by a publish.
 */
class ContentController extends WebsiteController
{
    public const STATUS_CHIPS = ['' => 'All', 'DRAFT' => 'Drafts', 'PUBLISHED' => 'Published', 'ARCHIVED' => 'Archived'];

    public function index(Request $request, string $resource)
    {
        $def = Resources::get($resource);
        $query = ['limit' => $this->perPage($request, 50), 'cursor' => $request->query('cursor')];
        foreach (array_merge(['q', 'status'], array_column($def['filters'] ?? [], 'name')) as $k) {
            $v = $request->query($k);
            if (is_string($v) && $v !== '') {
                $query[$k] = $v;
            }
        }
        $list = $this->cms->fetch($def['api'], $query);
        $options = $this->optionsFor($def);
        $summary = $def['publish'] ? $this->cms->fetch('summary') : null;

        return view('pages.website.content.index', [
            'def' => $def, 'list' => $list, 'items' => Cms::items((array) $list->data), 'next' => $list->next(), 'options' => $options, 'chips' => self::STATUS_CHIPS, 'counts' => $this->counts($resource, $summary),
            'canManage' => $this->can(Cms::MANAGE), 'canPublish' => $this->can(Cms::PUBLISH),
        ]);
    }

    public function create(string $resource)
    {
        $def = Resources::get($resource);
        abort_unless($this->can(Cms::MANAGE), 403);

        return $this->form($def, [], null);
    }

    /** Route parameters arrive positionally: URI parameters first, then the `resource` default. */
    public function edit(string $id, string $resource)
    {
        $def = Resources::get($resource);
        $item = $this->cms->get("{$def['api']}/{$id}");

        return $this->form($def, $item, $id);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $def = Resources::get($resource);
        $payload = $this->payload($request, $def, true);
        $item = $this->cms->post($def['api'], $payload);
        $id = (string) ($item['id'] ?? '');
        $message = "{$def['label']} created.";
        if ($id !== '' && $this->wantsPublish($request, $def)) {
            $item = $this->publish($def, $id, $request);
            $message = "{$def['label']} created and ".($this->isScheduled($item) ? 'scheduled.' : 'published.');
        }

        return $id !== '' ? $this->saved("website.{$resource}.edit", ['id' => $id], $message) : redirect()->route("website.{$resource}.index")->with('success', $message);
    }

    public function update(Request $request, string $id, string $resource): RedirectResponse
    {
        $def = Resources::get($resource);
        $item = $this->cms->patch("{$def['api']}/{$id}", $this->payload($request, $def, false), $def['versioned'] ? $request->input('rowVersion') : null);
        $message = "{$def['label']} saved.";
        if ($this->wantsPublish($request, $def)) {
            $item = $this->publish($def, $id, $request);
            $message = "{$def['label']} saved and ".($this->isScheduled($item) ? 'scheduled.' : 'published.');
        }

        return $this->saved("website.{$resource}.edit", ['id' => $id], $message);
    }

    public function transition(Request $request, string $id, string $action, string $resource): RedirectResponse
    {
        $def = Resources::get($resource);
        abort_unless($def['publish'] && in_array($action, ['publish', 'unpublish', 'archive'], true), 404);
        $item = $action === 'publish' ? $this->publish($def, $id, $request) : $this->cms->post("{$def['api']}/{$id}/{$action}");
        $word = match ($action) {
            'publish' => $this->isScheduled($item) ? 'scheduled for '.Cms::when($item['publishedAt'] ?? null) : 'published',
            'unpublish' => 'unpublished and moved back to drafts',
            default => 'archived',
        };

        return back()->with('success', "{$def['label']} {$word}.");
    }

    public function destroy(string $id, string $resource): RedirectResponse
    {
        $def = Resources::get($resource);
        $this->cms->delete("{$def['api']}/{$id}");

        return redirect()->route("website.{$resource}.index")->with('success', "{$def['label']} deleted.");
    }

    // ------------------------------------------------------------------ internals

    /** @param  array<string, mixed>  $def  @param  array<string, mixed>  $item */
    private function form(array $def, array $item, ?string $id)
    {
        $resource = $def['key'];
        $options = $this->optionsFor($def);
        $values = $this->values($def, $item);
        $media = [];
        foreach (self::fields($def) as $f) {
            if (($f['type'] ?? '') === 'image') {
                $expanded = $item[self::MEDIA_KEYS[$f['name']] ?? ''] ?? null;
                $media[$f['name']] = is_array($expanded) ? $expanded : $this->media($item[$f['name']] ?? null);
            }
        }

        return view('pages.website.content.edit', [
            'def' => $def, 'item' => $item, 'id' => $id, 'values' => $values, 'media' => $media, 'options' => $options,
            'canManage' => $this->can(Cms::MANAGE), 'canPublish' => $this->can(Cms::PUBLISH), 'status' => $item ? Cms::status($item) : 'NEW',
            'facilities' => $resource === 'events' ? $options['facilities'] : [], 'products' => $resource === 'events' ? $options['products'] : [],
        ]);
    }

    public const MEDIA_KEYS = ['heroMediaId' => 'hero', 'coverMediaId' => 'cover', 'seoOgMediaId' => 'seoOgImage'];

    /** @return array<string, array<string, string>> */
    private function optionsFor(array $def): array
    {
        $o = [];
        if ($def['key'] === 'posts') {
            $o['categories'] = $this->categoryOptions();
        }
        if ($def['key'] === 'events') {
            $o['facilities'] = $this->facilityOptions();
            $o['products'] = $this->ticketProductOptions();
        }

        return $o;
    }

    /** @return list<array<string, mixed>> */
    public static function fields(array $def): array
    {
        $out = [];
        foreach (array_merge($def['main'], $def['side']) as $section) {
            array_push($out, ...($section['fields'] ?? []));
        }

        return array_merge($out, $def['extra'] ?? []);
    }

    /** Form values from an API item (Lagos-local dates, arrays kept). @return array<string, mixed> */
    private function values(array $def, array $item): array
    {
        $v = $item;
        foreach (self::fields($def) as $f) {
            if (($f['type'] ?? '') === 'datetime') {
                $v[$f['name']] = Cms::toLocalInput($item[$f['name']] ?? null);
            }
            if (($f['type'] ?? '') === 'toggle') {
                $v[$f['name']] = (bool) ($item[$f['name']] ?? ($f['default'] ?? false));
            }
        }
        if ($def['key'] === 'events') {
            $v['recurrence'] = strtoupper((string) ($item['recurrence'] ?? 'NONE')) ?: 'NONE';
            $v['ticketMode'] = ! empty($item['ticketProductId']) ? 'product' : (! empty($item['ticketUrl']) ? 'url' : 'none');
        }
        if (isset($item['publishedAt'])) {
            $v['publishedAtLocal'] = Cms::toLocalInput($item['publishedAt']);
        }

        return $v;
    }

    /**
     * Form -> API body. Create drops blank optional fields (the API generates slugs and defaults); update sends blanks as null so a field can be cleared.
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request, array $def, bool $creating): array
    {
        $out = [];
        foreach (self::fields($def) as $f) {
            $n = $f['name'];
            $raw = $request->input($n);
            $type = $f['type'] ?? 'text';
            $value = match ($type) {
                'toggle' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
                'tags' => array_values(array_filter(array_map(fn ($t) => trim((string) $t), (array) $raw), fn ($t) => $t !== '')),
                'number' => is_numeric($raw) ? (int) $raw : null,
                'datetime' => Cms::fromLocalInput(is_string($raw) ? $raw : null),
                default => is_string($raw) && trim($raw) !== '' ? trim($raw) : (($type === 'markdown' && is_string($raw)) ? '' : null),
            };
            if ($n === 'slug' && $value === null) {
                continue;
            }
            if ($creating && ($value === null || $value === '') && $type !== 'toggle') {
                continue;
            }
            $out[$n] = $value;
        }
        if ($def['key'] === 'events') {
            $mode = (string) $request->input('ticketMode', 'none');
            $out['ticketUrl'] = $mode === 'url' ? ($out['ticketUrl'] ?? null) : null;
            $out['ticketProductId'] = $mode === 'product' ? ($out['ticketProductId'] ?? null) : null;
            if (($out['recurrence'] ?? 'NONE') !== 'WEEKLY') {
                $out['recurrence'] = 'NONE';
                $out['recurrenceUntil'] = null;
            }
            if ($creating) {
                $out = array_filter($out, fn ($v, $k) => $v !== null || in_array($k, ['recurrenceUntil'], true) === false, ARRAY_FILTER_USE_BOTH);
            }
        }

        return $out;
    }

    private function wantsPublish(Request $request, array $def): bool
    {
        return $def['publish'] && $request->input('intent') === 'publish' && $this->can(Cms::PUBLISH);
    }

    /** @return array<string, mixed> */
    private function publish(array $def, string $id, Request $request): array
    {
        $body = [];
        if ($at = Cms::fromLocalInput($request->input('publishAt'))) {
            $body['publishedAt'] = $at;
        }

        return $this->cms->post("{$def['api']}/{$id}/publish", $body);
    }

    private function isScheduled(array $item): bool
    {
        return Cms::status($item) === 'SCHEDULED';
    }

    /** @return array<string, int|string> */
    private function counts(string $resource, $summary): array
    {
        if (! $summary || ! $summary->ok()) {
            return [];
        }
        $row = (array) ($summary->data[$resource === 'pages' ? 'pages' : $resource] ?? []);

        return array_filter(['DRAFT' => $row['draft'] ?? null, 'PUBLISHED' => $row['published'] ?? null], fn ($v) => $v !== null);
    }
}
