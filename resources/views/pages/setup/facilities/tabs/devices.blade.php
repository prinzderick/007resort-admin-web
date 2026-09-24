<x-card title="Devices at this facility" subtitle="Tablets, POS terminals and screens homed here or currently checked out here" flush>
    <x-fetch :of="$devices" what="Devices" />
    @if ($devices->ok())
        <div class="overflow-x-auto"><table class="data-table" data-testid="fac-devices"><thead><tr><th>Device</th><th>Kind / mode</th><th>Status</th><th>Last seen</th></tr></thead><tbody>
        @forelse ($devices->items() as $d)<tr><td class="font-medium">{{ $d['name'] ?? '' }}</td><td>{{ str_replace('_', ' ', $d['kind'] ?? '') }}<div class="text-xs text-stone-500">{{ $d['mode'] ?? '' }}</div></td><td><x-badge :status="$d['status'] ?? 'UNKNOWN'" /></td><td><x-time :at="$d['lastSeenAt'] ?? null" ago /></td></tr>@empty<tr><td colspan="4"><x-empty title="No devices here" text="Issue a registration code on the Devices page, choosing this facility as the home." icon="device" /></td></tr>@endforelse
        </tbody></table></div>
    @endif
    <div class="border-t border-stone-100 p-4"><x-btn variant="secondary" :href="route('devices.index')">Manage devices</x-btn></div>
</x-card>
