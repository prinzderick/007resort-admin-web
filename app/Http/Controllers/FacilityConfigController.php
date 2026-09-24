<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Setup > Facilities > Operating points, kitchen/bar screens and dining tables (docs/CONFIG_ADMIN_API.md sections 3 and 4).
 * Every write is version-checked with the row version the form was built from ("If-Match"), so a second administrator's change
 * is reported instead of silently overwritten.
 */
class FacilityConfigController extends Controller
{
    public const POINT_KINDS = ['TABLE_AREA' => 'Table area', 'COUNTER' => 'Counter / till', 'GATE' => 'Gate / entrance', 'STORE_WINDOW' => 'Store window', 'STATION' => 'Kitchen / bar screen', 'ROOM' => 'Room'];

    public const KDS_KINDS = ['KITCHEN' => 'Kitchen', 'BAR' => 'Bar', 'DISPENSE' => 'Dispense / pass'];

    private function back(string $facility, string $message): RedirectResponse
    {
        return redirect()->route('setup.facilities.show', ['facility' => $facility, 'tab' => 'points'])->with('success', $message);
    }

    private function ifMatch(Request $request): array
    {
        $v = trim((string) $request->input('rowVersion'));

        return $v !== '' ? ['If-Match' => '"'.trim($v, '"').'"'] : [];
    }

    // ---- operating points -------------------------------------------------------------------------------------------------

    public function storePoint(Request $request, string $facility): RedirectResponse
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'code' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_]{1,63}$/'],
            'kind' => ['required', 'in:'.implode(',', array_keys(self::POINT_KINDS))], 'kdsKind' => ['nullable', 'in:'.implode(',', array_keys(self::KDS_KINDS))],
            'prepRouteId' => ['nullable', 'uuid'], 'defaultPrepStationId' => ['nullable', 'uuid'],
        ]);
        $body = ['name' => $d['name'], 'code' => strtoupper($d['code']), 'kind' => $d['kind']];
        if (! empty($d['defaultPrepStationId'])) {
            $body['defaultPrepStationId'] = $d['defaultPrepStationId'];
        }
        if ($d['kind'] === 'STATION') {
            $body['kdsStation'] = array_filter(['kind' => $d['kdsKind'] ?? 'KITCHEN', 'prepRouteId' => $d['prepRouteId'] ?? null]);
        }
        $this->api->request('POST', "organization/facilities/{$facility}/operating-points", [], $body);

        return $this->back($facility, 'Operating point added.');
    }

    public function updatePoint(Request $request, string $point): RedirectResponse
    {
        $d = $request->validate(['facilityId' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:120'], 'defaultPrepStationId' => ['nullable', 'uuid'], 'prepRouteId' => ['nullable', 'uuid']]);
        $body = ['name' => $d['name'], 'defaultPrepStationId' => $d['defaultPrepStationId'] ?? null];
        if (! empty($d['prepRouteId'])) {
            $body['kdsStation'] = ['prepRouteId' => $d['prepRouteId']];
        }
        $this->api->request('PATCH', "organization/operating-points/{$point}", [], $body, $this->ifMatch($request));

        return $this->back($d['facilityId'], 'Operating point saved.');
    }

    public function pointState(Request $request, string $point, string $state): RedirectResponse
    {
        abort_unless(in_array($state, ['deactivate', 'reactivate'], true), 404);
        $d = $request->validate(['facilityId' => ['required', 'uuid']]);
        $this->api->request('POST', "organization/operating-points/{$point}/{$state}", [], [], $this->ifMatch($request));

        return $this->back($d['facilityId'], $state === 'deactivate' ? 'Operating point switched off.' : 'Operating point switched back on.');
    }

    // ---- dining tables ----------------------------------------------------------------------------------------------------

    public function storeTable(Request $request, string $facility): RedirectResponse
    {
        $d = $request->validate(['label' => ['required', 'string', 'max:32'], 'seats' => ['nullable', 'integer', 'min:1', 'max:200'], 'operatingPointId' => ['nullable', 'uuid']]);
        $this->api->request('POST', "organization/facilities/{$facility}/tables", [], array_filter(['label' => $d['label'], 'seats' => isset($d['seats']) ? (int) $d['seats'] : null, 'operatingPointId' => $d['operatingPointId'] ?? null], fn ($v) => $v !== null));

        return $this->back($facility, 'Table '.$d['label'].' added.');
    }

    /** "T1 to T20, 4 seats": the API skips labels that already exist and says which. */
    public function bulkTables(Request $request, string $facility): RedirectResponse
    {
        $d = $request->validate([
            'prefix' => ['nullable', 'string', 'max:12'], 'from' => ['required', 'integer', 'min:0', 'max:9999'], 'to' => ['required', 'integer', 'gte:from', 'max:9999'],
            'seats' => ['required', 'integer', 'min:1', 'max:200'], 'padWidth' => ['nullable', 'integer', 'min:0', 'max:6'], 'operatingPointId' => ['nullable', 'uuid'],
        ]);
        abort_if($d['to'] - $d['from'] >= 500, 422, 'A batch can create at most 500 tables.');
        $res = $this->api->request('POST', "organization/facilities/{$facility}/tables/bulk", [], array_filter([
            'prefix' => $d['prefix'] ?? '', 'from' => (int) $d['from'], 'to' => (int) $d['to'], 'seats' => (int) $d['seats'],
            'padWidth' => isset($d['padWidth']) ? (int) $d['padWidth'] : null, 'operatingPointId' => $d['operatingPointId'] ?? null,
        ], fn ($v) => $v !== null));
        $created = count((array) ($res->body['created'] ?? []));
        $skipped = collect((array) ($res->body['skipped'] ?? []))->map(fn ($s) => is_array($s) ? ($s['label'] ?? '') : (string) $s)->filter()->values();
        $msg = $created.' table'.($created === 1 ? '' : 's').' added.'.($skipped->isNotEmpty() ? ' Skipped because they already exist: '.$skipped->implode(', ').'.' : '');

        return $this->back($facility, $msg);
    }

    public function updateTable(Request $request, string $table): RedirectResponse
    {
        $d = $request->validate(['facilityId' => ['required', 'uuid'], 'label' => ['required', 'string', 'max:32'], 'seats' => ['required', 'integer', 'min:1', 'max:200'], 'operatingPointId' => ['nullable', 'uuid']]);
        $this->api->request('PATCH', "organization/tables/{$table}", [], ['label' => $d['label'], 'seats' => (int) $d['seats'], 'operatingPointId' => $d['operatingPointId'] ?? null], $this->ifMatch($request));

        return $this->back($d['facilityId'], 'Table saved.');
    }

    public function tableState(Request $request, string $table, string $state): RedirectResponse
    {
        abort_unless(in_array($state, ['deactivate', 'reactivate', 'unmerge'], true), 404);
        $d = $request->validate(['facilityId' => ['required', 'uuid']]);
        $this->api->request('POST', "organization/tables/{$table}/{$state}", [], [], $this->ifMatch($request));

        return $this->back($d['facilityId'], match ($state) { 'deactivate' => 'Table switched off.', 'reactivate' => 'Table switched back on.', default => 'Tables separated.' });
    }

    public function mergeTable(Request $request, string $table): RedirectResponse
    {
        $d = $request->validate(['facilityId' => ['required', 'uuid'], 'intoTableId' => ['required', 'uuid']]);
        $this->api->request('POST', "organization/tables/{$table}/merge", [], ['intoTableId' => $d['intoTableId']], $this->ifMatch($request));

        return $this->back($d['facilityId'], 'Tables joined. Their seats now count together.');
    }
}
