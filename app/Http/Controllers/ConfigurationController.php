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
        $categories = Fetch::of(fn () => $this->api->get('catalog/categories', ['limit' => 200]), ['GET', '/catalog/categories']);
        $products = Fetch::of(fn () => $this->api->get('catalog/products', ['limit' => 200, 'facilityId' => $request->query('facility')]), ['GET', '/catalog/products']);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $facilityId = $request->query('facility') ?: ($dash->flatten($tree->items())[0]['id'] ?? null);
        $avail = $facilityId ? Fetch::of(fn () => $this->api->get('catalog/availability', ['facilityId' => $facilityId, 'limit' => 200]), ['GET', '/catalog/availability']) : new Fetch(null, 'pending');
        $availability = [];
        foreach ($avail->items() as $a) {
            $availability[$a['productId']] = $a['available'];
        }
        $catNames = [];
        foreach ($categories->items() as $c) {
            $catNames[$c['id']] = $c['name'];
        }

        return view('pages.config.catalog', ['categories' => $categories, 'products' => $products, 'facilities' => $dash->flatten($tree->items()), 'facilityId' => $facilityId, 'availability' => $availability, 'avail' => $avail, 'catNames' => $catNames]);
    }

    public function setAvailability(Request $request, string $product): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.availability.manage'), 403);
        $d = $request->validate(['facilityId' => ['required', 'uuid'], 'available' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:200']]);
        $this->api->request('PUT', "catalog/products/{$product}/availability/{$d['facilityId']}", [], array_filter(['available' => (bool) $d['available'], 'reason' => $d['reason'] ?? null], fn ($v) => $v !== null));

        return redirect()->route('config.catalog', ['facility' => $d['facilityId']])->with('success', $d['available'] ? 'Item is available again.' : 'Item marked unavailable (86\'d) at this facility.');
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
            'name' => ['required', 'string', 'max:120'], 'durationDays' => ['required', 'integer', 'min:1', 'max:3650'], 'price' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'visitLimit' => ['nullable', 'integer', 'min:1'], 'facilityIds' => ['nullable', 'array'], 'facilityIds.*' => ['uuid'],
        ]);
        $body = [
            'name' => $d['name'], 'durationDays' => (int) $d['durationDays'], 'price' => $d['price'], 'visitLimit' => isset($d['visitLimit']) && $d['visitLimit'] !== null ? (int) $d['visitLimit'] : null,
            'facilityIds' => $d['facilityIds'] ?? [], 'propertyWide' => empty($d['facilityIds']), 'active' => $request->boolean('active', true),
        ];
        $plan ? $this->api->request('PATCH', "memberships/plans/{$plan}", [], $body) : $this->api->request('POST', 'memberships/plans', [], $body);

        return redirect()->route('config.memberships')->with('success', $plan ? 'Plan updated.' : 'Plan created.');
    }

    public function bookings(DashboardData $dash)
    {
        $resources = Fetch::of(fn () => $this->api->get('bookings/resources', ['limit' => 100]), ['GET', '/bookings/resources']);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $names = [];
        foreach ($dash->flatten($tree->items()) as $f) {
            $names[$f['id']] = $f['name'];
        }

        return view('pages.config.bookings', ['resources' => $resources, 'facilityNames' => $names, 'canWrite' => Contract::has(...self::RESOURCE_WRITE)]);
    }

    public function updateResource(Request $request, string $resource): RedirectResponse
    {
        abort_unless(Contract::has(...self::RESOURCE_WRITE), 404);
        abort_unless($this->staff->canAny('facility.configure', 'config.manage'), 403);
        $d = $request->validate([
            'offlineAllocationStrategy' => ['required', 'in:A,B,C'], 'offlineReserveCapacity' => ['nullable', 'integer', 'min:0'], 'onlineStalenessThresholdSeconds' => ['nullable', 'integer', 'min:60'],
        ]);
        $this->api->request('PATCH', "bookings/resources/{$resource}", [], array_filter($d, fn ($v) => $v !== null && $v !== ''));

        return redirect()->route('config.bookings')->with('success', 'Booking rules saved.');
    }

    public function tickets()
    {
        return view('pages.config.pending', ['title' => 'Ticket types', 'intro' => 'Ticket and entitlement types (pool day pass, sports entry, rentals) with validity, validation mode and pricing.', 'items' => [
            'List / create / edit ticket types (no /ticket-types endpoint in the contract)',
            'Validation mode ENTRY vs ENTRY_EXIT and validity windows as editable rules',
            'Note: issuing and redeeming entitlements exists (/entitlements) but is an on-site operation, not admin configuration.',
        ]]);
    }

    public function kds()
    {
        $stations = Fetch::of(fn () => $this->api->get('kds/stations', ['limit' => 100]), ['GET', '/kds/stations']);
        $products = Fetch::of(fn () => $this->api->get('catalog/products', ['limit' => 200]), ['GET', '/catalog/products']);
        $byStation = [];
        foreach ($products->items() as $p) {
            $byStation[$p['prepRoute']['stationName'] ?? ($p['prepRoute']['kind'] ?? 'NONE')][] = $p['name'];
        }

        return view('pages.config.kds', ['stations' => $stations, 'products' => $products, 'byStation' => $byStation]);
    }

    public function payments(DashboardData $dash)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $rules = [];
        foreach (array_slice($dash->flatten($tree->items()), 0, 12) as $f) {
            $c = Fetch::of(fn () => $this->api->get("facilities/{$f['id']}/capabilities"), ['GET', '/facilities/{facilityId}/capabilities']);
            $c->ok() && $rules[] = ['facility' => $f, 'rules' => $c->data['operatingRules'] ?? []];
        }

        return view('pages.config.payments', ['tree' => $tree, 'rules' => $rules]);
    }
}
