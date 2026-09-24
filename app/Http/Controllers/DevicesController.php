<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Support\Fetch;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DevicesController extends Controller
{
    public function index(Request $request, DashboardData $dash)
    {
        $status = $request->query('status');
        $devices = $this->staff->canAny('device.register', 'device.revoke')
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

        return view('pages.devices.index', ['devices' => $devices, 'attendance' => $attendance, 'facilities' => $dash->flatten($tree->items()), 'facilityNames' => $names, 'staffNames' => $staffNames, 'status' => $status]);
    }

    /** Issue a one-time registration code. The device registers itself with it (POST /devices/register). */
    public function issueCode(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('device.register'), 403);
        $d = $request->validate(['facilityId' => ['nullable', 'uuid']]);
        $r = $this->api->request('POST', 'devices/registration-codes', [], array_filter($d));

        return redirect()->route('devices.index')->with('secret', ['label' => 'One-time registration code', 'value' => $r->body['code'] ?? '', 'note' => 'Enter it on the new device. It works once and expires '.Time::format($r->body['expiresAt'] ?? null).'.']);
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
