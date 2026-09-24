<x-layouts.app title="Devices">
    <x-page-header title="Devices" subtitle="Tablets, POS terminals, kitchen screens and scanners registered with the property server. Choose where each one works and what it does.">
        <x-slot:actions>
            @if (auth_staff()->can('payment.collect') || auth_staff()->can('device.manage'))<x-btn variant="secondary" icon="card" :href="route('devices.payment-terminals')">Card machines</x-btn>@endif
            @if (auth_staff()->can('device.register'))<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'register-device')">Register a device</x-btn>@endif
        </x-slot:actions>
    </x-page-header>
    @if (session('secret'))
        <div class="mb-5 rounded-xl border-2 border-sky-400 bg-sky-50 p-4" data-testid="secret">
            <div class="text-sm font-semibold">{{ session('secret')['label'] }}</div>
            <div class="my-2 select-all font-mono text-2xl tracking-wider">{{ session('secret')['value'] }}</div>
            <div class="text-sm text-sky-900">{{ session('secret')['note'] }}</div>
        </div>
    @endif

    @if ($devices->state !== 'forbidden')
    <x-card flush x-data="tableTools">
        <x-table-tools placeholder="Filter devices...">
            <form method="GET"><x-filter-select name="status" label="Status" :options="['ACTIVE' => 'Active', 'PENDING' => 'Pending', 'REVOKED' => 'Revoked']" :value="$status" all="All statuses" /></form>
        </x-table-tools>
        <x-fetch :of="$devices" what="Devices" />
        @if ($devices->ok())
            <div class="table-scroll"><table class="data-table" data-testid="devices-table"><thead><tr><th>Device</th><th>Does</th><th>Status</th><th>Home facility</th><th>Checked out to</th><th>App</th><th>Last seen</th><th class="w-12"></th></tr></thead><tbody>
            @forelse ($devices->items() as $d)
                @php
                    $seen = \App\Support\Time::parse($d['lastSeenAt'] ?? null);
                    $dstatus = $d['status'] ?? 'UNKNOWN';
                    $quiet = $dstatus === 'ACTIVE' && (! $seen || $seen->diffInSeconds(now()) > 300);
                    $co = $d['checkout'] ?? null;
                    $out = is_array($co) && empty($co['checkedInAt']);
                    $home = $d['homeFacility']['name'] ?? ($facilityNames[$d['homeFacilityId'] ?? ''] ?? null);
                    $devName = $d['name'] ?? ($d['id'] ?? 'device');
                @endphp
                <tr data-row>
                    <td><div class="font-medium">{{ $devName }}</div><div class="text-xs text-stone-500">{{ str_replace('_', ' ', $d['kind'] ?? '') }} &middot; {{ $d['platform'] ?? '' }}</div></td>
                    <td>@if (! empty($d['mode']))<x-badge tone="info" :dot="false">{{ ucwords(strtolower(str_replace('_', ' ', $d['mode']))) }}</x-badge>@endif</td>
                    <td><x-badge :status="$dstatus" /></td>
                    <td>{{ $home ?? '-' }}</td>
                    <td class="text-sm">@if ($out){{ $staffNames[$co['staffId'] ?? ''] ?? \Illuminate\Support\Str::limit((string) ($co['staffId'] ?? ''), 8, '') }}<div class="text-xs text-stone-500">at {{ $facilityNames[$d['facilityId'] ?? ($co['facilityId'] ?? '')] ?? '-' }}</div>@else<span class="text-stone-400">-</span>@endif</td>
                    <td class="text-stone-600">{{ $d['appVersion'] ?? '' }}</td>
                    <td class="whitespace-nowrap"><x-time :at="$d['lastSeenAt'] ?? null" ago />@if ($quiet)<x-badge tone="warn" class="ml-1">quiet</x-badge>@endif</td>
                    <td class="text-right">@if (! empty($d['id']) && ($canManage || ($dstatus !== 'REVOKED' && auth_staff()->can('device.revoke'))))<x-row-menu>
                        @if ($canManage)<button type="button" @click="$dispatch('open-modal', 'edit-device-{{ $d['id'] }}')">Edit</button>@endif
                        @if ($dstatus !== 'REVOKED' && auth_staff()->can('device.revoke'))<form method="POST" action="{{ route('devices.revoke', $d['id']) }}" x-data="confirmSubmit('Revoke {{ e($devName) }}? It stops working immediately.')" @submit="ask($event)">@csrf<button class="w-full text-left text-red-800">Revoke</button></form>@endif
                    </x-row-menu>@endif</td></tr>
            @empty<tr><td colspan="8"><x-empty title="No devices" text="Register a tablet, POS terminal or screen with a one-time code." icon="device" /></td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    @if ($canManage && $devices->ok())@foreach ($devices->items() as $d)@if (! empty($d['id']))@include('partials.device-edit-dialog', ['d' => $d, 'back' => '/devices'])@endif @endforeach @endif
    @endif

    @if (auth_staff()->can('device.register'))
        <x-dialog name="register-device" title="Register a new device" subtitle="Issue a one-time code, then enter it on the device. The device receives its own credential; this portal never sees it.">
            <form method="POST" action="{{ route('devices.code') }}" class="grid gap-4" novalidate>@csrf
                <x-form.select name="facilityId" label="Home facility (optional)" :options="collect($facilities)->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name']])->values()->all()" :clearable="true" placeholder="Any facility" />
                <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Issue registration code</x-btn></div>
            </form>
        </x-dialog>
    @endif

    @if ($attendance->state !== 'forbidden')
        <x-card title="Attendance terminals (biometric)" subtitle="Fingerprint / face clock-in terminals. The token is shown once, when you register or rotate." flush x-data="tableTools">
            <x-slot:aside>@if (auth_staff()->can('attendance.device.manage'))<x-btn type="button" icon="plus" @click="$dispatch('open-modal', 'add-terminal')">Register terminal</x-btn>@endif</x-slot:aside>
            <x-fetch :of="$attendance" what="Attendance terminals" />
            @if ($attendance->ok())
                <div class="table-scroll"><table class="data-table"><thead><tr><th>Serial</th><th>Name</th><th>Adapter</th><th>Facility</th><th>Status</th><th>Last seen</th><th>Last punch</th><th class="w-12"></th></tr></thead><tbody>
                @forelse ($attendance->items() as $t)
                    @php $tStatus = $t['status'] ?? 'UNKNOWN'; @endphp
                    <tr data-row><td class="font-medium">{{ $t['serialNumber'] ?? '-' }}</td><td>{{ $t['name'] ?? '' }}</td><td class="text-stone-600">{{ $t['adapter'] ?? '' }}</td><td>{{ $facilityNames[$t['facilityId'] ?? ''] ?? '-' }}</td><td><x-badge :status="$tStatus" /></td><td><x-time :at="$t['lastSeenAt'] ?? null" ago /></td><td><x-time :at="$t['lastPunchAt'] ?? null" ago /></td>
                        <td class="text-right">@if (! empty($t['id']) && auth_staff()->can('attendance.device.manage'))<x-row-menu>
                            <form method="POST" action="{{ route('devices.terminal.status', $t['id']) }}">@csrf<input type="hidden" name="status" value="{{ $tStatus === 'ACTIVE' ? 'DISABLED' : 'ACTIVE' }}"><button class="w-full text-left">{{ $tStatus === 'ACTIVE' ? 'Disable' : 'Enable' }}</button></form>
                            <form method="POST" action="{{ route('devices.terminal.rotate', $t['id']) }}" x-data="confirmSubmit('Rotate the token? The terminal stops working until it is reconfigured.')" @submit="ask($event)">@csrf<button class="w-full text-left">Rotate token</button></form>
                        </x-row-menu>@endif</td></tr>
                @empty<tr><td colspan="8"><x-empty title="No terminals" text="Register a ZKTeco terminal to clock staff in by fingerprint." icon="clock" /></td></tr>@endforelse
                </tbody></table></div>
            @endif
        </x-card>
        @if (auth_staff()->can('attendance.device.manage'))
            <x-dialog name="add-terminal" title="Register an attendance terminal">
                <form method="POST" action="{{ route('devices.terminal.create') }}" class="grid gap-4" novalidate>@csrf
                    <x-form.text name="serialNumber" label="Serial number" required :maxlength="64" hint="Printed on the terminal or shown in its menu." />
                    <x-form.text name="name" label="Name" required :maxlength="120" placeholder="e.g. Main gate" />
                    <x-form.select name="facilityId" label="Facility" :options="collect($facilities)->map(fn ($f) => ['value' => $f['id'], 'label' => $f['name']])->values()->all()" :clearable="true" placeholder="Not assigned" />
                    <x-form.segmented name="adapter" label="Protocol" :options="['ZKTECO_ADMS' => 'ZKTeco (ADMS)', 'JSON_PUSH' => 'JSON push']" value="ZKTECO_ADMS" />
                    <div class="flex justify-end gap-2"><x-btn type="button" variant="secondary" @click="open = false">Cancel</x-btn><x-btn>Register terminal</x-btn></div>
                </form>
            </x-dialog>
        @endif
    @endif
</x-layouts.app>
