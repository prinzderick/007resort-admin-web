<?php

namespace App\Http\Controllers\Website;

use App\Auth\StaffSession;
use App\Http\Controllers\Controller;
use App\Services\Cms\CmsApi;
use App\Services\Portal\DashboardData;
use App\Services\R007Api\R007ApiClient;
use App\Services\R007Api\R007ApiException;
use App\Support\Cms\Cms;
use App\Support\Fetch;

/** Shared plumbing for the Website (CMS) screens. Rules and validation live in the API; these controllers shape forms into requests. */
abstract class WebsiteController extends Controller
{
    /** @var array<string, array<string, mixed>|null> */
    private array $mediaCache = [];

    public function __construct(R007ApiClient $api, StaffSession $staff, protected CmsApi $cms)
    {
        parent::__construct($api, $staff);
    }

    /** @param  array<string, mixed>|null  $item  */
    protected function can(string $permission): bool
    {
        return $this->staff->can($permission);
    }

    /** id => name for blog categories. @return array<string, string> */
    protected function categoryOptions(): array
    {
        $f = $this->cms->fetch('post-categories', ['limit' => 200]);

        return collect($f->items())->filter(fn ($c) => isset($c['id']))->mapWithKeys(fn ($c) => [$c['id'] => (string) ($c['name'] ?? $c['slug'] ?? $c['id'])])->all();
    }

    /** id => name for facilities (venue picker). @return array<string, string> */
    protected function facilityOptions(): array
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'));
        if (! $tree->ok()) {
            return [];
        }

        return collect(app(DashboardData::class)->flatten($tree->items()))->filter(fn ($f) => isset($f['id']))->mapWithKeys(fn ($f) => [$f['id'] => (string) ($f['name'] ?? $f['code'] ?? $f['id'])])->all();
    }

    /** id => name for ticket products. @return array<string, string> */
    protected function ticketProductOptions(): array
    {
        $f = Fetch::of(fn () => $this->api->get('admin/catalog/products', ['limit' => 200, 'kind' => 'TICKET']));

        return collect($f->items())->filter(fn ($p) => isset($p['id']))->mapWithKeys(fn ($p) => [$p['id'] => (string) ($p['name'] ?? $p['sku'] ?? $p['id'])])->all();
    }

    /**
     * The Media object for an id: taken from an expanded map when present, else read from the library (once per request). Null when unknown.
     *
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>|null
     */
    protected function media(?string $id, array $map = []): ?array
    {
        if (! $id) {
            return null;
        }
        if ($m = Cms::media($id, $map)) {
            return $m;
        }
        if (! array_key_exists($id, $this->mediaCache)) {
            try {
                $this->mediaCache[$id] = $this->cms->get("media/{$id}");
            } catch (R007ApiException $e) {
                if ($e->isUnauthenticated()) {
                    throw $e;
                }
                $this->mediaCache[$id] = null;
            }
        }

        return $this->mediaCache[$id];
    }

    /** Flash + redirect helper for "saved" outcomes. */
    protected function saved(string $route, array $params, string $message)
    {
        return redirect()->route($route, $params)->with('success', $message);
    }
}
