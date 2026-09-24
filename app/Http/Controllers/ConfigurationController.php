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
            'price' => ['required', 'regex:/^\d{1,15}(\.\d{1,4})?$/'], 'visitLimit' => ['nullable', 'integer', 'min:1', 'max:100000'], '_scope' => ['nullable', 'in:ALL,SOME'],
            'guestAllowance' => ['nullable', 'integer', 'min:0', 'max:50'], 'memberDiscountPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bookingAdvanceDays' => ['nullable', 'integer', 'min:0', 'max:365'], 'gracePeriodDays' => ['nullable', 'integer', 'min:0', 'max:365'], 'renewalNoticeDays' => ['nullable', 'integer', 'min:0', 'max:365'],
            'facilityIds' => ['nullable', 'array'], 'facilityIds.*' => ['uuid'],
        ]);
        $body = [
            'name' => $d['name'], 'durationDays' => (int) $d['durationDays'], 'price' => $d['price'], 'visitLimit' => isset($d['visitLimit']) ? (int) $d['visitLimit'] : null,
            'facilityIds' => $request->input('_scope') === 'SOME' ? ($d['facilityIds'] ?? []) : [], 'propertyWide' => $request->input('_scope') !== 'SOME' || empty($d['facilityIds']), 'active' => $request->boolean('active', true),
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

    public const VALIDATION_MODES = [
        'SINGLE_USE' => ['One entry', 'Scanned once, then used up. Day passes and event tickets.', 'check'],
        'MULTIPLE_ENTRY' => ['Several entries', 'Can be scanned many times while valid.', 'refresh'],
        'ENTRY_EXIT' => ['In and out tracked', 'Each scan toggles inside/outside. Pools and gyms.', 'layers'],
        'TIME_LIMITED' => ['Time limited', 'Valid for a set time from the first scan.', 'clock'],
        'STAFF_APPROVAL' => ['Staff approves', 'A staff member decides at the gate.', 'user'],
        'NONE' => ['No scan needed', 'Just a receipt, nothing is checked.', 'ban'],
    ];

    public const VALIDITY_KINDS = ['ISSUE_DAY' => 'Valid on the day it is sold', 'DURATION_MINUTES' => 'Valid for a set time', 'BOOKING_SLOT' => 'Valid for the booked slot'];

    /** Ticket types (what can be sold and how it is scanned) and, second, the entitlements already issued from them. */
    public function tickets(Request $request, DashboardData $dash)
    {
        $tab = $request->query('tab', 'types') === 'issued' ? 'issued' : 'types';
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        $types = Fetch::of(fn () => $this->api->get('ticketing/ticket-types', ['limit' => 200]), ['GET', '/ticketing/ticket-types']);
        $entitlements = $tab === 'issued' ? Fetch::of(fn () => $this->api->get('entitlements', ['limit' => 100, 'cursor' => $request->query('cursor')]), ['GET', '/entitlements']) : new Fetch(['items' => []]);

        return view('pages.setup.tickets', [
            'tab' => $tab, 'types' => $types, 'entitlements' => $entitlements, 'facilities' => $flat, 'modes' => self::VALIDATION_MODES, 'validityKinds' => self::VALIDITY_KINDS,
            'canManage' => $this->staff->can('ticket_type.manage'),
            'ticketFacilities' => array_values(array_filter($flat, fn ($f) => array_intersect(['TICKETING', 'TICKET_VALIDATION'], (array) ($f['capabilities'] ?? [])) !== [])),
        ]);
    }

    public function saveTicketType(Request $request, ?string $type = null): RedirectResponse
    {
        abort_unless($this->staff->can('ticket_type.manage'), 403);
        $d = $request->validate([
            'code' => [$type ? 'nullable' : 'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'], 'name' => ['required', 'string', 'max:120'], 'facilityId' => [$type ? 'nullable' : 'required', 'uuid'],
            'format' => ['required', 'in:INDIVIDUAL,COMBINED'], 'validationMode' => ['required', 'in:'.implode(',', array_keys(self::VALIDATION_MODES))],
            'validityKind' => ['required', 'in:'.implode(',', array_keys(self::VALIDITY_KINDS))], 'validityMinutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'earlyEntryMinutes' => ['nullable', 'integer', 'min:0', 'max:240'], 'price' => ['nullable', 'regex:/^\d{1,15}(\.\d{1,4})?$/'], 'etag' => ['nullable', 'string', 'max:40'],
        ]);
        $body = ['name' => $d['name'], 'format' => $d['format'], 'validationMode' => $d['validationMode'], 'validityKind' => $d['validityKind'],
            'validityMinutes' => $d['validityKind'] === 'DURATION_MINUTES' ? (int) ($d['validityMinutes'] ?? 0) : null, 'earlyEntryMinutes' => (int) ($d['earlyEntryMinutes'] ?? 0), 'active' => $request->boolean('active', true)];
        if (($d['price'] ?? '') !== '') {
            $body['price'] = $d['price'];
        }
        if ($type) {
            $this->api->request('PATCH', "ticketing/ticket-types/{$type}", [], $body, $this->ifMatch($d['etag'] ?? null));
        } else {
            $this->api->request('POST', 'ticketing/ticket-types', [], ['code' => strtoupper($d['code']), 'facilityId' => $d['facilityId']] + $body);
        }

        return redirect()->route('setup.tickets')->with('success', $type ? 'Ticket type saved.' : 'Ticket type created. It can be sold at the facility straight away.');
    }

    /** Kitchen & bar routing: which screen prepares each kind of order at a facility, and the routes themselves. */
    public function kds(Request $request, DashboardData $dash)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        $selling = array_values(array_filter($flat, fn ($f) => in_array('POS', (array) ($f['capabilities'] ?? []), true)));
        $facilityId = (string) ($request->query('facility') ?: ($selling[0]['id'] ?? ''));
        $routes = Fetch::of(fn () => $this->api->get('catalog/prep-routes'), ['GET', '/catalog/prep-routes']);
        $stations = $this->all('organization/operating-points', ['kind' => 'STATION'], ['GET', '/organization/operating-points'], 2);
        $map = $facilityId !== '' ? Fetch::of(fn () => $this->api->get('catalog/prep-route-stations', ['facilityId' => $facilityId]), ['GET', '/catalog/prep-route-stations']) : new Fetch(['items' => []]);
        $names = collect($flat)->pluck('name', 'id')->all();

        return view('pages.setup.kds', [
            'facilities' => $selling, 'facilityId' => $facilityId, 'routes' => $routes, 'stations' => $stations, 'map' => collect($map->items())->pluck('kdsStationId', 'prepRouteId')->all(), 'mapFetch' => $map,
            'facilityNames' => $names, 'canManage' => $this->staff->canAny('catalog.manage', 'facility.manage'),
            'stationOptions' => collect($stations->items())->where('active', true)->map(fn ($p) => ['value' => $p['id'], 'label' => $p['name'].' ('.($names[$p['facilityId']] ?? '').')'])->values()->all(),
        ]);
    }

    public function saveRouting(Request $request): RedirectResponse
    {
        abort_unless($this->staff->canAny('catalog.manage', 'facility.manage'), 403);
        $d = $request->validate(['facilityId' => ['required', 'uuid'], 'stations' => ['nullable', 'array'], 'stations.*' => ['nullable', 'uuid']]);
        foreach ((array) ($d['stations'] ?? []) as $routeId => $stationId) {
            $this->api->request('PUT', 'catalog/prep-route-stations', [], ['facilityId' => $d['facilityId'], 'prepRouteId' => $routeId, 'kdsStationId' => $stationId ?: null]);
        }

        return redirect()->route('setup.kds', ['facility' => $d['facilityId']])->with('success', 'Routing saved. New orders at this facility go to the screens you chose.');
    }

    public function saveRoute(Request $request, ?string $route = null): RedirectResponse
    {
        abort_unless($this->staff->can('catalog.manage'), 403);
        $d = $request->validate(['code' => [$route ? 'nullable' : 'required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/'], 'name' => ['required', 'string', 'max:80'], 'kind' => ['required', 'in:KITCHEN,BAR,NONE']]);
        $route ? $this->api->request('PATCH', "catalog/prep-routes/{$route}", [], ['name' => $d['name'], 'kind' => $d['kind']]) : $this->api->request('POST', 'catalog/prep-routes', [], ['code' => strtoupper($d['code']), 'name' => $d['name'], 'kind' => $d['kind']]);

        return redirect()->route('setup.kds')->with('success', 'Route saved.');
    }

    /** Payment methods each facility accepts, plus how payment is timed there (read from its rules). */
    public function payments(DashboardData $dash)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $taking = array_values(array_filter($dash->flatten($tree->items()), fn ($f) => in_array('PAYMENT_ACCEPTANCE', (array) ($f['capabilities'] ?? []), true)));
        $rows = [];
        $labels = [];
        foreach (array_slice($taking, 0, 40) as $f) {
            $res = Fetch::of(fn () => $this->api->request('GET', "facilities/{$f['id']}/payment-methods"), ['GET', '/facilities/{facilityId}/payment-methods']);
            $rules = Fetch::of(fn () => $this->api->get("facilities/{$f['id']}/capabilities"), ['GET', '/facilities/{facilityId}/capabilities']);
            if ($res->ok()) {
                $labels = $labels ?: (array) ($res->data->body['labels'] ?? []);
                $rows[] = ['facility' => $f, 'methods' => (array) ($res->data->body['methods'] ?? []), 'version' => $res->data->etag(), 'rules' => $rules->ok() ? (array) ($rules->data['operatingRules'] ?? []) : []];
            }
        }

        return view('pages.setup.payments', ['tree' => $tree, 'rows' => $rows, 'labels' => $labels ?: ['CASH' => 'Cash', 'CARD' => 'Card', 'TRANSFER' => 'Bank transfer', 'POS_TERMINAL' => 'POS terminal', 'PAYSTACK' => 'Paystack (online)'],
            'canEdit' => $this->staff->canAny('settings.manage', 'config.manage')]);
    }

    /** One PUT per facility whose ticks changed. A facility must keep at least one method: the API refuses the rest in plain words. */
    public function savePaymentMethods(Request $request): RedirectResponse
    {
        abort_unless($this->staff->canAny('settings.manage', 'config.manage'), 403);
        $posted = (array) $request->input('methods', []);
        $versions = (array) $request->input('version', []);
        $before = (array) $request->input('before', []);
        $saved = 0;
        foreach ($posted as $facilityId => $methods) {
            $now = collect((array) $methods)->map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN))->all();
            if ($now === collect((array) ($before[$facilityId] ?? []))->map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN))->all()) {
                continue;
            }
            $etag = trim((string) ($versions[$facilityId] ?? ''));
            $this->api->request('PUT', "facilities/{$facilityId}/payment-methods", [], ['methods' => $now], $etag !== '' ? ['If-Match' => $etag] : []);
            $saved++;
        }

        return redirect()->route('setup.payments')->with($saved ? 'success' : 'status', $saved ? ($saved === 1 ? 'Payment methods saved for 1 facility.' : "Payment methods saved for {$saved} facilities.") : 'Nothing changed.');
    }

}
