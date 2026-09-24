<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Services\Portal\Directory;
use App\Support\Contract;
use App\Support\Fetch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Setup > Facilities: the centre of configuration. List, add (template wizard), and one edit page with tabs:
 * General, Capabilities, Operating rules (a FORM generated from the API's rule definitions), Operating points & tables,
 * Devices, Products, Who can work here. Every write goes to the API (validated + audited + synced there); a screen that
 * the contract cannot back yet says so instead of offering a dead button.
 */
class FacilitiesController extends Controller
{
    public const TABS = ['general' => 'General', 'capabilities' => 'Capabilities', 'rules' => 'Operating rules', 'points' => 'Operating points & tables', 'devices' => 'Devices', 'products' => 'Products', 'access' => 'Who can work here'];

    public const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    public function index(Request $request, DashboardData $dash)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $rows = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$rows): void {
            foreach ($nodes as $n) {
                $rows[] = $n + ['_depth' => $depth];
                $walk((array) ($n['children'] ?? []), $depth + 1);
            }
        };
        $walk($tree->items(), 0);

        return view('pages.setup.facilities.index', [
            'tree' => $tree, 'rows' => $rows, 'canAdd' => Contract::has('POST', '/organization/facilities'), 'canManage' => $this->staff->canAny('facility.manage', 'facility.configure', 'config.manage'),
        ]);
    }

    /** The "+ Add facility" wizard. */
    public function create(DashboardData $dash)
    {
        abort_unless($this->staff->canAny('facility.manage', 'facility.configure', 'config.manage'), 403);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $templates = Fetch::of(fn () => $this->api->get('organization/facility-templates'), ['GET', '/organization/facility-templates']);
        $capTypes = Fetch::of(fn () => $this->api->get('organization/capability-types'), ['GET', '/organization/capability-types']);
        $defs = Fetch::of(fn () => $this->api->get('organization/rule-definitions'), ['GET', '/organization/rule-definitions']);
        $site = Fetch::of(fn () => $this->api->get('organization/site'), ['GET', '/organization/site']);
        $kinds = is_array($templates->data) ? (array) ($templates->data['kinds'] ?? []) : [];

        return view('pages.setup.facilities.create', [
            'templates' => $templates, 'capTypes' => $capTypes, 'defs' => $defs, 'facilities' => $dash->flatten($tree->items()), 'kinds' => $kinds,
            'timezone' => $site->ok() ? ($site->data['timezone'] ?? 'Africa/Lagos') : 'Africa/Lagos', 'canAdd' => Contract::has('POST', '/organization/facilities'),
            'ruleLabels' => collect($defs->items())->mapWithKeys(fn ($d) => [($d['key'] ?? '') => ($d['label'] ?? '')])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->staff->canAny('facility.manage', 'facility.configure', 'config.manage') && Contract::has('POST', '/organization/facilities'), 403);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:200'], 'code' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_]{1,63}$/'], 'kind' => ['nullable', 'string', 'max:32'],
            'parentId' => ['nullable', 'uuid'], 'description' => ['nullable', 'string', 'max:500'], 'timezone' => ['nullable', 'string', 'max:64'],
            'templateKey' => ['nullable', 'string', 'max:40'], 'capabilities' => ['nullable', 'array'], 'capabilities.*' => ['string', 'max:48'],
        ]);
        $body = array_filter([
            'name' => $d['name'], 'code' => strtoupper($d['code']), 'kind' => ! empty($d['kind']) ? strtoupper($d['kind']) : null, 'parentId' => $d['parentId'] ?? null,
            'description' => $d['description'] ?? null, 'timezone' => $d['timezone'] ?? null, 'templateKey' => $d['templateKey'] ?? null,
            'applyStarter' => $request->boolean('applyStarter'),
        ], fn ($v) => $v !== null && $v !== '');
        // Explicit capability choice beats the template's default set.
        if ($request->has('capabilities')) {
            $body['capabilities'] = array_values($d['capabilities'] ?? []);
        }
        $res = $this->api->request('POST', 'organization/facilities', [], $body);
        $id = $res->body['id'] ?? null;

        return $id ? redirect()->route('setup.facilities.show', ['facility' => $id, 'tab' => 'rules'])->with('success', 'Facility created. Review its operating rules below, then add products and devices.')
            : redirect()->route('setup.facilities')->with('success', 'Facility created.');
    }

    public function show(Request $request, string $facility, DashboardData $dash, Directory $dir)
    {
        $tab = array_key_exists($request->query('tab', 'general'), self::TABS) ? (string) $request->query('tab', 'general') : 'general';
        $res = Fetch::of(fn () => $this->api->request('GET', "organization/facilities/{$facility}"), ['GET', '/organization/facilities/{facilityId}']);
        $fac = $res->ok() ? (array) $res->data->body : [];
        $etag = $res->ok() ? $res->data->etag() : null;
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());

        $data = ['tab' => $tab, 'id' => $facility, 'fac' => $res->ok() ? new Fetch($fac) : $res, 'f' => $fac, 'etag' => $etag, 'flat' => $flat, 'tabs' => self::TABS,
            'canEdit' => $this->staff->canAny('facility.manage', 'facility.configure', 'config.manage'),
            'canCaps' => Contract::has('PUT', '/facilities/{facilityId}/capabilities') && $this->staff->canAny('config.manage.capabilities', 'facility.manage', 'facility.configure', 'config.manage'),
            'canRules' => Contract::has('PUT', '/facilities/{facilityId}/operating-rules') && $this->staff->canAny('config.manage.rules', 'facility.configure', 'config.manage'),
            'canGeneral' => Contract::has('PATCH', '/organization/facilities/{facilityId}') && $this->staff->canAny('facility.manage', 'facility.configure', 'config.manage'),
            'days' => self::DAYS];

        if (! $res->ok()) {
            return view('pages.setup.facilities.show', $data);
        }

        match ($tab) {
            'capabilities' => $data += $this->capabilitiesData($facility),
            'rules' => $data += $this->rulesData($facility, $flat),
            'points' => $data += $this->pointsData($facility),
            'devices' => $data += ['devices' => $this->all('devices', ['filter[facilityId]' => $facility], ['GET', '/devices'], 2)],
            'products' => $data += $this->productsData($facility),
            'access' => $data += $this->accessData($facility, $flat, $dir),
            default => $data += ['timezones' => ['Africa/Lagos', 'UTC', 'Africa/Accra', 'Europe/London'], 'kinds' => $this->kinds()],
        };

        return view('pages.setup.facilities.show', $data);
    }

    /** General: name, kind, description, contact, opening hours (PATCH /organization/facilities/{id}). */
    public function update(Request $request, string $facility): RedirectResponse
    {
        abort_unless(Contract::has('PATCH', '/organization/facilities/{facilityId}') && $this->staff->canAny('facility.manage', 'facility.configure', 'config.manage'), 403);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:200'], 'kind' => ['nullable', 'string', 'max:32'], 'description' => ['nullable', 'string', 'max:500'], 'timezone' => ['nullable', 'string', 'max:64'],
            'sortOrder' => ['nullable', 'integer'], 'contact' => ['nullable', 'array'], 'contact.phone' => ['nullable', 'string', 'max:40'], 'contact.email' => ['nullable', 'email', 'max:190'],
            'contact.address' => ['nullable', 'string', 'max:255'], 'contact.managerName' => ['nullable', 'string', 'max:120'],
            'hours' => ['nullable', 'array'], 'exceptions' => ['nullable', 'array'], 'etag' => ['nullable', 'string', 'max:100'],
        ]);
        $weekly = [];
        foreach (self::DAYS as $k => $_) {
            $open = trim((string) ($d['hours'][$k]['open'] ?? ''));
            $close = trim((string) ($d['hours'][$k]['close'] ?? ''));
            if ($open !== '' && $close !== '') {
                $weekly[$k] = [['open' => $open, 'close' => $close]];
            }
        }
        $exceptions = [];
        foreach ((array) ($d['exceptions'] ?? []) as $ex) {
            $date = trim((string) ($ex['date'] ?? ''));
            if ($date === '') {
                continue;
            }
            $row = ['date' => $date, 'closed' => ! empty($ex['closed'])];
            if (! $row['closed'] && ! empty($ex['open']) && ! empty($ex['close'])) {
                $row['windows'] = [['open' => $ex['open'], 'close' => $ex['close']]];
            }
            ! empty($ex['note']) && $row['note'] = $ex['note'];
            $exceptions[] = $row;
        }
        $contact = array_filter((array) ($d['contact'] ?? []), fn ($v) => $v !== null && $v !== '');
        $body = array_filter([
            'name' => $d['name'], 'kind' => ! empty($d['kind']) ? strtoupper($d['kind']) : null, 'description' => $d['description'] ?? null, 'timezone' => ! empty($d['timezone']) ? $d['timezone'] : null,
            'sortOrder' => isset($d['sortOrder']) ? (int) $d['sortOrder'] : null,
        ], fn ($v) => $v !== null) + ['contact' => $contact === [] ? null : $contact, 'openingHours' => ['weekly' => (object) $weekly, 'exceptions' => $exceptions]];
        $this->api->request('PATCH', "organization/facilities/{$facility}", [], $body, $this->ifMatch($d['etag'] ?? null));

        return redirect()->route('setup.facilities.show', ['facility' => $facility, 'tab' => 'general'])->with('success', 'Facility details saved.');
    }

    /** Capabilities: the full set of enabled capability codes (PUT /facilities/{id}/capabilities). */
    public function setCapabilities(Request $request, string $facility): RedirectResponse
    {
        abort_unless(Contract::has('PUT', '/facilities/{facilityId}/capabilities') && $this->staff->canAny('config.manage.capabilities', 'facility.manage', 'facility.configure', 'config.manage'), 403);
        $d = $request->validate(['capabilities' => ['nullable', 'array'], 'capabilities.*' => ['string', 'max:48'], 'etag' => ['nullable', 'string', 'max:100']]);
        $this->api->request('PUT', "facilities/{$facility}/capabilities", [], ['capabilities' => array_values($d['capabilities'] ?? [])], $this->ifMatch($d['etag'] ?? null));

        return redirect()->route('setup.facilities.show', ['facility' => $facility, 'tab' => 'capabilities'])->with('success', 'Capabilities saved. Rules that no longer apply are hidden; ones that now apply are on the Operating rules tab.');
    }

    /**
     * Operating rules: only the values that changed are sent (a value equal to the standard one is sent as null = reset to the
     * default), with the version this form was built from. A change to a high-impact rule needs the acknowledgement (confirm).
     */
    public function setRules(Request $request, string $facility): RedirectResponse
    {
        abort_unless(Contract::has('PUT', '/facilities/{facilityId}/operating-rules') && $this->staff->canAny('config.manage.rules', 'facility.configure', 'config.manage'), 403);
        $current = $this->api->request('GET', "facilities/{$facility}/operating-rules");
        $defs = collect($this->api->get('organization/rule-definitions')['items'] ?? [])->keyBy('key')->all();
        $values = (array) ($current->body['values'] ?? []);
        $changes = [];
        $risky = false;
        $input = (array) $request->input('rules', []);
        foreach ($values as $key => $_) { // an unticked checkbox group posts nothing: that means "none"
            if (! array_key_exists($key, $input) && ($defs[$key]['type'] ?? '') === 'multi_enum' && $request->has('_rules_present')) {
                $input[$key] = [];
            }
        }
        foreach ($input as $key => $raw) {
            $def = $defs[$key] ?? null;
            if ($def === null || ! array_key_exists($key, $values)) {
                continue; // not applicable at this facility: the API would reject it
            }
            $value = $this->coerce($def, $raw);
            if ($this->same($def, $value, $values[$key])) {
                continue;
            }
            $changes[$key] = $this->same($def, $value, $def['default'] ?? null) ? null : $value;
            $risky = $risky || ($def['dangerLevel'] ?? '') === 'high';
        }
        $back = redirect()->route('setup.facilities.show', ['facility' => $facility, 'tab' => 'rules']);
        if ($changes === []) {
            return $back->with('status', 'Nothing changed.');
        }
        if ($risky && ! $request->boolean('confirm')) {
            return $back->withInput()->with('error', 'You changed a high-impact setting (money, stock or security). Tick the confirmation under the settings, then save again.');
        }
        $this->api->request('PUT', "facilities/{$facility}/operating-rules", [], ['rules' => $changes] + ($risky ? ['confirm' => true] : []), $this->ifMatch((string) $current->etag()));

        return $back->with('success', count($changes) === 1 ? '1 setting saved.' : count($changes).' settings saved.');
    }

    /** @param  array<string, mixed>  $def */
    private function same(array $def, mixed $a, mixed $b): bool
    {
        if (($def['type'] ?? '') === 'money') {
            return $a !== null && $b !== null && bccomp((string) $a, (string) $b, 4) === 0;
        }
        if (($def['type'] ?? '') === 'multi_enum') {
            $x = (array) $a;
            $y = (array) $b;
            sort($x);
            sort($y);

            return $x === $y;
        }

        return $a === $b || (is_numeric($a) && is_numeric($b) && (float) $a === (float) $b);
    }

    public function deactivate(Request $request, string $facility): RedirectResponse
    {
        abort_unless(Contract::has('POST', '/organization/facilities/{facilityId}/deactivate') && $this->staff->canAny('facility.manage', 'facility.configure', 'config.manage'), 403);
        $d = $request->validate(['reason' => ['nullable', 'string', 'max:255'], 'etag' => ['nullable', 'string', 'max:100']]);
        $this->api->request('POST', "organization/facilities/{$facility}/deactivate", [], array_filter(['reason' => $d['reason'] ?? null, 'cascade' => $request->boolean('cascade')], fn ($v) => $v !== null), $this->ifMatch($d['etag'] ?? null));

        return redirect()->route('setup.facilities.show', ['facility' => $facility])->with('success', 'Facility deactivated. It stops appearing in the apps; its history is kept.');
    }

    public function reactivate(Request $request, string $facility): RedirectResponse
    {
        abort_unless(Contract::has('POST', '/organization/facilities/{facilityId}/reactivate') && $this->staff->canAny('facility.manage', 'facility.configure', 'config.manage'), 403);
        $this->api->request('POST', "organization/facilities/{$facility}/reactivate", [], [], $this->ifMatch($request->input('etag')));

        return redirect()->route('setup.facilities.show', ['facility' => $facility])->with('success', 'Facility reactivated.');
    }

    public function move(Request $request, string $facility): RedirectResponse
    {
        abort_unless(Contract::has('POST', '/organization/facilities/{facilityId}/move') && $this->staff->canAny('facility.manage', 'facility.configure', 'config.manage'), 403);
        $d = $request->validate(['parentId' => ['nullable', 'uuid'], 'etag' => ['nullable', 'string', 'max:100']]);
        $this->api->request('POST', "organization/facilities/{$facility}/move", [], ['parentId' => $d['parentId'] ?? null], $this->ifMatch($d['etag'] ?? null));

        return redirect()->route('setup.facilities.show', ['facility' => $facility])->with('success', 'Facility moved.');
    }

    // ---- tab data -----------------------------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function capabilitiesData(string $facility): array
    {
        $caps = Fetch::of(fn () => $this->api->request('GET', "facilities/{$facility}/capabilities"), ['GET', '/facilities/{facilityId}/capabilities']);
        $types = Fetch::of(fn () => $this->api->get('organization/capability-types'), ['GET', '/organization/capability-types']);
        $body = $caps->ok() ? (array) $caps->data->body : [];

        return ['caps' => $caps->ok() ? new Fetch($body) : $caps, 'capTypes' => $types, 'enabled' => (array) ($body['capabilities'] ?? []), 'capEtag' => $caps->ok() ? $caps->data->etag() : null, 'capVersion' => $body['version'] ?? null];
    }

    /**
     * @param  list<array<string, mixed>>  $flat
     * @return array<string, mixed>
     */
    private function rulesData(string $facility, array $flat): array
    {
        $eff = Fetch::of(fn () => $this->api->request('GET', "facilities/{$facility}/operating-rules"), ['GET', '/facilities/{facilityId}/operating-rules']);
        $defs = Fetch::of(fn () => $this->api->get('organization/rule-definitions'), ['GET', '/organization/rule-definitions']);
        $body = $eff->ok() ? (array) $eff->data->body : [];
        $facilities = collect($flat)->filter(fn ($f) => in_array('PAYMENT_ACCEPTANCE', (array) ($f['capabilities'] ?? []), true))
            ->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name'] ?? $f['code']])->values()->all();

        return [
            'eff' => $eff->ok() ? new Fetch($body) : $eff, 'defs' => $defs, 'defMap' => collect($defs->items())->keyBy('key')->all(),
            'ruleValues' => (array) ($body['values'] ?? []), 'ruleCapabilities' => (array) ($body['capabilities'] ?? []), 'ruleVersion' => $eff->ok() ? $eff->data->etag() : null,
            'notApplicable' => (array) ($body['notApplicable'] ?? []), 'ruleOptions' => ['payment_facility_unit_id' => $facilities],
        ];
    }

    /** @return array<string, mixed> */
    private function pointsData(string $facility): array
    {
        return [
            'points' => Fetch::of(fn () => $this->api->get("organization/facilities/{$facility}/operating-points"), ['GET', '/organization/facilities/{facilityId}/operating-points']),
            'tables' => $this->all('tables', ['facilityId' => $facility], ['GET', '/tables'], 2),
            'stations' => Fetch::of(fn () => $this->api->get('kds/stations', ['facilityId' => $facility, 'limit' => 100]), ['GET', '/kds/stations']),
        ];
    }

    /** @return array<string, mixed> */
    private function productsData(string $facility): array
    {
        return ['products' => $this->all('catalog/products', ['facilityId' => $facility, 'includeInactive' => 1], ['GET', '/catalog/products'], 2), 'avail' => $this->all('catalog/availability', ['facilityId' => $facility], ['GET', '/catalog/availability'], 2)];
    }

    /**
     * Who holds a role that covers this facility: at the facility itself, or inherited from the site/organization.
     *
     * @param  list<array<string, mixed>>  $flat
     * @return array<string, mixed>
     */
    private function accessData(string $facility, array $flat, Directory $dir): array
    {
        if (! $this->staff->can('staff.manage')) {
            return ['access' => new Fetch(null, 'forbidden'), 'rows' => []];
        }
        $staff = $this->all('staff', [], ['GET', '/staff'], 2);
        $roles = $this->all('roles', [], ['GET', '/roles'], 1);
        $roleNames = collect($roles->items())->mapWithKeys(fn ($r) => [($r['id'] ?? '') => ($r['name'] ?? $r['code'] ?? '')])->all();
        $rows = [];
        foreach (array_slice($staff->items(), 0, 60) as $m) {
            if (empty($m['id'])) {
                continue;
            }
            $a = Fetch::of(fn () => $this->api->get("staff/{$m['id']}/role-assignments", ['limit' => 50]), ['GET', '/staff/{staffId}/role-assignments']);
            foreach ($a->items() as $as) {
                if (! empty($as['revokedAt'])) {
                    continue;
                }
                $scope = $as['scopeType'] ?? '';
                if ($scope === 'FACILITY' && ($as['scopeId'] ?? null) !== $facility) {
                    continue;
                }
                $rows[] = ['staffId' => $m['id'], 'name' => $m['displayName'] ?? '', 'number' => $m['staffNumber'] ?? '', 'status' => $m['status'] ?? '', 'role' => $roleNames[$as['roleId'] ?? ''] ?? ($as['roleCode'] ?? ''), 'scope' => $scope, 'inherited' => $scope !== 'FACILITY'];
            }
        }

        return ['access' => $staff, 'rows' => $rows];
    }

    /** @return list<string> */
    private function kinds(): array
    {
        $t = Fetch::of(fn () => $this->api->get('organization/facility-templates'), ['GET', '/organization/facility-templates']);

        return is_array($t->data) && ! empty($t->data['kinds']) ? (array) $t->data['kinds'] : ['RECEPTION', 'RESTAURANT', 'CLUB', 'BAR', 'SPA', 'SALON', 'CAFE', 'KITCHEN', 'RETAIL', 'STORE', 'SPORTS', 'POOL', 'EVENT_CENTRE', 'OFFICE', 'GATE', 'GENERAL'];
    }

    /** @param  array<string, mixed>  $def */
    private function coerce(array $def, mixed $raw): mixed
    {
        return match ($def['type'] ?? '') {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'multi_enum' => array_values(array_filter((array) $raw, fn ($v) => $v !== '' && $v !== null)),
            'number', 'duration' => $raw === '' || $raw === null ? null : (($def['integer'] ?? false) || ($def['type'] === 'duration') ? (int) $raw : (str_contains((string) $raw, '.') ? (float) $raw : (int) $raw)),
            'facility' => $raw === '' ? null : (string) $raw,
            default => $raw === '' ? null : (string) $raw,
        };
    }

    /** @return array<string, string> */
    private function ifMatch(?string $etag): array
    {
        $etag = trim((string) $etag);

        return $etag !== '' ? ['If-Match' => $etag] : [];
    }
}
