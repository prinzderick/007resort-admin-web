<x-layouts.app title="Booking rules">
    <x-page-header title="Booking resources & rules" subtitle="Offline-allocation strategy decides what happens to a resource when Local cannot reach Cloud." />
    <x-config-nav />
    <x-card title="Resources" flush>
        <x-fetch :of="$resources" what="Booking resources" />
        @if ($resources->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="resources"><thead><tr><th>Resource</th><th>Facility</th><th>Mode</th><th>Capacity</th><th>Slot</th><th class="text-right">Price</th><th>Offline strategy</th></tr></thead><tbody>
            @forelse ($resources->items() as $r)
                <tr><td class="font-medium">{{ $r['name'] }}</td><td>{{ $facilityNames[$r['facilityId']] ?? '' }}</td><td>{{ str_replace('_', ' ', $r['mode']) }}</td><td>{{ $r['capacity'] ?? 1 }}</td><td>{{ $r['slotMinutes'] }} min</td><td class="text-right"><x-money :value="$r['price']" /></td>
                    <td>
                        @if ($canWrite && auth_staff()->canAny('facility.configure', 'config.manage'))
                            <form method="POST" action="{{ route('config.bookings.update', $r['id']) }}" class="flex flex-wrap items-center gap-2">@csrf @method('PATCH')
                                <select name="offlineAllocationStrategy" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm">@foreach (['A' => 'A: offline allocation', 'B' => 'B: online authority required', 'C' => 'C: pause online availability'] as $k => $l)<option value="{{ $k }}" @selected(($r['offlineAllocationStrategy'] ?? 'A') === $k)>{{ $l }}</option>@endforeach</select>
                                <input name="offlineReserveCapacity" value="{{ $r['offlineReserveCapacity'] ?? '' }}" placeholder="Local reserve" class="min-h-10 w-28 rounded-lg border border-stone-300 px-2 text-sm">
                                <input name="onlineStalenessThresholdSeconds" value="{{ $r['onlineStalenessThresholdSeconds'] ?? '' }}" placeholder="Stale after (s)" class="min-h-10 w-32 rounded-lg border border-stone-300 px-2 text-sm">
                                <x-btn class="min-h-10">Save</x-btn></form>
                        @else
                            {{ isset($r['offlineAllocationStrategy']) ? 'Strategy '.$r['offlineAllocationStrategy'] : 'Not reported by the API' }}
                        @endif
                    </td></tr>
            @empty<tr><td colspan="7" class="text-center text-stone-500">No resources.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    <x-card title="What the strategies mean">
        <dl class="space-y-2 text-sm">
            <div><dt class="font-semibold">A. Offline allocation (default)</dt><dd class="text-stone-600">Capacity is split into a Cloud pool and a Local offline reserve, so Reception keeps booking at normal speed during an outage without any chance of double booking.</dd></div>
            <div><dt class="font-semibold">B. Online authority required</dt><dd class="text-stone-600">Reception must reach Cloud to confirm this resource ("requires connectivity to book"). Other on-site operations are unaffected.</dd></div>
            <div><dt class="font-semibold">C. Pause online availability</dt><dd class="text-stone-600">The website stops offering this resource once the site heartbeat is staler than a threshold.</dd></div>
        </dl>
    </x-card>
    @unless ($canWrite)
        <x-pending-api :items="['Edit slot length, capacity, price and booking rules per resource (no booking-resource write endpoint in the contract)', 'Set the offline-allocation strategy A/B/C, Local reserve and staleness threshold per resource (form is built and appears as soon as PATCH /bookings/resources/{resourceId} is in the contract)']" />
    @endunless
</x-layouts.app>
