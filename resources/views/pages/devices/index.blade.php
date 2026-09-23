<x-layouts.app title="Devices">
    <x-page-header title="Devices" subtitle="Tablets, POS terminals, KDS screens and scanners registered with the API." />
    @if (session('secret'))
        <div class="mb-5 rounded-xl border-2 border-sky-400 bg-sky-50 p-4" data-testid="secret">
            <div class="text-sm font-semibold">{{ session('secret')['label'] }}</div>
            <div class="my-2 select-all font-mono text-2xl tracking-wider">{{ session('secret')['value'] }}</div>
            <div class="text-sm text-sky-900">{{ session('secret')['note'] }}</div>
        </div>
    @endif

    @if ($devices->state !== 'forbidden')
    <form method="GET" class="mb-4 flex items-end gap-3"><div><label class="mb-1 block text-sm font-medium">Status</label>
        <select name="status" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm" onchange="this.form.submit()"><option value="">All</option>@foreach (['ACTIVE', 'PENDING', 'REVOKED'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div></form>
    <x-card title="Registered devices" flush>
        <x-fetch :of="$devices" what="Devices" />
        @if ($devices->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="devices-table"><thead><tr><th>Device</th><th>Kind</th><th>Status</th><th>Facility</th><th>App</th><th>Last seen</th><th>Checked out to</th><th></th></tr></thead><tbody>
            @forelse ($devices->items() as $d)
                @php
                    $seen = \App\Support\Time::parse($d['lastSeenAt'] ?? null);
                    $quiet = $d['status'] === 'ACTIVE' && (! $seen || $seen->diffInSeconds(now()) > 300);
                @endphp
                <tr><td class="font-medium">{{ $d['name'] }}<div class="text-xs font-normal text-stone-500">{{ $d['platform'] ?? '' }}</div></td><td>{{ str_replace('_', ' ', $d['kind']) }}</td><td><x-badge :status="$d['status']" /></td>
                    <td>{{ $facilityNames[$d['facilityId'] ?? ''] ?? '-' }}</td><td>{{ $d['appVersion'] ?? '' }}</td>
                    <td><x-time :at="$d['lastSeenAt'] ?? null" ago />@if ($quiet)<x-badge tone="warn" class="ml-1">quiet</x-badge>@endif</td>
                    <td class="text-xs">{{ ($d['checkout'] ?? null) && empty($d['checkout']['checkedInAt']) ? \Illuminate\Support\Str::limit($d['checkout']['staffId'], 8, '') : '-' }}</td>
                    <td>@if ($d['status'] !== 'REVOKED' && auth_staff()->can('device.revoke'))<form method="POST" action="{{ route('devices.revoke', $d['id']) }}">@csrf<button class="text-sm text-red-800 underline" onclick="return confirm('Revoke {{ e($d['name']) }}? It will stop working immediately.')">Revoke</button></form>@endif</td></tr>
            @empty<tr><td colspan="8" class="text-center text-stone-500">No devices.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    @endif

    @if (auth_staff()->can('device.register'))
        <x-card title="Register a new device">
            <p class="mb-3 text-sm text-stone-600">Issue a one-time code, then enter it on the device. The device registers itself with the API and receives its own credential; this portal never sees it.</p>
            <form method="POST" action="{{ route('devices.code') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div><label class="mb-1 block text-sm font-medium">Home facility (optional)</label><select name="facilityId" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">Any</option>@foreach ($facilities as $f)<option value="{{ $f['id'] }}">{{ $f['name'] }}</option>@endforeach</select></div>
                <x-btn>Issue registration code</x-btn>
            </form>
        </x-card>
    @endif

    @if ($attendance->state !== 'forbidden')
        <x-card title="Attendance terminals (biometric)" flush>
            <x-fetch :of="$attendance" what="Attendance terminals" />
            @if ($attendance->ok())
                <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Serial</th><th>Adapter</th><th>Status</th><th>Last seen</th><th></th></tr></thead><tbody>
                @forelse ($attendance->items() as $t)
                    <tr><td class="font-medium">{{ $t['serial'] }}</td><td>{{ $t['adapter'] }}</td><td><x-badge :status="$t['status']" /></td><td><x-time :at="$t['lastSeenAt'] ?? null" ago /></td>
                        <td class="flex gap-3">
                            <form method="POST" action="{{ route('devices.terminal.status', $t['id']) }}">@csrf<input type="hidden" name="status" value="{{ $t['status'] === 'ACTIVE' ? 'DISABLED' : 'ACTIVE' }}"><button class="text-sm underline">{{ $t['status'] === 'ACTIVE' ? 'Disable' : 'Enable' }}</button></form>
                            <form method="POST" action="{{ route('devices.terminal.rotate', $t['id']) }}">@csrf<button class="text-sm underline" onclick="return confirm('Rotate the token? The terminal stops working until reconfigured.')">Rotate token</button></form></td></tr>
                @empty<tr><td colspan="5" class="text-center text-stone-500">No terminals.</td></tr>@endforelse
                </tbody></table></div>
            @endif
            <form method="POST" action="{{ route('devices.terminal.create') }}" class="flex flex-wrap items-end gap-3 border-t border-stone-100 p-4">@csrf
                <div><label class="mb-1 block text-sm font-medium">Serial</label><input name="serial" required class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
                <div><label class="mb-1 block text-sm font-medium">Adapter</label><select name="adapter" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm"><option>ZKTECO_ADMS</option><option>JSON_PUSH</option></select></div>
                <x-btn>Register terminal</x-btn></form>
        </x-card>
    @endif
    <x-pending-api :items="['List and revoke individual staff sessions (only POST /auth/sessions/{id}/revoke exists; there is no sessions list)', 'Security events viewer (security_event.view has no endpoint yet)']" />
</x-layouts.app>
