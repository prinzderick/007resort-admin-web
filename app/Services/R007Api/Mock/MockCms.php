<?php

namespace App\Services\R007Api\Mock;

use App\Services\R007Api\ApiResponse;
use App\Services\R007Api\R007ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fixture stand-in for the Website CMS admin API (`/admin/cms/*`, docs/CMS_API.md section 4) used by R007_MOCK=true.
 * Speaks the contract's shapes and rules (statuses, slug rules, must_archive_first, media_in_use, permissions) on state kept in the cache.
 * It is a UI aid, not a spec: the real API remains the authority (concurrency/If-Match is not modelled here).
 */
class MockCms
{
    private const KEY = 'r007.mock.cms.v1';

    /** @var array<string, mixed> */
    private array $s = [];

    /** @var list<string> */
    private array $perms = [];

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @param  list<string>  $perms
     */
    public function handle(string $method, string $path, array $query, array $body, array $perms): ApiResponse
    {
        $this->s = Cache::get(self::KEY) ?? MockCmsData::seed();
        $this->perms = $perms;
        $path = '/'.trim(substr($path, strlen('/admin/cms')), '/');
        $u = '(?<id>[0-9a-f-]{36})';
        $res = ['pages' => 'pages', 'posts' => 'posts', 'events' => 'events'];

        $routes = [
            ['GET', '/meta', 'cms.view', fn () => $this->meta()],
            ['GET', '/summary', 'cms.view', fn () => $this->summary()],
            ['POST', '/render-preview', 'cms.view', fn ($a, $q, $b) => $this->ok(['html' => '<p>'.e((string) ($b['markdown'] ?? '')).'</p>'])],
            // settings
            ['GET', '/settings', 'cms.view', fn () => $this->ok(['groups' => $this->s['settings'], 'media' => $this->mediaMap()])],
            ['GET', '/settings/(?<group>[a-z]+)', 'cms.view', fn ($a) => $this->ok(['group' => $a['group']] + $this->group($a['group']))],
            ['PUT', '/settings/(?<group>[a-z]+)', 'cms.manage', fn ($a, $q, $b) => $this->putGroup($a['group'], $b)],
            // home sections
            ['GET', '/home-sections', 'cms.view', fn ($a, $q) => $this->homeList($q)],
            ['GET', "/home-sections/{$u}", 'cms.view', fn ($a) => $this->ok($this->find('home', $a['id']))],
            ['POST', '/home-sections/reorder', 'cms.manage', fn ($a, $q, $b) => $this->reorder('home', (array) ($b['items'] ?? []))],
            ['POST', '/home-sections', 'cms.manage', fn ($a, $q, $b) => $this->homeCreate($b)],
            ['PATCH', "/home-sections/{$u}", 'cms.manage', fn ($a, $q, $b) => $this->homePatch($a['id'], $b)],
            ['POST', "/home-sections/{$u}/(?<act>enable|disable)", 'cms.publish', fn ($a) => $this->homeToggle($a['id'], $a['act'] === 'enable')],
            ['DELETE', "/home-sections/{$u}", 'cms.manage', fn ($a) => $this->remove('home', $a['id'])],
            // media
            ['GET', '/media', 'cms.view', fn ($a, $q) => $this->mediaList($q)],
            ['POST', '/media', 'cms.media.manage', fn ($a, $q, $b) => $this->mediaUpload($b)],
            ['GET', "/media/{$u}", 'cms.view', fn ($a) => $this->ok($this->mediaOut($this->find('media', $a['id'])))],
            ['GET', "/media/{$u}/usage", 'cms.view', fn ($a) => $this->ok($this->usage($a['id']))],
            ['PATCH', "/media/{$u}", 'cms.media.manage', fn ($a, $q, $b) => $this->mediaPatch($a['id'], $b)],
            ['DELETE', "/media/{$u}", 'cms.media.manage', fn ($a) => $this->mediaDelete($a['id'])],
            // subscribers
            ['GET', '/subscribers/export', 'cms.subscribers.export', fn ($a, $q) => $this->export($q)],
            ['GET', '/subscribers', 'cms.subscribers.view', fn ($a, $q) => $this->subList($q)],
            ['POST', "/subscribers/{$u}/unsubscribe", 'cms.manage', fn ($a) => $this->update('subs', $a['id'], ['status' => 'UNSUBSCRIBED', 'unsubscribedAt' => MockCmsData::iso()])],
            ['DELETE', "/subscribers/{$u}", 'cms.manage', fn ($a) => $this->remove('subs', $a['id'])],
            // messages
            ['GET', '/messages', 'cms.messages.manage', fn ($a, $q) => $this->msgList($q)],
            ['GET', "/messages/{$u}", 'cms.messages.manage', fn ($a) => $this->ok($this->find('msgs', $a['id']))],
            ['PATCH', "/messages/{$u}", 'cms.messages.manage', fn ($a, $q, $b) => $this->update('msgs', $a['id'], array_intersect_key($b, ['status' => 1, 'internalNote' => 1]) + (isset($b['status']) && $b['status'] !== 'NEW' ? ['handledAt' => MockCmsData::iso(), 'handledBy' => 'You'] : []))],
            ['DELETE', "/messages/{$u}", 'cms.messages.manage', fn ($a) => $this->remove('msgs', $a['id'])],
            // categories
            ['GET', '/post-categories', 'cms.view', fn ($a, $q) => $this->plainList('cats', $q, ['name', 'slug'])],
            ['POST', '/post-categories/reorder', 'cms.manage', fn ($a, $q, $b) => $this->reorder('cats', (array) ($b['items'] ?? []))],
            ['POST', '/post-categories', 'cms.manage', fn ($a, $q, $b) => $this->create('cats', $b, ['name'], 'name')],
            ['GET', "/post-categories/{$u}", 'cms.view', fn ($a) => $this->ok($this->find('cats', $a['id']))],
            ['PATCH', "/post-categories/{$u}", 'cms.manage', fn ($a, $q, $b) => $this->update('cats', $a['id'], $b, ['name'])],
            ['DELETE', "/post-categories/{$u}", 'cms.manage', fn ($a) => $this->catDelete($a['id'])],
            // albums + items
            ['GET', '/gallery/albums', 'cms.view', fn ($a, $q) => $this->contentList('albums', $q)],
            ['POST', '/gallery/albums/reorder', 'cms.manage', fn ($a, $q, $b) => $this->reorder('albums', (array) ($b['items'] ?? []))],
            ['POST', '/gallery/albums', 'cms.manage', fn ($a, $q, $b) => $this->create('albums', $b + ['status' => 'DRAFT'], ['title'], 'title')],
            ['GET', "/gallery/albums/{$u}", 'cms.view', fn ($a) => $this->ok($this->out('albums', $this->find('albums', $a['id'])))],
            ['PATCH', "/gallery/albums/{$u}", 'cms.manage', fn ($a, $q, $b) => $this->update('albums', $a['id'], $b, ['title'])],
            ['DELETE', "/gallery/albums/{$u}", 'cms.manage', fn ($a) => $this->contentDelete('albums', $a['id'])],
            ['POST', "/gallery/albums/{$u}/(?<act>publish|unpublish|archive)", 'cms.publish', fn ($a, $q, $b) => $this->transition('albums', $a['id'], $a['act'], $b)],
            ['GET', "/gallery/albums/{$u}/items", 'cms.view', fn ($a) => $this->ok(['items' => $this->albumItems($a['id']), 'nextCursor' => null, 'media' => $this->mediaMap()])],
            ['POST', "/gallery/albums/{$u}/items/reorder", 'cms.manage', fn ($a, $q, $b) => $this->reorder('items', (array) ($b['items'] ?? []))],
            ['POST', "/gallery/albums/{$u}/items", 'cms.manage', fn ($a, $q, $b) => $this->itemsAdd($a['id'], $b)],
            ['PATCH', "/gallery/items/{$u}", 'cms.manage', fn ($a, $q, $b) => $this->update('items', $a['id'], $b)],
            ['DELETE', "/gallery/items/{$u}", 'cms.manage', fn ($a) => $this->remove('items', $a['id'])],
        ];
        foreach (['pages' => [['title', 'bodyMarkdown'], 'title'], 'posts' => [['title', 'bodyMarkdown'], 'title'], 'events' => [['title', 'category', 'startsAt', 'endsAt'], 'title']] as $p => [$req, $src]) {
            $coll = $res[$p];
            $routes[] = ['GET', "/{$p}", 'cms.view', fn ($a, $q) => $this->contentList($coll, $q)];
            $routes[] = ['POST', "/{$p}", 'cms.manage', fn ($a, $q, $b) => $this->create($coll, $b + ['status' => 'DRAFT'], $req, $src)];
            $routes[] = ['GET', "/{$p}/{$u}", 'cms.view', fn ($a) => $this->ok($this->out($coll, $this->find($coll, $a['id'])))];
            $routes[] = ['PATCH', "/{$p}/{$u}", 'cms.manage', fn ($a, $q, $b) => $this->update($coll, $a['id'], $b, $req)];
            $routes[] = ['DELETE', "/{$p}/{$u}", 'cms.manage', fn ($a) => $this->contentDelete($coll, $a['id'])];
            $routes[] = ['POST', "/{$p}/{$u}/(?<act>publish|unpublish|archive)", 'cms.publish', fn ($a, $q, $b) => $this->transition($coll, $a['id'], $a['act'], $b)];
        }

        foreach ($routes as [$m, $pattern, $perm, $handler]) {
            if ($m !== $method || ! preg_match('#^'.$pattern.'$#', $path, $match)) {
                continue;
            }
            if (! in_array($perm, $this->perms, true) && ! in_array('*', $this->perms, true)) {
                $this->fail(403, 'permission_denied', "Requires permission {$perm}.", ['permission' => $perm]);
            }
            $args = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            $out = $handler($args, $query, $body);
            Cache::forever(self::KEY, $this->s);

            return $out;
        }
        $this->fail(404, null, 'No such endpoint in the mock CMS API.');
    }

    // ------------------------------------------------------------------ meta / summary

    private function meta(): ApiResponse
    {
        return $this->ok(['statuses' => ['DRAFT', 'PUBLISHED', 'ARCHIVED'], 'eventCategories' => ['SPORT', 'MUSIC', 'PARTY', 'WELLNESS', 'FOOD', 'OTHER'], 'homeSectionTypes' => new \stdClass,
            'settingGroups' => array_keys($this->s['settings']), 'contactTopics' => ['GENERAL', 'BOOKING', 'EVENTS', 'MEMBERSHIP', 'FEEDBACK', 'PRESS', 'OTHER'], 'subscriberSources' => ['footer', 'blog', 'event', 'popup', 'checkout'], 'timezone' => 'Africa/Lagos',
            'media' => ['maxBytes' => 8388608, 'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif']]]);
    }

    private function summary(): ApiResponse
    {
        $by = fn (string $c) => ['draft' => count(array_filter($this->s[$c], fn ($r) => $r['status'] === 'DRAFT')), 'published' => count(array_filter($this->s[$c], fn ($r) => $r['status'] === 'PUBLISHED'))];
        $sub = fn (string $st) => count(array_filter($this->s['subs'], fn ($r) => $r['status'] === $st));

        return $this->ok(['pages' => $by('pages'), 'posts' => $by('posts'), 'events' => $by('events'), 'albums' => $by('albums'), 'subscribers' => ['pending' => $sub('PENDING'), 'confirmed' => $sub('CONFIRMED'), 'unsubscribed' => $sub('UNSUBSCRIBED')],
            'messages' => ['new' => count(array_filter($this->s['msgs'], fn ($r) => $r['status'] === 'NEW'))], 'media' => ['count' => count($this->s['media'])]]);
    }

    // ------------------------------------------------------------------ settings

    /** @return array<string, mixed> */
    private function group(string $g): array
    {
        return $this->s['settings'][$g] ?? $this->fail(404, 'not_found', 'Unknown settings group.');
    }

    private function putGroup(string $g, array $body): ApiResponse
    {
        $cur = $this->group($g);
        $v = $body['value'] ?? null;
        if (! is_array($v)) {
            $this->invalid(['value' => ['The value is required.']]);
        }
        $errors = [];
        if ($g === 'brand' && trim((string) ($v['name'] ?? '')) === '') {
            $errors['name'] = ['The brand name is required.'];
        }
        if ($g === 'contact' && ($v['email'] ?? '') !== '' && ! filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Enter a valid email address.'];
        }
        if ($g === 'announcement' && ! empty($v['enabled']) && trim((string) ($v['text'] ?? '')) === '') {
            $errors['text'] = ['Write the announcement text or switch the bar off.'];
        }
        $errors && $this->invalid($errors);
        $this->s['settings'][$g] = ['value' => $v, 'rowVersion' => $cur['rowVersion'] + 1, 'updatedAt' => MockCmsData::iso()];

        return $this->ok(['group' => $g] + $this->s['settings'][$g]);
    }

    // ------------------------------------------------------------------ home sections

    private function homeList(array $q): ApiResponse
    {
        $rows = array_values(array_filter($this->s['home'], fn ($r) => (empty($q['type']) || $r['type'] === $q['type']) && (! isset($q['enabled']) || $q['enabled'] === '' || (bool) filter_var($q['enabled'], FILTER_VALIDATE_BOOLEAN) === $r['enabled'])));
        usort($rows, fn ($a, $b) => $a['sortOrder'] <=> $b['sortOrder']);

        return $this->ok(['items' => $rows, 'nextCursor' => null, 'media' => $this->mediaMap()]);
    }

    private function homeRules(string $type, array $p): void
    {
        $req = ['HERO_SLIDE' => ['headline', 'mediaId'], 'HIGHLIGHT' => ['title', 'blurb', 'category'], 'STAT' => ['label', 'value'], 'TESTIMONIAL' => ['name', 'quote'], 'FAQ' => ['question', 'answer'], 'PARTNER' => ['name'], 'CTA_BAND' => ['title', 'ctaLabel', 'ctaLink']];
        if (! isset($req[$type])) {
            $this->invalid(['type' => ['Unknown section type.']]);
        }
        $errors = [];
        foreach ($req[$type] as $f) {
            if (trim((string) ($p[$f] ?? '')) === '') {
                $errors["payload.{$f}"] = ['This field is required.'];
            }
        }
        $errors && $this->invalid($errors);
    }

    private function homeCreate(array $b): ApiResponse
    {
        $this->homeRules((string) ($b['type'] ?? ''), (array) ($b['payload'] ?? []));
        $max = max(array_column($this->s['home'], 'sortOrder') ?: [0]);
        $row = ['id' => (string) Str::uuid(), 'type' => $b['type'], 'sortOrder' => $b['sortOrder'] ?? $max + 10, 'enabled' => false, 'payload' => $b['payload'], 'rowVersion' => 1, 'createdAt' => MockCmsData::iso(), 'updatedAt' => MockCmsData::iso()];
        $this->s['home'][] = $row;

        return $this->ok($row, 201);
    }

    private function homePatch(string $id, array $b): ApiResponse
    {
        $cur = $this->find('home', $id);
        if (isset($b['payload'])) {
            $this->homeRules($cur['type'], (array) $b['payload']);
        }

        return $this->update('home', $id, array_intersect_key($b, ['payload' => 1, 'sortOrder' => 1]));
    }

    private function homeToggle(string $id, bool $on): ApiResponse
    {
        return $this->update('home', $id, ['enabled' => $on]);
    }

    // ------------------------------------------------------------------ media

    /** @return array<string, array<string, mixed>> */
    private function mediaMap(): array
    {
        $out = [];
        foreach ($this->s['media'] as $m) {
            $out[$m['id']] = $this->mediaOut($m);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function mediaOut(array $m): array
    {
        return $m + ['usageCount' => count($this->usage($m['id'])['usage'])];
    }

    private function mediaList(array $q): ApiResponse
    {
        $rows = array_values(array_filter(array_reverse($this->s['media']), fn ($m) => (empty($q['q']) || str_contains(strtolower($m['alt'].' '.$m['originalName'].' '.$m['credit']), strtolower($q['q']))) && (empty($q['tag']) || in_array($q['tag'], $m['tags'], true))));

        return $this->page($rows, $q, fn ($m) => $this->mediaOut($m));
    }

    private function mediaUpload(array $b): ApiResponse
    {
        $f = $b['_file'] ?? null;
        if (! $f || ! is_file($f['path'])) {
            $this->invalid(['file' => ['Choose an image to upload.']]);
        }
        if (! in_array($f['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
            $this->fail(415, 'media_type_unsupported', 'Only JPEG, PNG, WebP or AVIF images can be uploaded.');
        }
        if ($f['size'] > 8388608) {
            $this->fail(413, 'media_too_large', 'That image is larger than 8 MB.');
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif'][$f['mime']];
        $id = (string) Str::uuid();
        Storage::disk('public')->put("mock-cms/{$id}.{$ext}", (string) file_get_contents($f['path']));
        [$w, $h] = @getimagesize($f['path']) ?: [1200, 800];
        $row = ['id' => $id, 'url' => url("/storage/mock-cms/{$id}.{$ext}"), 'sourceUrl' => $b['sourceUrl'] ?? null, 'width' => $w, 'height' => $h, 'mimeType' => $f['mime'], 'alt' => (string) ($b['alt'] ?? ''), 'credit' => (string) ($b['credit'] ?? ''), 'dominantColor' => '#7a8896', 'variants' => [],
            'tags' => array_values((array) ($b['tags'] ?? [])), 'sizeBytes' => $f['size'], 'originalName' => $f['name'], 'rowVersion' => 1, 'createdAt' => MockCmsData::iso(), 'updatedAt' => MockCmsData::iso()];
        $this->s['media'][] = $row;

        return $this->ok($this->mediaOut($row), 201);
    }

    private function mediaPatch(string $id, array $b): ApiResponse
    {
        return $this->update('media', $id, array_intersect_key($b, ['alt' => 1, 'credit' => 1, 'sourceUrl' => 1, 'tags' => 1]));
    }

    private function mediaDelete(string $id): ApiResponse
    {
        $this->find('media', $id);
        $u = $this->usage($id);
        if ($u['usageCount'] > 0) {
            $this->fail(409, 'media_in_use', 'This picture is still used on the website, so it cannot be deleted.', ['usage' => $u['usage']]);
        }

        return $this->remove('media', $id);
    }

    /** @return array{usageCount: int, usage: list<array<string, mixed>>} */
    private function usage(string $id): array
    {
        $out = [];
        $scan = function (string $type, array $rows, array $fields, callable $label) use ($id, &$out) {
            foreach ($rows as $r) {
                foreach ($fields as $f) {
                    if (($r[$f] ?? null) === $id) {
                        $out[] = ['type' => $type, 'id' => $r['id'] ?? $type, 'label' => $label($r), 'field' => $f];
                    }
                }
            }
        };
        $scan('page', $this->s['pages'], ['heroMediaId', 'seoOgMediaId'], fn ($r) => $r['title']);
        $scan('post', $this->s['posts'], ['coverMediaId', 'seoOgMediaId'], fn ($r) => $r['title']);
        $scan('event', $this->s['events'], ['coverMediaId', 'seoOgMediaId'], fn ($r) => $r['title']);
        $scan('album', $this->s['albums'], ['coverMediaId'], fn ($r) => $r['title']);
        $scan('gallery_item', $this->s['items'], ['mediaId'], fn ($r) => $r['caption'] ?: 'Gallery photo');
        foreach ($this->s['home'] as $r) {
            foreach ($r['payload'] as $f => $v) {
                if ($v === $id) {
                    $out[] = ['type' => 'home_section', 'id' => $r['id'], 'label' => (string) ($r['payload']['headline'] ?? $r['payload']['title'] ?? $r['type']), 'field' => $f];
                }
            }
        }
        foreach ($this->s['settings'] as $g => $row) {
            foreach ($row['value'] as $f => $v) {
                if ($v === $id) {
                    $out[] = ['type' => 'setting', 'id' => $g, 'label' => ucfirst($g).' settings', 'field' => $f];
                }
            }
        }

        return ['usageCount' => count($out), 'usage' => $out];
    }

    // ------------------------------------------------------------------ subscribers / messages

    private function subList(array $q): ApiResponse
    {
        $all = $this->s['subs'];
        $rows = array_values(array_filter($all, fn ($r) => (empty($q['status']) || $r['status'] === $q['status']) && (empty($q['source']) || $r['source'] === $q['source']) && (empty($q['q']) || str_contains(strtolower($r['email'].' '.$r['name']), strtolower($q['q'])))));
        $c = fn (string $st) => count(array_filter($all, fn ($r) => $r['status'] === $st));

        return $this->page($rows, $q, null, ['counts' => ['pending' => $c('PENDING'), 'confirmed' => $c('CONFIRMED'), 'unsubscribed' => $c('UNSUBSCRIBED')]]);
    }

    private function export(array $q): ApiResponse
    {
        $lines = ['email,name,source,status,consentedAt,confirmedAt,unsubscribedAt'];
        foreach ($this->s['subs'] as $r) {
            if (! empty($q['status']) && $r['status'] !== $q['status']) {
                continue;
            }
            $lines[] = implode(',', [$r['email'], $r['name'], $r['source'], $r['status'], $r['consentedAt'], $r['confirmedAt'], $r['unsubscribedAt']]);
        }

        return new ApiResponse(200, ['_raw' => implode("\n", $lines)."\n"], ['content-type' => 'text/csv; charset=utf-8']);
    }

    private function msgList(array $q): ApiResponse
    {
        $all = array_reverse($this->s['msgs']);
        $rows = array_values(array_filter($all, fn ($r) => (empty($q['status']) || $r['status'] === $q['status']) && (empty($q['topic']) || $r['topic'] === $q['topic']) && (empty($q['q']) || str_contains(strtolower($r['name'].' '.$r['email'].' '.$r['message']), strtolower($q['q'])))));
        $c = fn (string $st) => count(array_filter($this->s['msgs'], fn ($r) => $r['status'] === $st));

        return $this->page($rows, $q, null, ['counts' => ['new' => $c('NEW'), 'read' => $c('READ'), 'replied' => $c('REPLIED'), 'spam' => $c('SPAM')]]);
    }

    // ------------------------------------------------------------------ categories / albums / items

    private function catDelete(string $id): ApiResponse
    {
        $c = $this->find('cats', $id);
        if (array_filter($this->s['posts'], fn ($p) => ($p['categoryId'] ?? null) === $id)) {
            $this->fail(409, 'category_in_use', 'Posts still use this category. Move them first.');
        }

        return $this->remove('cats', $id);
    }

    /** @return list<array<string, mixed>> */
    private function albumItems(string $albumId): array
    {
        $rows = array_values(array_filter($this->s['items'], fn ($i) => $i['albumId'] === $albumId));
        usort($rows, fn ($a, $b) => $a['sortOrder'] <=> $b['sortOrder']);

        return array_map(fn ($i) => $i + ['media' => $this->mediaOut($this->find('media', $i['mediaId']))], $rows);
    }

    private function itemsAdd(string $albumId, array $b): ApiResponse
    {
        $this->find('albums', $albumId);
        $list = isset($b['items']) ? (array) $b['items'] : [$b];
        $out = [];
        foreach ($list as $it) {
            $this->find('media', (string) ($it['mediaId'] ?? ''));
            if (array_filter($this->s['items'], fn ($x) => $x['albumId'] === $albumId && $x['mediaId'] === $it['mediaId'])) {
                $this->fail(409, 'duplicate_item', 'That picture is already in this album.');
            }
            $max = max(array_column(array_filter($this->s['items'], fn ($x) => $x['albumId'] === $albumId), 'sortOrder') ?: [0]);
            $row = ['id' => (string) Str::uuid(), 'albumId' => $albumId, 'mediaId' => $it['mediaId'], 'caption' => $it['caption'] ?? null, 'altText' => $it['altText'] ?? null, 'category' => $it['category'] ?? null, 'tags' => $it['tags'] ?? [], 'featured' => (bool) ($it['featured'] ?? false), 'sortOrder' => $max + 10, 'rowVersion' => 1];
            $this->s['items'][] = $row;
            $out[] = $row;
        }

        return $this->ok(isset($b['items']) ? ['items' => $out] : $out[0], 201);
    }

    // ------------------------------------------------------------------ generic engine

    /** @return array<string, mixed> */
    private function find(string $coll, string $id): array
    {
        foreach ($this->s[$coll] as $r) {
            if (($r['id'] ?? null) === $id) {
                return $r;
            }
        }
        $this->fail(404, 'not_found', 'Resource not found.');
    }

    /** Expanded admin resource: media objects and derived counts. */
    private function out(string $coll, array $r): array
    {
        $exp = function (string $idKey, string $key) use (&$r) {
            $m = collect($this->s['media'])->firstWhere('id', $r[$idKey] ?? null);
            $r[$key] = $m ? $this->mediaOut($m) : null;
        };
        match ($coll) {
            'pages' => [$exp('heroMediaId', 'hero'), $exp('seoOgMediaId', 'seoOgImage')],
            'posts', 'events' => [$exp('coverMediaId', 'cover'), $exp('seoOgMediaId', 'seoOgImage')],
            'albums' => [$exp('coverMediaId', 'cover'), $r['itemCount'] = count(array_filter($this->s['items'], fn ($i) => $i['albumId'] === $r['id']))],
            'cats' => $r['postCount'] = count(array_filter($this->s['posts'], fn ($p) => ($p['categoryId'] ?? null) === $r['id'])),
            default => null,
        };

        return $r;
    }

    private function contentList(string $coll, array $q): ApiResponse
    {
        $rows = array_values(array_filter(array_reverse($this->s[$coll]), function ($r) use ($q) {
            $hay = strtolower(($r['title'] ?? '').' '.($r['slug'] ?? '').' '.($r['excerpt'] ?? '').' '.($r['summary'] ?? ''));

            return (empty($q['status']) || $r['status'] === $q['status'])
                && (empty($q['q']) || str_contains($hay, strtolower($q['q'])))
                && (empty($q['category']) || ($r['category'] ?? null) === $q['category'])
                && (empty($q['categoryId']) || ($r['categoryId'] ?? null) === $q['categoryId'])
                && (empty($q['tag']) || in_array($q['tag'], (array) ($r['tags'] ?? []), true))
                && (! isset($q['featured']) || $q['featured'] === '' || (bool) filter_var($q['featured'], FILTER_VALIDATE_BOOLEAN) === (bool) ($r['featured'] ?? false))
                && (empty($q['when']) || ($q['when'] === 'upcoming') === (strtotime($r['endsAt'] ?? 'now') >= time()));
        }));

        return $this->page($rows, $q, fn ($r) => $this->out($coll, $r));
    }

    private function plainList(string $coll, array $q, array $search): ApiResponse
    {
        $rows = array_values(array_filter($this->s[$coll], fn ($r) => empty($q['q']) || str_contains(strtolower(implode(' ', array_map(fn ($f) => $r[$f] ?? '', $search))), strtolower($q['q']))));
        usort($rows, fn ($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));

        return $this->page($rows, $q, fn ($r) => $this->out($coll, $r));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $extra
     */
    private function page(array $rows, array $q, ?callable $map = null, array $extra = []): ApiResponse
    {
        $limit = max(1, min(200, (int) ($q['limit'] ?? 50)));
        $offset = ! empty($q['cursor']) ? (int) base64_decode((string) $q['cursor']) : 0;
        $slice = array_slice($rows, $offset, $limit);
        $next = $offset + $limit < count($rows) ? base64_encode((string) ($offset + $limit)) : null;

        return $this->ok(['items' => array_map($map ?? fn ($r) => $r, $slice), 'nextCursor' => $next] + $extra);
    }

    /**
     * @param  list<string>  $required
     */
    private function create(string $coll, array $b, array $required, string $slugFrom): ApiResponse
    {
        $errors = [];
        foreach ($required as $f) {
            if (trim((string) ($b[$f] ?? '')) === '') {
                $errors[$f] = ['This field is required.'];
            }
        }
        $errors && $this->invalid($errors);
        $slug = $this->slug($coll, (string) ($b['slug'] ?? ''), (string) $b[$slugFrom]);
        $row = $b + ['tags' => [], 'featured' => false];
        unset($row['_file']);
        $row = array_merge($row, ['id' => (string) Str::uuid(), 'slug' => $slug, 'status' => $b['status'] ?? 'DRAFT', 'publishedAt' => null, 'rowVersion' => 1, 'createdAt' => MockCmsData::iso(), 'updatedAt' => MockCmsData::iso(), 'sortOrder' => $b['sortOrder'] ?? (max(array_column($this->s[$coll], 'sortOrder') ?: [0]) + 10)]);
        if ($coll === 'posts') {
            $row = $this->postDerived($row);
        }
        $this->s[$coll][] = $row;

        return $this->ok($this->out($coll, $row), 201, ['etag' => '"1"']);
    }

    private function update(string $coll, string $id, array $b, array $required = []): ApiResponse
    {
        $errors = [];
        foreach ($required as $f) {
            if (array_key_exists($f, $b) && trim((string) $b[$f]) === '') {
                $errors[$f] = ['This field is required.'];
            }
        }
        if (($b['recurrence'] ?? null) === 'WEEKLY' && empty($b['recurrenceUntil']) === false && ! empty($b['startsAt']) && substr($b['startsAt'], 0, 10) > $b['recurrenceUntil']) {
            $errors['recurrenceUntil'] = ['The end date must be on or after the first date.'];
        }
        if (isset($b['startsAt'], $b['endsAt']) && $b['endsAt'] <= $b['startsAt']) {
            $errors['endsAt'] = ['The event must end after it starts.'];
        }
        $errors && $this->invalid($errors);
        foreach ($this->s[$coll] as $i => $r) {
            if ($r['id'] !== $id) {
                continue;
            }
            if (isset($b['slug']) && $b['slug'] !== $r['slug']) {
                $b['slug'] = $this->slug($coll, (string) $b['slug'], '', $id);
            }
            $new = array_merge($r, array_diff_key($b, ['id' => 1, 'rowVersion' => 1, 'status' => 1]), isset($b['status']) && in_array($coll, ['subs', 'msgs'], true) ? ['status' => $b['status']] : [], ['rowVersion' => ($r['rowVersion'] ?? 1) + 1, 'updatedAt' => MockCmsData::iso()]);
            if ($coll === 'posts') {
                $new = $this->postDerived($new);
                if (isset($b['categoryId'])) {
                    $c = collect($this->s['cats'])->firstWhere('id', $b['categoryId']);
                    $new['category'] = $c ? ['id' => $c['id'], 'slug' => $c['slug'], 'name' => $c['name']] : null;
                }
            }
            $this->s[$coll][$i] = $new;

            return $this->ok($this->out($coll, $new), 200, ['etag' => '"'.$new['rowVersion'].'"']);
        }
        $this->fail(404, 'not_found', 'Resource not found.');
    }

    private function postDerived(array $p): array
    {
        $words = str_word_count(strip_tags((string) ($p['bodyMarkdown'] ?? '')));
        $p['readingTimeMinutes'] = max(1, (int) round($words / 220));
        if (($p['excerpt'] ?? '') === '') {
            $p['excerpt'] = Str::limit(trim(preg_replace('/[#*_>\-\[\]()]+/', ' ', (string) ($p['bodyMarkdown'] ?? ''))), 200);
        }

        return $p;
    }

    private function contentDelete(string $coll, string $id): ApiResponse
    {
        $r = $this->find($coll, $id);
        if (($r['status'] ?? '') === 'PUBLISHED') {
            $this->fail(409, 'must_archive_first', 'Archive this first: published content cannot be deleted.');
        }
        if ($coll === 'albums') {
            $this->s['items'] = array_values(array_filter($this->s['items'], fn ($i) => $i['albumId'] !== $id));
        }

        return $this->remove($coll, $id);
    }

    private function remove(string $coll, string $id): ApiResponse
    {
        $this->find($coll, $id);
        $this->s[$coll] = array_values(array_filter($this->s[$coll], fn ($r) => $r['id'] !== $id));

        return new ApiResponse(204, []);
    }

    private function transition(string $coll, string $id, string $act, array $b): ApiResponse
    {
        $r = $this->find($coll, $id);
        $patch = match ($act) {
            'publish' => ['status' => 'PUBLISHED', 'publishedAt' => $b['publishedAt'] ?? ($r['publishedAt'] && $r['status'] === 'PUBLISHED' ? $r['publishedAt'] : MockCmsData::iso())],
            'unpublish' => ['status' => 'DRAFT'],
            default => ['status' => 'ARCHIVED'],
        };
        if ($act === 'publish' && $r['status'] === 'PUBLISHED' && empty($b['publishedAt'])) {
            return $this->ok($this->out($coll, $r));
        }
        foreach ($this->s[$coll] as $i => $x) {
            if ($x['id'] === $id) {
                $this->s[$coll][$i] = array_merge($x, $patch, ['rowVersion' => $x['rowVersion'] + 1, 'updatedAt' => MockCmsData::iso()]);

                return $this->ok($this->out($coll, $this->s[$coll][$i]));
            }
        }
        $this->fail(404, 'not_found', 'Resource not found.');
    }

    private function reorder(string $coll, array $items): ApiResponse
    {
        $n = 0;
        foreach ($items as $it) {
            foreach ($this->s[$coll] as $i => $r) {
                if ($r['id'] === ($it['id'] ?? null)) {
                    $this->s[$coll][$i]['sortOrder'] = (int) $it['sortOrder'];
                    $n++;

                    continue 2;
                }
            }
            $this->fail(422, 'validation_failed', 'Unknown id in the list.');
        }

        return $this->ok(['updated' => $n]);
    }

    private function slug(string $coll, string $slug, string $from, ?string $ignore = null): string
    {
        $taken = fn (string $s) => array_filter($this->s[$coll], fn ($r) => ($r['slug'] ?? null) === $s && $r['id'] !== $ignore);
        if ($slug !== '') {
            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                $this->invalid(['slug' => ['Use lowercase letters, numbers and single dashes only.']]);
            }
            $taken($slug) && $this->fail(409, 'slug_taken', 'That web address is already used. Choose another.');

            return $slug;
        }
        $base = Str::slug($from) ?: 'item';
        $s = $base;
        for ($i = 2; $taken($s); $i++) {
            $s = "{$base}-{$i}";
        }

        return $s;
    }

    // ------------------------------------------------------------------ plumbing

    /** @param  array<mixed>  $body */
    private function ok(array $body, int $status = 200, array $headers = []): ApiResponse
    {
        return new ApiResponse($status, $body, $headers);
    }

    /** @param  array<string, list<string>>  $errors */
    private function invalid(array $errors): never
    {
        throw R007ApiException::fromProblem(422, ['type' => 'about:blank', 'title' => 'Validation failed', 'status' => 422, 'detail' => 'Please correct the highlighted fields.', 'code' => 'validation_failed', 'errors' => $errors]);
    }

    private function fail(int $status, ?string $code, string $detail, array $extra = []): never
    {
        throw R007ApiException::fromProblem($status, array_filter(['type' => 'about:blank', 'title' => Str::headline((string) ($code ?? 'not found')), 'status' => $status, 'detail' => $detail, 'code' => $code] + $extra, fn ($v) => $v !== null));
    }
}
