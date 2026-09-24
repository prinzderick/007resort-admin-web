<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Services\Portal\Directory;
use App\Support\Fetch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Card machines (payment terminal registry, docs/WAITER_COLLECTION.md section 5). A terminal belongs to a facility and can be handed to
 * one tablet or one waiter. The manual bank machines need no integration: the cashier confirms each slip.
 */
class TerminalsController extends Controller
{
    public const PROVIDERS = ['MANUAL_BANK' => 'Bank card machine (cashier checks the slip)', 'PAYSTACK_TERMINAL' => 'Paystack terminal (confirms itself)'];

    public const STATUSES = ['ACTIVE' => 'In use', 'INACTIVE' => 'Not in use', 'RETIRED' => 'Retired'];

    public function index(Request $request, DashboardData $dash, Directory $dir)
    {
        $status = (string) $request->query('status', '');
        $facilityId = (string) $request->query('facilityId', '');
        $terminals = $this->all('payment-terminals', array_filter(['status' => $status ?: null, 'facilityId' => $facilityId ?: null]), ['GET', '/payment-terminals'], 2);
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        $devices = $this->staff->canAny('device.register', 'device.revoke', 'device.manage') ? $this->all('devices', ['filter[kind]' => 'MOBILE_TABLET'], ['GET', '/devices'], 1) : new Fetch(null, 'forbidden');

        return view('pages.devices.terminals', [
            'terminals' => $terminals, 'facilities' => $flat, 'facilityNames' => collect($flat)->pluck('name', 'id')->all(), 'devices' => $devices,
            'deviceNames' => collect($devices->items())->pluck('name', 'id')->all(), 'staffNames' => $dir->staffNames(), 'status' => $status, 'facilityId' => $facilityId,
            'canManage' => $this->staff->can('device.manage'), 'providers' => self::PROVIDERS, 'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('device.manage'), 403);
        $d = $request->validate([
            'facilityId' => ['required', 'uuid'], 'label' => ['required', 'string', 'max:120'], 'serial' => ['required', 'string', 'max:80'],
            'provider' => ['required', 'in:'.implode(',', array_keys(self::PROVIDERS))], 'assignedDeviceId' => ['nullable', 'uuid'], 'assignedStaffId' => ['nullable', 'uuid'],
        ]);
        $this->api->request('POST', 'payment-terminals', [], array_filter($d, fn ($v) => $v !== null && $v !== ''));

        return redirect()->route('devices.payment-terminals')->with('success', 'Card machine added.');
    }

    public function update(Request $request, string $terminal): RedirectResponse
    {
        abort_unless($this->staff->can('device.manage'), 403);
        $d = $request->validate([
            'label' => ['required', 'string', 'max:120'], 'status' => ['required', 'in:'.implode(',', array_keys(self::STATUSES))],
            'assignedDeviceId' => ['nullable', 'uuid'], 'assignedStaffId' => ['nullable', 'uuid'], 'rowVersion' => ['nullable', 'string', 'max:20'],
        ]);
        $body = ['label' => $d['label'], 'status' => $d['status'], 'assignedDeviceId' => $d['assignedDeviceId'] ?? null, 'assignedStaffId' => $d['assignedStaffId'] ?? null];
        $this->api->request('PATCH', "payment-terminals/{$terminal}", [], $body, ! empty($d['rowVersion']) ? ['If-Match' => '"'.trim($d['rowVersion'], '"').'"'] : []);

        return redirect()->route('devices.payment-terminals')->with('success', 'Card machine saved.');
    }
}
