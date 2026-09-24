<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Support\Contract;
use App\Support\Fetch;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DevicesController extends Controller
{
    /** Modes that fit a device kind (the API refuses the rest with 422). */
    public const MODES = [
        'MOBILE_TABLET' => ['ATTENDANT' => 'Waiter / attendant', 'SUPERVISOR' => 'Supervisor', 'SPORTS_ENTRANCE' => 'Sports entrance scanner', 'SPORTS_STORE' => 'Sports store'],
        'POS_TERMINAL' => ['POS' => 'Point of sale'],
        'KDS_SCREEN' => ['KDS' => 'Kitchen / bar screen'],
        'ENTRANCE_SCANNER' => ['SPORTS_ENTRANCE' => 'Sports entrance scanner', 'SPORTS_STORE' => 'Sports store'],
        'ATTENDANCE_TERMINAL' => ['ATTENDANCE_TERMINAL' => 'Attendance terminal'],
    ];

    public function index(Request $request, DashboardData $dash)
    {
        $status = $request->query('status');
        $devices = $this->staff->canAny('device.register', 'device.revoke', 'device.manage')
            ? Fetch::of(fn () => $this->api->get('devices', ['limit' => 200, 'filter[status]' => $status]), ['GET', '/devices'])
            : new Fetch(null, 'forbidden');
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $attendance = $this->staff->can('attendance.device.manage')
            ? Fetch::of(fn () => $this->api->get('attendance/devices', ['limit' => 100]), ['GET', '/attendance/devices'])
            : new Fetch(null, 'forbidden');

        $names = [];
        foreach ($dash->flatten($tree->items()) as $f) {
            $names[$f['id'] ?? ''] = $f['name'] ?? '';
        }
        // Who a tablet is checked out to: names when this account may list staff, otherwise the view shows a short id.
        $staffNames = [];
        if ($this->staff->can('staff.manage') && $devices->ok()) {
            $staff = Fetch::of(fn () => $this->api->get('staff', ['limit' => 200]), ['GET', '/staff']);
            foreach ($staff->items() as $m) {
                $staffNames[$m['id'] ?? ''] = $m['displayName'] ?? ($m['staffNumber'] ?? '');
            }
        }

        $points = $this->staff->canAny('device.manage', 'facility.manage', 'config.view')
            ? $this->all('organization/operating-points', [], ['GET', '/organization/operating-points'], 2) : new Fetch(null, 'forbidden');

        return view('pages.devices.index', ['points' => $points, 'canManage' => $this->staff->can('device.manage') && Contract::has('PATCH', '/devices/{deviceId}'), 'modes' => self::MODES, 'devices' => $devices, 'attendance' => $attendance, 'facilities' => $dash->flatten($tree->items()), 'facilityNames' => $names, 'staffNames' => $staffNames, 'status' => $status]);
    }

    /** Issue a one-time registration code. The device registers itself with it (POST /devices/register). */
    public function issueCode(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('device.register'), 403);
        $d = $request->validate(['facilityId' => ['nullable', 'uuid']]);
        $r = $this->api->request('POST', 'devices/registration-codes', [], array_filter($d));

        return redirect()->route('devices.index')->with('secret', ['label' => 'One-time registration code', 'value' => $r->body['code'] ?? '', 'note' => 'Enter it on the new device. It works once and expires '.Time::format($r->body['expiresAt'] ?? null).'.']);
    }

    /** PATCH /devices/{id}: name, home facility, operating point, mode, active (device.manage, version-checked). */
    public function update(Request $request, string $device): RedirectResponse
    {
        abort_unless($this->staff->can('device.manage'), 403);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'facilityId' => ['nullable', 'uuid'], 'operatingPointId' => ['nullable', 'uuid'],
            'mode' => ['nullable', 'string', 'max:32'], 'rowVersion' => ['nullable', 'string', 'max:20'], 'back' => ['nullable', 'string', 'max:200'],
        ]);
        $body = ['name' => $d['name'], 'facilityId' => $d['facilityId'] ?? null, 'operatingPointId' => $d['operatingPointId'] ?? null];
        if (! empty($d['mode'])) {
            $body['mode'] = $d['mode'];
        }
        $headers = ! empty($d['rowVersion']) ? ['If-Match' => '"'.trim($d['rowVersion'], '"').'"'] : [];
        $this->api->request('PATCH', "devices/{$device}", [], $body, $headers);
        $back = ! empty($d['back']) && str_starts_with($d['back'], '/') ? $d['back'] : route('devices.index');

        return redirect($back)->with('success', 'Device saved. It picks up the new home facility and mode the next time it connects.');
    }

    public function revoke(string $device): RedirectResponse
    {
        abort_unless($this->staff->can('device.revoke'), 403);
        $this->api->request('POST', "devices/{$device}/revoke");

        return redirect()->route('devices.index')->with('success', 'Device revoked. It can no longer call the API, even with a cached staff session.');
    }

    public function createTerminal(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('attendance.device.manage'), 403);
        $d = $request->validate(['serialNumber' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:120'], 'facilityId' => ['nullable', 'uuid'], 'adapter' => ['required', 'in:ZKTECO_ADMS,JSON_PUSH']]);
        $r = $this->api->request('POST', 'attendance/devices', [], array_filter($d, fn ($v) => $v !== null && $v !== ''));

        return redirect()->route('devices.index')->with('secret', ['label' => 'Terminal token (shown once)', 'value' => $r->body['deviceToken'] ?? '', 'note' => 'Configure it on the terminal now; it cannot be shown again.']);
    }

    public function rotateTerminal(string $device): RedirectResponse
    {
        abort_unless($this->staff->can('attendance.device.manage'), 403);
        $r = $this->api->request('POST', "attendance/devices/{$device}/rotate-token");

        return redirect()->route('devices.index')->with('secret', ['label' => 'New terminal token (shown once)', 'value' => $r->body['deviceToken'] ?? '', 'note' => 'The previous token stopped working.']);
    }

    public function terminalStatus(Request $request, string $device): RedirectResponse
    {
        abort_unless($this->staff->can('attendance.device.manage'), 403);
        $d = $request->validate(['status' => ['required', 'in:ACTIVE,DISABLED']]);
        $this->api->request('POST', "attendance/devices/{$device}/status", [], $d);

        return redirect()->route('devices.index')->with('success', 'Terminal '.strtolower($d['status'] === 'ACTIVE' ? 'enabled' : 'disabled').'.');
    }
}
