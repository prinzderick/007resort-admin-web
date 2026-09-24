<x-card title="Devices at this facility" subtitle="Tablets, POS terminals and screens whose home is here, or that are checked out here right now." flush>
    <x-slot:aside><x-btn variant="secondary" :href="route('devices.index')">All devices</x-btn></x-slot:aside>
    <x-fetch :of="$devices" what="Devices" />
    @if ($devices->ok())
        <div class="table-scroll"><table class="data-table" data-testid="fac-devices"><thead><tr><th>Device</th><th>Does</th><th>Status</th><th>Last seen</th><th class="w-12"></th></tr></thead><tbody>
        @forelse ($devices->items() as $d)<tr><td><div class="font-medium">{{ $d['name'] ?? '' }}</div><div class="text-xs text-stone-500">{{ str_replace('_', ' ', $d['kind'] ?? '') }}</div></td><td>@if (! empty($d['mode']))<x-badge tone="info" :dot="false">{{ ucwords(strtolower(str_replace('_', ' ', $d['mode']))) }}</x-badge>@endif</td><td><x-badge :status="$d['status'] ?? 'UNKNOWN'" /></td><td><x-time :at="$d['lastSeenAt'] ?? null" ago /></td>
            <td class="text-right">@if ($canDevices && ! empty($d['id']))@php $out = ! empty($d['checkout']) && empty($d['checkout']['checkedInAt']); @endphp<button type="button" class="text-sm font-medium text-brand-700 underline" @click="$dispatch('open-modal', { name: 'edit-device', data: {{ \Illuminate\Support\Js::from(['id' => $d['id'], 'name' => $d['name'] ?? '', 'kind' => ucwords(strtolower(str_replace('_', ' ', $d['kind'] ?? ''))), 'rowVersion' => $d['rowVersion'] ?? '', 'facilityId' => $d['homeFacilityId'] ?? ($d['homeFacility']['id'] ?? null), 'mode' => $d['mode'] ?? null, 'operatingPointId' => $d['operatingPointId'] ?? null, 'checkedOut' => $out]) }} })">Edit</button>@endif</td></tr>
        @empty<tr><td colspan="5"><x-empty title="No devices here" text="Register a device on the Devices page and choose this facility as its home." icon="device" /></td></tr>@endforelse
        </tbody></table></div>
    @endif
</x-card>
@if ($canDevices && $devices->ok())@include('partials.device-edit-dialog', ['facilities' => $flat, 'back' => '/setup/facilities/'.$id.'?tab=devices'])@endif
