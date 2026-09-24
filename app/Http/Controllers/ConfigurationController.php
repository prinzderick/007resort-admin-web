<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Support\Contract;
use App\Support\Fetch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Configuration: what the API contract lets an admin change is editable here
 * (tax/VAT per ADR-0011, membership plans, product availability, ...). What it
 * only lets us READ is shown read-only, and what it does not cover yet is
 * listed as pending. Writes that a later contract may add are wired behind
 * Contract::has() so they light up without a rewrite.
 */
class ConfigurationController extends Controller
{
    /** Endpoint keys a future contract is expected to add; forms appear only when present. */
    public const RULES_WRITE = ['PUT', '/facilities/{facilityId}/capabilities'];

    public const RESOURCE_WRITE = ['PATCH', '/bookings/resources/{resourceId}'];

    /** Booking Authority strategies as the API names them (authority.offlineStrategy). */
    public const STRATEGIES = ['A_OFFLINE_ALLOCATION' => 'A: offline allocation', 'B_ONLINE_AUTHORITY_REQUIRED' => 'B: online authority required', 'C_DISABLE_ONLINE' => 'C: pause online availability'];

    public function index()
    {
        return view('pages.config.index', ['contract' => Contract::version()]);
    }

    public function facilities(Request $request, DashboardData $dash)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        $selected = $request->query('facility');
        $detail = null;
        $points = null;

        if ($selected) {
            $detail = Fetch::of(fn () => $this->api->get("facilities/{$selected}/capabilities"), ['GET', '/facilities/{facilityId}/capabilities']);
            $points = Fetch::of(fn () => $this->api->get("organization/facilities/{$selected}/operating-points"), ['GET', '/organization/facilities/{facilityId}/operating-points']);
        }

        return view('pages.config.facilities', ['tree' => $tree, 'flat' => $flat, 'selected' => $selected, 'detail' => $detail, 'points' => $points, 'canWrite' => Contract::has(...self::RULES_WRITE)]);
    }

    public function updateRules(Request $request, string $facility): RedirectResponse
    {
        abort_unless(Contract::has(...self::RULES_WRITE), 404);
        abort_unless($this->staff->canAny('facility.configure', 'config.manage'), 403);
        $d = $request->validate([
            'approvalThresholdAmount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'], 'allowOpenTabs' => ['nullable', 'boolean'], 'requireCashSession' => ['nullable', 'boolean'],
            'allowOfflineOrders' => ['nullable', 'boolean'], 'allowOfflinePayments' => ['required', 'in:NONE,CASH_ONLY,ALL'], 'paymentTiming' => ['nullable', 'in:PAY_BEFORE,PAY_ON_EXIT,PAY_LATER'],
        ]);
        $rules = array_filter([
            'approvalThresholdAmount' => $d['approvalThresholdAmount'], 'allowOpenTabs' => $request->boolean('allowOpenTabs'), 'requireCashSession' => $request->boolean('requireCashSession'),
            'allowOfflineOrders' => $request->boolean('allowOfflineOrders'), 'allowOfflinePayments' => $d['allowOfflinePayments'], 'paymentTiming' => $d['paymentTiming'] ?? null,
        ], fn ($v) => $v !== null);
        $res = $this->api->request('PUT', "facilities/{$facility}/capabilities", [], ['operatingRules' => $rules]);

        return $this->done($res, 'config.facilities', 'Operating rules saved.', 'The rule change was accepted but needs approval.', ['facility' => $facility]);
    }

    public function catalog(Request $request, DashboardData $dash)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        // Products are listed per facility (facilityId is required by the API). Default to the first one that sells things.
        $sells = array_values(array_filter($flat, fn ($f) => in_array('POS', (array) ($f['capabilities'] ?? []), true)));
        $facilityId = $request->query('facility') ?: (($sells[0] ?? $flat[0] ?? [])['id'] ?? null);

        $categories = $this->all('catalog/categories', ['includeInactive' => 1], ['GET', '/catalog/categories']);
        $products = $facilityId ? $this->all('catalog/products', ['facilityId' => $facilityId, 'includeInactive' => 1], ['GET', '/catalog/products']) : new Fetch(null, 'pending');
        $avail = $facilityId ? $this->all('catalog/availability', ['facilityId' => $facilityId], ['GET', '/catalog/availability']) : new Fetch(null, 'pending');
        $prepRoutes = Fetch::of(fn () => $this->api->get('catalog/prep-routes'), ['GET', '/catalog/prep-routes']);
        $taxRates = Fetch::of(fn () => $this->api->get('catalog/tax-rates'), ['GET', '/catalog/tax-rates']);
        $availability = [];
        foreach ($avail->items() as $a) {
            $availability[$a['productId'] ?? ''] = (bool) ($a['available'] ?? true);
        }
        $catNames = [];
        foreach ($categories->items() as $c) {
            $catNames[$c['id'] ?? ''] = $c['name'] ?? '';
        }
        $edit = $request->query('edit');

        return view('pages.config.catalog', [
            'categories' => $categories, 'products' => $products, 'facilities' => $flat, 'facilityId' => $facilityId, 'availability' => $availability, 'avail' => $avail, 'catNames' => $catNames,
            'prepRoutes' => $prepRoutes, 'taxRates' => $taxRates, 'edit' => $edit ? collect($products->items())->firstWhere('id', $edit) : null,
            'canManage' => Contract::has('POST', '/catalog/products') && $this->staff->can('catalog.manage'),
            'canPrice' => Contract::has('PUT', '/catalog/products/{id}/price') && $this->staff->can('pricing.manage'),
        ]);
    }

    public function setAvailability(Request $request, string $product): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.availability.manage'), 403);
        $d = $request->validate(['facilityId' => ['required', 'uuid'], 'available' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:120']]);
        $this->api->request('PUT', "catalog/products/{$product}/availability/{$d['facilityId']}", [], array_filter(['available' => (bool) $d['available'], 'reason' => $d['reason'] ?? null], fn ($v) => $v !== null));

        return redirect()->route('config.catalog', ['facility' => $d['facilityId']])->with('success', $d['available'] ? 'Item is available again.' : 'Item marked unavailable (86\'d) at this facility.');
    }

    public function createProduct(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage') && Contract::has('POST', '/catalog/products'), 403);
        $d = $request->validate([
            'sku' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:200'], 'categoryId' => ['required', 'uuid'],
            'kind' => ['required', 'in:GOOD,SERVICE,TICKET,RENTAL,MEMBERSHIP,FEE'], 'price' => ['required', 'regex:/^\d{1,15}(\.\d{1,4})?$/'],
            'prepRouteId' => ['nullable', 'uuid'], 'taxRateId' => ['nullable', 'uuid'], 'facilityId' => ['nullable', 'uuid'],
        ]);
        $body = ['sku' => $d['sku'], 'name' => $d['name'], 'categoryId' => $d['categoryId'], 'kind' => $d['kind'], 'price' => $d['price'],
            'taxExempt' => $request->boolean('taxExempt'), 'trackStock' => $request->boolean('trackStock')]
            + array_filter(['prepRouteId' => $d['prepRouteId'] ?? null, 'taxRateId' => $d['taxRateId'] ?? null, 'facilityIds' => ! empty($d['facilityId']) ? [$d['facilityId']] : null], fn ($v) => $v !== null && $v !== '');
        $this->api->request('POST', 'catalog/products', [], $body);

        return redirect()->route('config.catalog', array_filter(['facility' => $d['facilityId'] ?? null]))->with('success', 'Product created.');
    }

    public function updateProduct(Request $request, string $product): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage') && Contract::has('PATCH', '/catalog/products/{id}'), 403);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:200'], 'categoryId' => ['required', 'uuid'], 'kind' => ['required', 'in:GOOD,SERVICE,TICKET,RENTAL,MEMBERSHIP,FEE'],
            'prepRouteId' => ['nullable', 'uuid'], 'taxRateId' => ['nullable', 'uuid'], 'facilityId' => ['nullable', 'uuid'],
        ]);
        // Blank prep route / tax rate means "leave as is" (the product read model does not return their ids, so the form cannot preselect them).
        $body = ['name' => $d['name'], 'categoryId' => $d['categoryId'], 'kind' => $d['kind'],
            'taxExempt' => $request->boolean('taxExempt'), 'trackStock' => $request->boolean('trackStock'), 'active' => $request->boolean('active')]
            + array_filter(['prepRouteId' => $d['prepRouteId'] ?? null, 'taxRateId' => $d['taxRateId'] ?? null], fn ($v) => $v !== null && $v !== '');
        $this->api->request('PATCH', "catalog/products/{$product}", [], $body);

        return redirect()->route('config.catalog', array_filter(['facility' => $d['facilityId'] ?? null]))->with('success', 'Product saved.');
    }

    public function setPrice(Request $request, string $product): RedirectResponse
    {
        abort_unless($this->staff->can('pricing.manage') && Contract::has('PUT', '/catalog/products/{id}/price'), 403);
        $d = $request->validate(['amount' => ['required', 'regex:/^\d{1,15}(\.\d{1,4})?$/'], 'facilityId' => ['nullable', 'uuid']]);
        $this->api->request('PUT', "catalog/products/{$product}/price", [], array_filter(['amount' => $d['amount'], 'facilityId' => $request->boolean('onlyHere') ? ($d['facilityId'] ?? null) : null], fn ($v) => $v !== null));

        return redirect()->route('config.catalog', array_filter(['facility' => $d['facilityId'] ?? null]))->with('success', 'Price updated. New orders use it straight away; open orders keep their price.');
    }

    public function createCategory(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage') && Contract::has('POST', '/catalog/categories'), 403);
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'sortOrder' => ['nullable', 'integer'], 'facilityId' => ['nullable', 'uuid']]);
        $this->api->request('POST', 'catalog/categories', [], array_filter(['name' => $d['name'], 'sortOrder' => isset($d['sortOrder']) ? (int) $d['sortOrder'] : null], fn ($v) => $v !== null));

        return redirect()->route('config.catalog', array_filter(['facility' => $d['facilityId'] ?? null]))->with('success', 'Category created.');
    }

    public function tax()
    {
        $res = Fetch::of(fn () => $this->api->request('GET', 'admin/settings/tax'), ['GET', '/admin/settings/tax']);
        $r = $res->ok() ? $res->data : null;

        return view('pages.config.tax', ['setting' => $r ? new Fetch($r->body) : $res, 'etag' => $r?->etag()]);
    }

    public function updateTax(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'vatRatePercent' => ['required', 'regex:/^\d{1,2}(\.\d{1,4})?$/'], 'vatNumber' => ['nullable', 'string', 'max:64'], 'etag' => ['nullable', 'string', 'max:100'],
        ]);
        $body = ['vatEnabled' => $request->boolean('vatEnabled'), 'vatRatePercent' => $d['vatRatePercent'], 'pricesTaxInclusive' => $request->boolean('pricesTaxInclusive')];
        ! empty($d['vatNumber']) && $body['vatNumber'] = $d['vatNumber'];
        $this->api->request('PUT', 'admin/settings/tax', [], $body, ! empty($d['etag']) ? ['If-Match' => $d['etag']] : []);

        return redirect()->route('config.tax')->with('success', $body['vatEnabled'] ? 'VAT is now ON for new receipts and reports.' : 'VAT is OFF. Receipts show gross totals only.');
    }

    public function memberships(Request $request, DashboardData $dash)
    {
        $plans = Fetch::of(fn () => $this->api->get('memberships/plans', ['limit' => 100]), ['GET', '/memberships/plans']);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);

        return view('pages.config.memberships', ['plans' => $plans, 'facilities' => $dash->flatten($tree->items()), 'edit' => $request->query('edit')]);
    }

    public function savePlan(Request $request, ?string $plan = null): RedirectResponse
    {
        abort_unless($this->staff->can('membership.plan.manage'), 403);
        $d = $request->validate([
            'code' => [$plan ? 'nullable' : 'required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'], 'durationDays' => ['required', 'integer', 'min:1', 'max:3650'],
            'price' => ['required', 'regex:/^\d{1,15}(\.\d{1,4})?$/'], 'visitLimit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'guestAllowance' => ['nullable', 'integer', 'min:0', 'max:50'], 'memberDiscountPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bookingAdvanceDays' => ['nullable', 'integer', 'min:0', 'max:365'], 'gracePeriodDays' => ['nullable', 'integer', 'min:0', 'max:365'], 'renewalNoticeDays' => ['nullable', 'integer', 'min:0', 'max:365'],
            'facilityIds' => ['nullable', 'array'], 'facilityIds.*' => ['uuid'],
        ]);
        $body = [
            'name' => $d['name'], 'durationDays' => (int) $d['durationDays'], 'price' => $d['price'], 'visitLimit' => isset($d['visitLimit']) ? (int) $d['visitLimit'] : null,
            'facilityIds' => $d['facilityIds'] ?? [], 'propertyWide' => empty($d['facilityIds']), 'active' => $request->boolean('active', true),
        ];
        foreach (['guestAllowance', 'bookingAdvanceDays', 'gracePeriodDays', 'renewalNoticeDays'] as $k) {
            isset($d[$k]) && $body[$k] = (int) $d[$k];
        }
        isset($d['memberDiscountPercent']) && $body['memberDiscountPercent'] = (float) $d['memberDiscountPercent'];
        array_key_exists('description', $d) && $body['description'] = $d['description'];
        if ($plan) {
            $this->api->request('PATCH', "memberships/plans/{$plan}", [], $body);
        } else {
            $this->api->request('POST', 'memberships/plans', [], ['code' => strtoupper($d['code'])] + $body);
        }

        return redirect()->route('config.memberships')->with('success', $plan ? 'Plan updated.' : 'Plan created.');
    }

    public function bookings(DashboardData $dash)
    {
        $resources = $this->all('bookings/resources', [], ['GET', '/bookings/resources']);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $names = [];
        foreach ($dash->flatten($tree->items()) as $f) {
            $names[$f['id'] ?? ''] = $f['name'] ?? '';
        }

        return view('pages.config.bookings', ['resources' => $resources, 'facilityNames' => $names, 'canWrite' => Contract::has(...self::RESOURCE_WRITE) && $this->staff->can('booking.configure')]);
    }

    /** PATCH /bookings/resources/{id} (booking.configure): rules + the Booking Authority (offline-allocation) strategy. */
    public function updateResource(Request $request, string $resource): RedirectResponse
    {
        abort_unless(Contract::has(...self::RESOURCE_WRITE), 404);
        abort_unless($this->staff->can('booking.configure'), 403);
        $d = $request->validate([
            'offlineStrategy' => ['required', 'in:'.implode(',', array_keys(self::STRATEGIES))], 'localReserveUnits' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'onlineStaleAfterSeconds' => ['nullable', 'integer', 'min:30', 'max:604800'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'], 'slotMinutes' => ['nullable', 'integer', 'min:5', 'max:1440'], 'maxSlotsPerBooking' => ['nullable', 'integer', 'min:1', 'max:48'],
            'price' => ['nullable', 'regex:/^\d+(\.\d{1,4})?$/'],
        ]);
        $authority = ['offlineStrategy' => $d['offlineStrategy']];
        isset($d['localReserveUnits']) && $authority['localReserveUnits'] = (int) $d['localReserveUnits'];
        isset($d['onlineStaleAfterSeconds']) && $authority['onlineStaleAfterSeconds'] = (int) $d['onlineStaleAfterSeconds'];
        $body = ['authority' => $authority, 'onlineBookable' => $request->boolean('onlineBookable'), 'active' => $request->boolean('active', true)];
        foreach (['capacity', 'slotMinutes', 'maxSlotsPerBooking'] as $k) {
            isset($d[$k]) && $body[$k] = (int) $d[$k];
        }
        isset($d['price']) && $body['price'] = $d['price'];
        $this->api->request('PATCH', "bookings/resources/{$resource}", [], $body);

        return redirect()->route('config.bookings')->with('success', 'Booking rules saved.');
    }

    /** Ticket types have no endpoint; what the API does expose is the entitlements issued from them (read-only). */
    public function tickets(Request $request)
    {
        $entitlements = Fetch::of(fn () => $this->api->get('entitlements', ['limit' => 100, 'cursor' => $request->query('cursor')]), ['GET', '/entitlements']);

        return view('pages.config.tickets', ['entitlements' => $entitlements, 'typesEndpoint' => Contract::has('GET', '/ticket-types')]);
    }

    public function kds(DashboardData $dash)
    {
        $stations = Fetch::of(fn () => $this->api->get('kds/stations', ['limit' => 100]), ['GET', '/kds/stations']);
        $routes = Fetch::of(fn () => $this->api->get('catalog/prep-routes'), ['GET', '/catalog/prep-routes']);
        $byStation = [];
        $productsFetch = new Fetch(['items' => []]);
        // Products are read per facility, so read the facilities that have stations.
        $facilityIds = array_values(array_unique(array_filter(array_map(fn ($s) => $s['facilityId'] ?? null, $stations->items()))));
        foreach (array_slice($facilityIds, 0, 12) as $fid) {
            $p = $this->all('catalog/products', ['facilityId' => $fid], ['GET', '/catalog/products'], 2);
            if (! $p->ok()) {
                $productsFetch = $p;

                continue;
            }
            foreach ($p->items() as $prod) {
                $route = $prod['prepRoute'] ?? null;
                $key = is_array($route) ? ($route['stationName'] ?? ($route['kind'] ?? 'NONE')) : 'NONE';
                $byStation[$key][$prod['id'] ?? $prod['name'] ?? ''] = $prod['name'] ?? '';
            }
        }

        return view('pages.config.kds', ['stations' => $stations, 'routes' => $routes, 'products' => $productsFetch, 'byStation' => array_map('array_values', $byStation)]);
    }

    public function payments(DashboardData $dash)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $rules = [];
        // Only facilities that take payments have rules worth showing.
        $taking = array_values(array_filter($dash->flatten($tree->items()), fn ($f) => in_array('PAYMENT_ACCEPTANCE', (array) ($f['capabilities'] ?? []), true)));
        foreach (array_slice($taking, 0, 40) as $f) {
            $c = Fetch::of(fn () => $this->api->get("facilities/{$f['id']}/capabilities"), ['GET', '/facilities/{facilityId}/capabilities']);
            $c->ok() && $rules[] = ['facility' => $f, 'rules' => $c->data['operatingRules'] ?? []];
        }

        return view('pages.config.payments', ['tree' => $tree, 'rules' => $rules]);
    }
}
