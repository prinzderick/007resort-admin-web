<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Services\Portal\SetupProgress;
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
    public const RESOURCE_WRITE = ['PATCH', '/bookings/resources/{resourceId}'];

    /** Booking Authority strategies as the API names them (authority.offlineStrategy). */
    public const STRATEGIES = ['A_OFFLINE_ALLOCATION' => 'A: offline allocation', 'B_ONLINE_AUTHORITY_REQUIRED' => 'B: online authority required', 'C_DISABLE_ONLINE' => 'C: pause online availability'];

    /** The Setup hub: everything an administrator configures, in plain language, plus how far along the setup is. */
    public function index(SetupProgress $setup)
    {
        return view('pages.setup.index', ['contract' => Contract::version(), 'progress' => $setup->get()]);
    }

    /** Business profile, receipt and VAT: three settings documents, each with its own version (ETag). */
    public function business()
    {
        $one = function (string $path, array $endpoint) {
            $res = Fetch::of(fn () => $this->api->request('GET', $path), $endpoint);

            return [$res->ok() ? new Fetch($res->data->body) : $res, $res->ok() ? $res->data->etag() : null];
        };
        [$business, $businessEtag] = $one('admin/settings/business', ['GET', '/admin/settings/business']);
        [$receipt, $receiptEtag] = $one('admin/settings/receipt', ['GET', '/admin/settings/receipt']);
        [$tax, $taxEtag] = $one('admin/settings/tax', ['GET', '/admin/settings/tax']);
        // Until the settings endpoints answer, the site record still gives a read-only profile.
        $site = $business->ok() ? $business : Fetch::of(fn () => $this->api->get('organization/site'), ['GET', '/organization/site']);

        return view('pages.setup.business', [
            'business' => $business, 'businessEtag' => $businessEtag, 'receipt' => $receipt, 'receiptEtag' => $receiptEtag, 'setting' => $tax, 'etag' => $taxEtag, 'site' => $site,
            'canEdit' => $this->staff->canAny('settings.manage', 'config.manage'), 'canTax' => $this->staff->can('config.manage'),
        ]);
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        abort_unless($this->staff->canAny('settings.manage', 'config.manage'), 403);
        $d = $request->validate([
            'organizationName' => ['required', 'string', 'max:160'], 'siteName' => ['required', 'string', 'max:160'], 'timezone' => ['required', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:300'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:190'], 'etag' => ['nullable', 'string', 'max:40'],
        ]);
        $this->api->request('PUT', 'admin/settings/business', [], array_diff_key($d, ['etag' => 1]) + ['address' => $d['address'] ?? null, 'phone' => $d['phone'] ?? null, 'email' => $d['email'] ?? null], $this->ifMatch($d['etag'] ?? null));

        return redirect()->route('setup.business')->with('success', 'Business profile saved.');
    }

    public function updateReceipt(Request $request): RedirectResponse
    {
        abort_unless($this->staff->canAny('settings.manage', 'config.manage'), 403);
        $d = $request->validate([
            'businessName' => ['required', 'string', 'max:160'], 'address' => ['nullable', 'string', 'max:300'], 'phone' => ['nullable', 'string', 'max:40'],
            'headerNote' => ['nullable', 'string', 'max:300'], 'footer' => ['nullable', 'string', 'max:300'], 'logoUrl' => ['nullable', 'url', 'max:300'],
            'paperColumns' => ['required', 'in:32,48'], 'etag' => ['nullable', 'string', 'max:40'],
        ]);
        $body = ['businessName' => $d['businessName'], 'address' => $d['address'] ?? null, 'phone' => $d['phone'] ?? null, 'headerNote' => $d['headerNote'] ?? null,
            'footer' => $d['footer'] ?? null, 'logoUrl' => $d['logoUrl'] ?? null, 'showTin' => $request->boolean('showTin'), 'paperColumns' => (int) $d['paperColumns']];
        $this->api->request('PUT', 'admin/settings/receipt', [], $body, $this->ifMatch($d['etag'] ?? null));

        return redirect()->route('setup.business')->with('success', 'Receipt settings saved. New receipts use them straight away; reprints keep the receipt that was issued.');
    }

    /** @return array<string, string> */
    private function ifMatch(?string $etag): array
    {
        $etag = trim((string) $etag);

        return $etag !== '' ? ['If-Match' => $etag] : [];
    }

    public function updateTax(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'vatRatePercent' => ['required', 'regex:/^\d{1,3}(\.\d{1,4})?$/'], 'vatNumber' => ['nullable', 'string', 'max:64'], 'etag' => ['nullable', 'string', 'max:100'],
        ]);
        $body = ['vatEnabled' => $request->boolean('vatEnabled'), 'vatRatePercent' => $d['vatRatePercent'], 'pricesTaxInclusive' => $request->boolean('pricesTaxInclusive')];
        ! empty($d['vatNumber']) && $body['vatNumber'] = $d['vatNumber'];
        $this->api->request('PUT', 'admin/settings/tax', [], $body, $this->ifMatch($d['etag'] ?? null));

        return redirect()->route('setup.business')->with('success', $body['vatEnabled'] ? 'VAT is now ON for new receipts and reports.' : 'VAT is OFF. Receipts show gross totals only.');
    }

    public function memberships(Request $request, DashboardData $dash)
    {
        $plans = Fetch::of(fn () => $this->api->get('memberships/plans', ['limit' => 100]), ['GET', '/memberships/plans']);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);

        return view('pages.setup.memberships', ['plans' => $plans, 'facilities' => $dash->flatten($tree->items()), 'edit' => $request->query('edit')]);
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

        return redirect()->route('setup.memberships')->with('success', $plan ? 'Plan updated.' : 'Plan created.');
    }

    public function bookings(DashboardData $dash)
    {
        $resources = $this->all('bookings/resources', [], ['GET', '/bookings/resources']);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $names = [];
        foreach ($dash->flatten($tree->items()) as $f) {
            $names[$f['id'] ?? ''] = $f['name'] ?? '';
        }

        return view('pages.setup.bookings', ['resources' => $resources, 'facilityNames' => $names, 'canWrite' => Contract::has(...self::RESOURCE_WRITE) && $this->staff->can('booking.configure')]);
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

        return redirect()->route('setup.bookings')->with('success', 'Booking rules saved.');
    }

    /** Ticket types have no endpoint; what the API does expose is the entitlements issued from them (read-only). */
    public function tickets(Request $request)
    {
        $entitlements = Fetch::of(fn () => $this->api->get('entitlements', ['limit' => 100, 'cursor' => $request->query('cursor')]), ['GET', '/entitlements']);

        return view('pages.setup.tickets', ['entitlements' => $entitlements, 'typesEndpoint' => Contract::has('GET', '/ticket-types')]);
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

        return view('pages.setup.kds', ['stations' => $stations, 'routes' => $routes, 'products' => $productsFetch, 'byStation' => array_map('array_values', $byStation)]);
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

        return view('pages.setup.payments', ['tree' => $tree, 'rules' => $rules]);
    }
}
