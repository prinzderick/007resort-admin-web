<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Support\Csv;
use App\Support\Fetch;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('status');
        $staff = Fetch::of(fn () => $this->api->get('staff', ['limit' => 100, 'q' => $request->query('q'), 'filter[status]' => $filter]), ['GET', '/staff']);

        return view('pages.staff.index', ['staff' => $staff, 'q' => $request->query('q'), 'status' => $filter]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'staffNumber' => ['required', 'string', 'max:40'], 'firstName' => ['required', 'string', 'max:100'], 'lastName' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $res = $this->api->request('POST', 'staff', [], array_filter($d, fn ($v) => $v !== null && $v !== ''));

        return redirect()->route('staff.show', $res->body['id'])->with('success', 'Staff member created. Set a password, PIN or card next.');
    }

    public function show(string $staff)
    {
        $member = $this->api->request('GET', "staff/{$staff}");
        $member = ['data' => $member->body, 'etag' => $member->etag()];

        $roles = Fetch::of(fn () => $this->api->get('roles', ['limit' => 100]), ['GET', '/roles']);
        $assign = Fetch::of(fn () => $this->api->get("staff/{$staff}/role-assignments", ['limit' => 100]), ['GET', '/staff/{staffId}/role-assignments']);
        $facilities = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $devices = $this->staff->can('device.register')
            ? Fetch::of(fn () => $this->api->get('devices', ['limit' => 200]), ['GET', '/devices'])
            : new Fetch(null, 'forbidden');
        $site = Fetch::of(fn () => $this->api->get('organization/site'), ['GET', '/organization/site']);
        $audit = $this->staff->can('audit.view')
            ? Fetch::of(fn () => $this->api->get('audit', ['entityType' => 'staff', 'entityId' => $staff, 'limit' => 20]), ['GET', '/audit'])
            : new Fetch(null, 'forbidden');

        $roleNames = [];
        foreach ($roles->items() as $r) {
            $roleNames[$r['id']] = $r['name'];
        }
        $mine = array_values(array_filter($devices->items(), fn ($d) => ($d['checkout']['staffId'] ?? null) === $staff && ($d['checkout']['checkedInAt'] ?? null) === null));

        return view('pages.staff.show', [
            'member' => $member['data'], 'etag' => $member['etag'], 'roles' => $roles, 'assign' => $assign, 'roleNames' => $roleNames,
            'facilities' => app(DashboardData::class)->flatten($facilities->items()), 'site' => $site->data, 'devices' => $devices, 'myDevices' => $mine, 'audit' => $audit,
        ]);
    }

    public function update(Request $request, string $staff): RedirectResponse
    {
        $d = $request->validate([
            'firstName' => ['required', 'string', 'max:100'], 'lastName' => ['required', 'string', 'max:100'], 'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'], 'status' => ['required', 'in:ACTIVE,SUSPENDED,TERMINATED'], 'etag' => ['nullable', 'string', 'max:100'],
        ]);
        $headers = ! empty($d['etag']) ? ['If-Match' => $d['etag']] : [];
        $this->api->request('PATCH', "staff/{$staff}", [], array_diff_key($d, ['etag' => 1]), $headers);

        return redirect()->route('staff.show', $staff)->with('success', 'Staff member updated.');
    }

    public function grantRole(Request $request, string $staff): RedirectResponse
    {
        abort_unless($this->staff->can('role_assignment.manage'), 403);
        $d = $request->validate(['roleId' => ['required', 'uuid'], 'scopeType' => ['required', 'in:ORGANIZATION,SITE,FACILITY'], 'scopeId' => ['required', 'uuid']]);
        $this->api->request('POST', "staff/{$staff}/role-assignments", [], $d);

        return redirect()->route('staff.show', $staff)->with('success', 'Role granted.');
    }

    public function revokeRole(string $staff, string $assignment): RedirectResponse
    {
        abort_unless($this->staff->can('role_assignment.manage'), 403);
        $this->api->request('DELETE', "staff/{$staff}/role-assignments/{$assignment}");

        return redirect()->route('staff.show', $staff)->with('success', 'Role revoked.');
    }

    public function credential(Request $request, string $staff, string $kind): RedirectResponse
    {
        abort_unless(in_array($kind, ['password', 'pin', 'nfc-card'], true), 404);
        $rules = ['password' => ['password' => ['required', 'string', 'min:10', 'max:200']], 'pin' => ['pin' => ['required', 'digits_between:4,8']], 'nfc-card' => ['cardUid' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9:\-]+$/']]][$kind];
        $d = $request->validate($rules);
        $this->api->request('PUT', "staff/{$staff}/credentials/{$kind}", [], $d);

        return redirect()->route('staff.show', $staff)->with('success', ['password' => 'Password set.', 'pin' => 'PIN set.', 'nfc-card' => 'NFC card assigned.'][$kind]);
    }

    public function removeCard(string $staff): RedirectResponse
    {
        $this->api->request('DELETE', "staff/{$staff}/credentials/nfc-card");

        return redirect()->route('staff.show', $staff)->with('success', 'NFC card removed.');
    }

    public function attendance(Request $request)
    {
        $from = $this->day($request->query('from')) ?? Time::today();
        $to = $this->day($request->query('to')) ?? Time::today();
        $records = Fetch::of(fn () => $this->api->get('attendance', ['filter[from]' => $from, 'filter[to]' => $to, 'filter[status]' => $request->query('status'), 'limit' => 200]), ['GET', '/attendance']);
        $corrections = Fetch::of(fn () => $this->api->get('attendance/corrections', ['filter[status]' => 'PENDING', 'limit' => 50]), ['GET', '/attendance/corrections']);

        if ($request->query('format') === 'csv') {
            return Csv::stream("attendance-{$from}-{$to}.csv", ['Date', 'Staff', 'Clock in', 'Clock out', 'Minutes', 'Status', 'Source'],
                array_map(fn ($r) => [$r['workDate'], $r['staffName'] ?? $r['staffId'], $r['clockIn'] ?? '', $r['clockOut'] ?? '', $r['minutesWorked'] ?? '', $r['status'], $r['source'] ?? ''], $records->items()));
        }

        return view('pages.staff.attendance', ['records' => $records, 'corrections' => $corrections, 'from' => $from, 'to' => $to, 'status' => $request->query('status')]);
    }

    public function decideCorrection(Request $request, string $correction, string $decision): RedirectResponse
    {
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);
        $this->api->request('POST', "attendance/corrections/{$correction}/{$decision}", [], array_filter(['note' => $request->input('note')]));

        return redirect()->route('staff.attendance')->with('success', 'Correction '.($decision === 'approve' ? 'approved.' : 'rejected.'));
    }

    public function audit(Request $request)
    {
        $params = ['limit' => 100, 'action' => $request->query('action'), 'entityType' => $request->query('entityType'), 'actorStaffId' => $request->query('actor'),
            'filter[from]' => $this->day($request->query('from')), 'filter[to]' => $this->day($request->query('to')), 'cursor' => $request->query('cursor')];
        $audit = Fetch::of(fn () => $this->api->get('audit', $params), ['GET', '/audit']);

        if ($request->query('format') === 'csv') {
            return Csv::stream('audit-trail.csv', ['Seq', 'Time', 'Actor', 'Action', 'Entity type', 'Entity', 'Reason', 'Hash'],
                array_map(fn ($a) => [$a['seq'], $a['occurredAt'], $a['actorName'] ?? $a['actorStaffId'] ?? '', $a['action'], $a['entityType'], $a['entityId'] ?? '', $a['reason'] ?? '', $a['hash']], $audit->items()));
        }

        return view('pages.staff.audit', ['audit' => $audit, 'q' => $request->query()]);
    }

    private function day(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
