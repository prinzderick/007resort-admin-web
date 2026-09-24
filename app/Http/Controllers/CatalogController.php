<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Services\R007Api\R007ApiException;
use App\Support\Fetch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Setup > Catalog & prices (docs/CONFIG_ADMIN_API.md section 6): products, where each is sold, prices, categories, tax rates,
 * kitchen/bar routing, stock links, and CSV import with a dry-run report. All rules and validation live in the API; this controller
 * only shapes forms into requests and API answers into plain language.
 */
class CatalogController extends Controller
{
    public const TABS = ['products' => 'Products', 'categories' => 'Categories', 'prices' => 'Prices', 'tax' => 'Tax rates', 'import' => 'Import & export'];

    public const KINDS = ['GOOD' => 'Item (food, drink, goods)', 'SERVICE' => 'Service', 'TICKET' => 'Ticket', 'RENTAL' => 'Rental', 'MEMBERSHIP' => 'Membership', 'FEE' => 'Fee'];

    private const DECIMAL = '/^\d{1,15}(\.\d{1,4})?$/';

    public function index(Request $request, DashboardData $dash)
    {
        $tab = array_key_exists($request->query('tab', 'products'), self::TABS) ? (string) $request->query('tab', 'products') : 'products';
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        $categories = $this->all('catalog/categories', ['includeInactive' => 1], ['GET', '/catalog/categories']);
        $prepRoutes = Fetch::of(fn () => $this->api->get('catalog/prep-routes'), ['GET', '/catalog/prep-routes']);
        $taxRates = Fetch::of(fn () => $this->api->get('catalog/tax-rates', ['includeInactive' => 1]), ['GET', '/catalog/tax-rates']);
        $data = [
            'tab' => $tab, 'tabs' => self::TABS, 'facilities' => $flat, 'categories' => $categories, 'prepRoutes' => $prepRoutes, 'taxRates' => $taxRates, 'kinds' => self::KINDS,
            'canManage' => $this->staff->can('catalog.manage'), 'canPrice' => $this->staff->can('pricing.manage'), 'canAvail' => $this->staff->can('catalog.availability.manage'),
            'catNames' => collect($categories->items())->pluck('name', 'id')->all(), 'import' => session('import'),
        ];

        if ($tab === 'products') {
            $facilityId = (string) $request->query('facility', '');
            $params = ['q' => $request->query('q'), 'categoryId' => $request->query('category'), 'active' => $request->query('active') !== null && $request->query('active') !== '' ? ($request->query('active') === '1' ? 'true' : 'false') : null];
            $products = $this->all('admin/catalog/products', array_filter($params, fn ($v) => $v !== null && $v !== ''), ['GET', '/admin/catalog/products'], 5);
            $avail = $facilityId !== '' ? $this->all('catalog/availability', ['facilityId' => $facilityId], ['GET', '/catalog/availability'], 3) : new Fetch(['items' => []]);
            $data += ['products' => $products, 'facilityId' => $facilityId, 'availability' => collect($avail->items())->mapWithKeys(fn ($a) => [($a['productId'] ?? '') => (bool) ($a['available'] ?? true)])->all(),
                'sold' => $facilityId !== '' ? collect($avail->items())->pluck('productId')->flip()->all() : null];
        } elseif ($tab === 'prices') {
            $data += ['priceLists' => Fetch::of(fn () => $this->api->get('catalog/price-lists'), ['GET', '/catalog/price-lists']), 'products' => $this->all('admin/catalog/products', [], ['GET', '/admin/catalog/products'], 5)];
        }

        return view('pages.setup.catalog.index', $data);
    }

    // ---- product editor ---------------------------------------------------------------------------------------------------

    public function product(string $product, DashboardData $dash)
    {
        $res = Fetch::of(fn () => $this->api->request('GET', "admin/catalog/products/{$product}"), ['GET', '/admin/catalog/products/{productId}']);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        $p = $res->ok() ? (array) $res->data->body : [];
        $stock = Fetch::of(fn () => $this->api->get("catalog/products/{$product}/stock-links"), ['GET', '/catalog/products/{productId}/stock-links']);
        $items = $this->staff->canAny('catalog.manage', 'inventory.view') ? $this->all('inventory/items', [], ['GET', '/inventory/items'], 3) : new Fetch(['items' => []]);

        return view('pages.setup.catalog.product', [
            'fetch' => $res->ok() ? new Fetch($p) : $res, 'p' => $p, 'etag' => $res->ok() ? $res->data->etag() : null, 'id' => $product, 'facilities' => $flat,
            'categories' => $this->all('catalog/categories', ['includeInactive' => 1], ['GET', '/catalog/categories']), 'prepRoutes' => Fetch::of(fn () => $this->api->get('catalog/prep-routes'), ['GET', '/catalog/prep-routes']),
            'taxRates' => Fetch::of(fn () => $this->api->get('catalog/tax-rates', ['includeInactive' => 1]), ['GET', '/catalog/tax-rates']), 'priceLists' => Fetch::of(fn () => $this->api->get('catalog/price-lists'), ['GET', '/catalog/price-lists']),
            'stock' => $stock, 'stockItems' => $items, 'kinds' => self::KINDS, 'canManage' => $this->staff->can('catalog.manage'), 'canPrice' => $this->staff->can('pricing.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage'), 403);
        $d = $request->validate([
            'sku' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:200'], 'categoryId' => ['required', 'uuid'], 'kind' => ['required', 'in:'.implode(',', array_keys(self::KINDS))],
            'price' => ['required', 'regex:'.self::DECIMAL], 'prepRouteId' => ['nullable', 'uuid'], 'taxRateId' => ['nullable', 'uuid'], 'description' => ['nullable', 'string', 'max:500'], 'barcode' => ['nullable', 'string', 'max:64'],
            'facilityIds' => ['nullable', 'array'], 'facilityIds.*' => ['uuid'],
        ]);
        $body = ['sku' => $d['sku'], 'name' => $d['name'], 'categoryId' => $d['categoryId'], 'kind' => $d['kind'], 'price' => $d['price'], 'trackStock' => $request->boolean('trackStock'), 'taxExempt' => $request->boolean('taxExempt')]
            + array_filter(['prepRouteId' => $d['prepRouteId'] ?? null, 'taxRateId' => $d['taxRateId'] ?? null, 'description' => $d['description'] ?? null, 'barcode' => $d['barcode'] ?? null, 'facilityIds' => $d['facilityIds'] ?? null], fn ($v) => $v !== null && $v !== '' && $v !== []);
        $res = $this->api->request('POST', 'catalog/products', [], $body);

        return redirect()->route('setup.catalog.product', $res->body['id'] ?? '')->with('success', 'Product created. Choose where it is sold below.');
    }

    public function update(Request $request, string $product): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage'), 403);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:200'], 'categoryId' => ['required', 'uuid'], 'kind' => ['required', 'in:'.implode(',', array_keys(self::KINDS))], 'description' => ['nullable', 'string', 'max:500'],
            'barcode' => ['nullable', 'string', 'max:64'], 'prepRouteId' => ['nullable', 'uuid'], 'taxRateId' => ['nullable', 'uuid'], 'etag' => ['nullable', 'string', 'max:40'],
        ]);
        $body = ['name' => $d['name'], 'categoryId' => $d['categoryId'], 'kind' => $d['kind'], 'description' => $d['description'] ?? null, 'barcode' => $d['barcode'] ?? null,
            'prepRouteId' => $d['prepRouteId'] ?? null, 'taxRateId' => $d['taxRateId'] ?? null, 'taxExempt' => $request->boolean('taxExempt'), 'trackStock' => $request->boolean('trackStock'), 'active' => $request->boolean('active')];
        $this->api->request('PATCH', "catalog/products/{$product}", [], $body, $this->ifMatch($d['etag'] ?? null));

        return redirect()->route('setup.catalog.product', $product)->with('success', 'Product saved.');
    }

    /** Make the product sold at a facility (or change its screen / price there). */
    public function facility(Request $request, string $product, string $facility): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage'), 403);
        $d = $request->validate(['price' => ['nullable', 'regex:'.self::DECIMAL], 'unavailableReason' => ['nullable', 'string', 'max:120'], 'kdsStationId' => ['nullable', 'uuid']]);
        $this->api->request('PUT', "catalog/products/{$product}/facilities/{$facility}", [], [
            'available' => $request->boolean('available'), 'unavailableReason' => $d['unavailableReason'] ?? null, 'kdsStationId' => $d['kdsStationId'] ?? null, 'price' => ($d['price'] ?? '') !== '' ? $d['price'] : null,
        ]);

        return redirect()->route('setup.catalog.product', $product)->with('success', 'Saved for this facility.');
    }

    public function removeFacility(string $product, string $facility): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage'), 403);
        $this->api->request('DELETE', "catalog/products/{$product}/facilities/{$facility}");

        return redirect()->route('setup.catalog.product', $product)->with('success', 'No longer sold at that facility.');
    }

    /** The "86" switch used from the product list. */
    public function setAvailability(Request $request, string $product): RedirectResponse
    {
        abort_unless($this->staff->canAny('catalog.availability.manage', 'catalog.manage'), 403);
        $d = $request->validate(['facilityId' => ['required', 'uuid'], 'available' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:120']]);
        $this->api->request('PUT', "catalog/products/{$product}/availability/{$d['facilityId']}", [], array_filter(['available' => (bool) $d['available'], 'reason' => $d['reason'] ?? null], fn ($v) => $v !== null));

        return redirect()->route('setup.catalog', ['facility' => $d['facilityId']] + array_filter(['q' => $request->input('q')]))->with('success', $d['available'] ? 'Item is available again.' : 'Item marked unavailable (86\'d) at this facility.');
    }

    /** A new price: it starts now (or on the chosen date) and closes the previous open one. */
    public function price(Request $request, string $product): RedirectResponse
    {
        abort_unless($this->staff->can('pricing.manage'), 403);
        $d = $request->validate(['amount' => ['required', 'regex:'.self::DECIMAL], 'scope' => ['required', 'in:ALL,ONE'], 'facilityId' => ['nullable', 'uuid'], 'validFrom' => ['nullable', 'date'], 'priceListId' => ['nullable', 'uuid']]);
        $body = array_filter(['productId' => $product, 'amount' => $d['amount'], 'priceListId' => $d['priceListId'] ?? null, 'facilityId' => $d['scope'] === 'ONE' ? ($d['facilityId'] ?? null) : null, 'validFrom' => ! empty($d['validFrom']) ? $d['validFrom'].'T00:00:00Z' : null], fn ($v) => $v !== null);
        $this->api->request('POST', 'catalog/prices', [], $body);

        return redirect()->route('setup.catalog.product', $product)->with('success', 'New price saved. Orders taken from now on use it; open orders keep their price.');
    }

    public function stockLinks(Request $request, string $product): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage'), 403);
        $links = collect((array) $request->input('links', []))->filter(fn ($l) => ! empty($l['stockItemId']) && ($l['quantityPerUnit'] ?? '') !== '')
            ->map(fn ($l) => ['stockItemId' => $l['stockItemId'], 'quantityPerUnit' => (string) $l['quantityPerUnit']])->values()->all();
        $this->api->request('PUT', "catalog/products/{$product}/stock-links", [], ['links' => $links]);

        return redirect()->route('setup.catalog.product', $product)->with('success', $links === [] ? 'Stock link removed. Selling this product no longer deducts stock.' : 'Stock link saved. Each sale now deducts these quantities.');
    }

    // ---- categories, tax rates, price lists -------------------------------------------------------------------------------

    public function category(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage'), 403);
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'sortOrder' => ['nullable', 'integer']]);
        $this->api->request('POST', 'catalog/categories', [], array_filter(['name' => $d['name'], 'sortOrder' => isset($d['sortOrder']) ? (int) $d['sortOrder'] : null], fn ($v) => $v !== null));

        return redirect()->route('setup.catalog', ['tab' => 'categories'])->with('success', 'Category created.');
    }

    public function categoryRoute(Request $request, string $category): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage'), 403);
        $d = $request->validate(['prepRouteId' => ['nullable', 'uuid']]);
        $this->api->request('POST', "catalog/categories/{$category}/prep-route", [], ['prepRouteId' => $d['prepRouteId'] ?? null, 'applyToProducts' => $request->boolean('applyToProducts')]);

        return redirect()->route('setup.catalog', ['tab' => 'categories'])->with('success', $request->boolean('applyToProducts') ? 'Applied to every product in the category.' : 'Saved.');
    }

    public function taxRate(Request $request, ?string $rate = null): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage'), 403);
        $d = $request->validate(['code' => [$rate ? 'nullable' : 'required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/'], 'name' => ['required', 'string', 'max:80'], 'ratePercent' => ['required', 'regex:/^\d{1,3}(\.\d{1,4})?$/']]);
        $body = ['name' => $d['name'], 'ratePercent' => $d['ratePercent'], 'active' => $request->boolean('active', true)];
        $rate ? $this->api->request('PATCH', "catalog/tax-rates/{$rate}", [], $body) : $this->api->request('POST', 'catalog/tax-rates', [], ['code' => strtoupper($d['code'])] + $body);

        return redirect()->route('setup.catalog', ['tab' => 'tax'])->with('success', 'Tax rate saved. It applies to new orders only.');
    }

    public function priceList(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('pricing.manage'), 403);
        $d = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $this->api->request('POST', 'catalog/price-lists', [], ['name' => $d['name'], 'currency' => 'NGN', 'isDefault' => $request->boolean('isDefault'), 'active' => true]);

        return redirect()->route('setup.catalog', ['tab' => 'prices'])->with('success', 'Price list created.');
    }

    // ---- CSV export / import ------------------------------------------------------------------------------------------------

    public function export(string $kind)
    {
        abort_unless(in_array($kind, ['products', 'prices'], true), 404);
        abort_unless($this->staff->can($kind === 'prices' ? 'pricing.manage' : 'catalog.manage'), 403);
        $r = $this->api->raw('GET', "catalog/{$kind}/export");

        return response($r['body'], 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$kind.'-'.now()->format('Y-m-d').'.csv"']);
    }

    /** Step 1 (dry run: nothing is written) and step 2 (apply the same file). The file is kept in the session between the two. */
    public function import(Request $request, string $kind): RedirectResponse
    {
        abort_unless(in_array($kind, ['products', 'prices'], true), 404);
        abort_unless($this->staff->can($kind === 'prices' ? 'pricing.manage' : 'catalog.manage'), 403);
        $apply = $request->input('mode') === 'apply';
        if ($apply) {
            $csv = (string) session("import.{$kind}.csv", '');
        } else {
            $request->validate(['file' => ['nullable', 'file', 'max:2048'], 'csv' => ['nullable', 'string', 'max:2100000']]);
            $csv = $request->hasFile('file') ? (string) file_get_contents($request->file('file')->getRealPath()) : (string) $request->input('csv', '');
        }
        if (trim($csv) === '') {
            return redirect()->route('setup.catalog', ['tab' => 'import'])->with('error', $apply ? 'The file is no longer available. Upload it again.' : 'Choose a CSV file or paste its contents first.');
        }
        try {
            $r = $this->api->raw('POST', "catalog/{$kind}/import", ['dryRun' => $apply ? 'false' : 'true'], $csv);
            $report = (array) json_decode($r['body'], true);
        } catch (R007ApiException $e) {
            if ($e->problemCode() !== 'import_validation_failed') {
                throw $e;
            }
            $report = $e->extensions + ['dryRun' => ! $apply, 'applied' => false];
        }
        session()->put("import.{$kind}", ['csv' => $apply ? null : $csv, 'report' => $report, 'applied' => $apply && ($report['applied'] ?? false)]);

        return redirect()->route('setup.catalog', ['tab' => 'import', 'done' => $kind])->with($apply && ($report['applied'] ?? false) ? 'success' : 'status', $apply && ($report['applied'] ?? false) ? 'Import applied.' : 'Checked. Nothing has been changed yet.');
    }

    /** @return array<string, string> */
    private function ifMatch(?string $etag): array
    {
        $etag = trim((string) $etag);

        return $etag !== '' ? ['If-Match' => $etag] : [];
    }
}
