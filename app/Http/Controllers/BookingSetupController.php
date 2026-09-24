<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Support\Fetch;
use App\Support\Form\Hours;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Setup > Booking resources (docs/CONFIG_ADMIN_API.md section 9): the courts, chairs, rooms and halls that can be booked, what happens to
 * each when the site is offline (Booking Authority strategy A / B / C), their weekly opening windows, blackout dates and per-resource rule overrides.
 */
class BookingSetupController extends Controller
{
    public const STRATEGIES = [
        'A_OFFLINE_ALLOCATION' => ['A. Reserved share', 'The site keeps taking bookings while offline, from a share of the capacity kept back for it. Safe: no double booking.', 'pie'],
        'B_ONLINE_AUTHORITY_REQUIRED' => ['B. Needs the internet', 'Bookings for this resource can only be made while the site is online. Nothing is taken offline.', 'lock'],
        'C_DISABLE_ONLINE' => ['C. Website pauses', 'The website stops offering this resource while the site is offline. Reception is unaffected.', 'ban'],
    ];

    public const MODES = ['TIME_SLOT' => 'Time slots (courts, chairs)', 'WHOLE_RESOURCE' => 'Whole thing (a hall)', 'INDIVIDUAL_CAPACITY' => 'Per person (a class, a pool session)'];

    /** Rule keys a resource may override, and the camelCase name the override endpoint uses. */
    public const OVERRIDES = ['hold_ttl_seconds' => 'holdTtlSeconds', 'min_notice_minutes' => 'minNoticeMinutes', 'max_advance_days' => 'maxAdvanceDays', 'cancel_cutoff_minutes' => 'cancelCutoffMinutes',
        'cancel_fee_percent' => 'cancelFeePercent', 'reschedule_cutoff_minutes' => 'rescheduleCutoffMinutes', 'max_reschedules' => 'maxReschedules', 'early_entry_minutes' => 'earlyEntryMinutes'];

    public function index(DashboardData $dash)
    {
        $resources = $this->all('bookings/resources', [], ['GET', '/bookings/resources']);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());

        return view('pages.setup.bookings', [
            'resources' => $resources, 'facilities' => $flat, 'facilityNames' => collect($flat)->pluck('name', 'id')->all(), 'strategies' => self::STRATEGIES, 'modes' => self::MODES,
            'canWrite' => $this->staff->can('booking.configure'), 'bookable' => array_values(array_filter($flat, fn ($f) => in_array('BOOKING', (array) ($f['capabilities'] ?? []), true))),
        ]);
    }

    public function show(string $resource, DashboardData $dash)
    {
        $res = Fetch::of(fn () => $this->api->get('bookings/resources', ['limit' => 200]), ['GET', '/bookings/resources']);
        $r = collect($res->items())->firstWhere('id', $resource) ?? [];
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        $schedule = Fetch::of(fn () => $this->api->get("bookings/resources/{$resource}/schedule"), ['GET', '/bookings/resources/{resourceId}/schedule']);
        $rules = Fetch::of(fn () => $this->api->get("bookings/resources/{$resource}/rules"), ['GET', '/bookings/resources/{resourceId}/rules']);
        $blackouts = Fetch::of(fn () => $this->api->get("bookings/resources/{$resource}/blackouts", ['from' => now()->subDays(1)->toIso8601String()]), ['GET', '/bookings/resources/{resourceId}/blackouts']);
        $defs = Fetch::of(fn () => $this->api->get('organization/rule-definitions'), ['GET', '/organization/rule-definitions']);

        // Weekly windows (dayOfWeek 1..7 = Mon..Sun) as the weekly-hours control's value.
        $week = ['weekly' => [], 'exceptions' => []];
        foreach (Hours::DAYS as $i => $d) {
            $wins = collect($schedule->ok() ? ($schedule->data['windows'] ?? []) : [])->where('dayOfWeek', $i + 1)->values();
            $week['weekly'][$d] = ['open' => $wins->isNotEmpty(), 'intervals' => $wins->map(fn ($w) => ['from' => $w['open'], 'to' => $w['close']])->all()];
        }
        $effective = $rules->ok() ? (array) ($rules->data['effective'] ?? []) : [];
        $overrides = $rules->ok() ? collect(self::OVERRIDES)->filter(fn ($camel) => ($rules->data[$camel] ?? null) !== null) : collect();
        $ruleDefs = collect($defs->items())->whereIn('key', array_keys(self::OVERRIDES))->values()->all();
        $ruleValues = [];
        foreach (self::OVERRIDES as $key => $camel) {
            $ruleValues[$key] = $rules->ok() ? ($rules->data[$camel] ?? $effective[$camel] ?? null) : null;
        }

        return view('pages.setup.booking-resource', [
            'id' => $resource, 'r' => $r, 'fetch' => $res->ok() ? ($r === [] ? new Fetch(null, 'missing', 'Resource not found.') : new Fetch($r)) : $res, 'facilityName' => collect($flat)->firstWhere('id', $r['facilityId'] ?? null)['name'] ?? '',
            'strategies' => self::STRATEGIES, 'modes' => self::MODES, 'week' => $week, 'schedule' => $schedule, 'rules' => $rules, 'ruleDefs' => $ruleDefs, 'ruleValues' => $ruleValues, 'overrides' => $overrides->keys()->all(),
            'blackouts' => $blackouts, 'canWrite' => $this->staff->can('booking.configure'), 'overrideKeys' => self::OVERRIDES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('booking.configure'), 403);
        $d = $request->validate([
            'facilityId' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:200'], 'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'], 'mode' => ['required', 'in:'.implode(',', array_keys(self::MODES))],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'], 'slotMinutes' => ['nullable', 'integer', 'min:5', 'max:1440'], 'price' => ['nullable', 'regex:/^\d{1,15}(\.\d{1,4})?$/'],
        ]);
        $body = array_filter(['facilityId' => $d['facilityId'], 'name' => $d['name'], 'code' => strtoupper($d['code']), 'mode' => $d['mode'], 'capacity' => isset($d['capacity']) ? (int) $d['capacity'] : null,
            'slotMinutes' => isset($d['slotMinutes']) ? (int) $d['slotMinutes'] : null, 'price' => $d['price'] ?? null, 'onlineBookable' => $request->boolean('onlineBookable')], fn ($v) => $v !== null);
        $res = $this->api->request('POST', 'bookings/resources', [], $body);

        return redirect()->route('setup.bookings.show', $res->body['id'] ?? '')->with('success', 'Resource created. Set its opening windows and offline strategy below.');
    }

    public function update(Request $request, string $resource): RedirectResponse
    {
        abort_unless($this->staff->can('booking.configure'), 403);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:200'], 'offlineStrategy' => ['required', 'in:'.implode(',', array_keys(self::STRATEGIES))], 'reservePercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'onlineStaleAfterSeconds' => ['nullable', 'integer', 'min:30', 'max:604800'], 'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'], 'slotMinutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'maxSlotsPerBooking' => ['nullable', 'integer', 'min:1', 'max:48'], 'price' => ['nullable', 'regex:/^\d{1,15}(\.\d{1,4})?$/'],
        ]);
        $capacity = (int) ($d['capacity'] ?? 1);
        $authority = ['offlineStrategy' => $d['offlineStrategy']];
        if ($d['offlineStrategy'] === 'A_OFFLINE_ALLOCATION' && isset($d['reservePercent'])) {
            $authority['localReserveUnits'] = (int) floor($capacity * (float) $d['reservePercent'] / 100);
        }
        isset($d['onlineStaleAfterSeconds']) && $authority['onlineStaleAfterSeconds'] = (int) $d['onlineStaleAfterSeconds'];
        $body = ['name' => $d['name'], 'authority' => $authority, 'onlineBookable' => $request->boolean('onlineBookable'), 'allowWholeResource' => $request->boolean('allowWholeResource'), 'active' => $request->boolean('active')];
        foreach (['capacity', 'slotMinutes', 'maxSlotsPerBooking'] as $k) {
            isset($d[$k]) && $body[$k] = (int) $d[$k];
        }
        isset($d['price']) && $body['price'] = $d['price'];
        $this->api->request('PATCH', "bookings/resources/{$resource}", [], $body);

        return redirect()->route('setup.bookings.show', $resource)->with('success', 'Booking settings saved.');
    }

    public function schedule(Request $request, string $resource): RedirectResponse
    {
        abort_unless($this->staff->can('booking.configure'), 403);
        $h = Hours::toApi($request->input('hours'));
        $windows = [];
        foreach (Hours::DAYS as $i => $d) {
            foreach ($h['weekly'][$d] as $w) {
                $windows[] = ['dayOfWeek' => $i + 1, 'open' => $w['open'], 'close' => $w['close']];
            }
        }
        $this->api->request('PUT', "bookings/resources/{$resource}/schedule", [], ['windows' => $windows]);

        return redirect()->route('setup.bookings.show', $resource)->with('success', $windows === [] ? 'Opening windows cleared: the property\'s default hours apply.' : 'Opening windows saved.');
    }

    /** Only rules changed from the facility's value become overrides; "Use the facility rules" clears them all. */
    public function rules(Request $request, string $resource): RedirectResponse
    {
        abort_unless($this->staff->can('booking.configure'), 403);
        $current = $this->api->get("bookings/resources/{$resource}/rules");
        $effective = (array) ($current['effective'] ?? []);
        $body = [];
        foreach (self::OVERRIDES as $key => $camel) {
            if ($request->boolean('clear')) {
                $body[$camel] = null;

                continue;
            }
            $raw = $request->input("rules.{$key}");
            if ($raw === null || $raw === '') {
                continue;
            }
            $val = is_numeric($raw) ? $raw + 0 : $raw;
            $body[$camel] = (float) $val === (float) ($effective[$camel] ?? null) && ($current[$camel] ?? null) === null ? null : $val;
        }
        $this->api->request('PUT', "bookings/resources/{$resource}/rules", [], $body);

        return redirect(route('setup.bookings.show', $resource).'#rules')->with('success', $request->boolean('clear') ? 'This resource now follows the facility rules.' : 'Rules saved for this resource.');
    }

    public function blackout(Request $request, string $resource): RedirectResponse
    {
        abort_unless($this->staff->can('booking.configure'), 403);
        $d = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'reason' => ['nullable', 'string', 'max:255']]);
        $tz = config('r007.display_timezone', 'Africa/Lagos');
        $start = CarbonImmutable::parse($d['from'], $tz)->startOfDay()->utc()->toIso8601String();
        $end = CarbonImmutable::parse($d['to'], $tz)->addDay()->startOfDay()->utc()->toIso8601String();
        $this->api->request('POST', "bookings/resources/{$resource}/blackouts", [], array_filter(['start' => $start, 'end' => $end, 'reason' => $d['reason'] ?? null]));

        return redirect()->route('setup.bookings.show', $resource)->with('success', 'Blackout added. Nothing can be booked in that period.');
    }

    public function removeBlackout(Request $request, string $resource, string $blackout): RedirectResponse
    {
        abort_unless($this->staff->can('booking.configure'), 403);
        $this->api->request('DELETE', "bookings/blackouts/{$blackout}");

        return redirect()->route('setup.bookings.show', $resource)->with('success', 'Blackout removed.');
    }
}
