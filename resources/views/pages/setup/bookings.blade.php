<x-layouts.app title="Booking rules">
    <x-page-header title="Booking resources & rules" subtitle="Offline-allocation strategy decides what happens to a resource when Local cannot reach Cloud." />
    <x-card title="Resources" flush x-data="tableTools">
        <x-table-tools />
        <x-fetch :of="$resources" what="Booking resources" />
        @if ($resources->ok())
            <div class="table-scroll"><table class="data-table" data-testid="resources"><thead><tr><th>Resource</th><th>Facility</th><th>Mode</th><th>Capacity</th><th>Slot</th><th class="text-right">Price</th><th>Online</th><th>Booking authority (offline strategy)</th></tr></thead><tbody>
            @forelse ($resources->items() as $r)
                @php $a = (array) ($r['authority'] ?? []); $strategy = $a['offlineStrategy'] ?? null; @endphp
                <tr data-row><td class="font-medium">{{ $r['name'] ?? '' }}<div class="text-xs font-normal text-stone-500">{{ $r['code'] ?? '' }}</div></td><td>{{ $facilityNames[$r['facilityId'] ?? ''] ?? '' }}</td><td>{{ str_replace('_', ' ', $r['mode'] ?? '') }}</td><td>{{ $r['capacity'] ?? 1 }}</td><td>{{ $r['slotMinutes'] ?? '-' }} min</td><td class="text-right"><x-money :value="$r['price'] ?? '0'" /></td>
                    <td>{{ ($r['onlineBookable'] ?? false) ? 'Yes' : 'No' }}</td>
                    <td>
                        @if ($canWrite && ! empty($r['id']))
                            <form method="POST" action="{{ route('setup.bookings.update', $r['id']) }}" class="flex flex-wrap items-center gap-2">@csrf @method('PATCH')
                                <select name="offlineStrategy" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm">@foreach (\App\Http\Controllers\ConfigurationController::STRATEGIES as $k => $l)<option value="{{ $k }}" @selected(($strategy ?? 'A_OFFLINE_ALLOCATION') === $k)>{{ $l }}</option>@endforeach</select>
                                <input name="localReserveUnits" value="{{ $a['localReserveUnits'] ?? '' }}" placeholder="Local reserve" inputmode="numeric" aria-label="Local reserve units" class="min-h-10 w-28 rounded-lg border border-stone-300 px-2 text-sm">
                                <input name="onlineStaleAfterSeconds" value="{{ $a['onlineStaleAfterSeconds'] ?? '' }}" placeholder="Stale after (s)" inputmode="numeric" aria-label="Online stale after seconds" class="min-h-10 w-32 rounded-lg border border-stone-300 px-2 text-sm">
                                <label class="text-xs"><input type="checkbox" name="onlineBookable" value="1" @checked($r['onlineBookable'] ?? false)> online</label>
                                <input type="hidden" name="active" value="1">
                                <x-btn class="min-h-10">Save</x-btn></form>
                        @else
                            {{ $strategy ? (\App\Http\Controllers\ConfigurationController::STRATEGIES[$strategy] ?? $strategy) : 'Not reported by the API' }}@if (isset($a['localReserveUnits'])) &middot; reserve {{ $a['localReserveUnits'] }}@endif
                        @endif
                    </td></tr>
            @empty<tr><td colspan="8" class="text-center text-stone-500">No resources.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    <x-card title="What the strategies mean">
        <dl class="space-y-2 text-sm">
            <div><dt class="font-semibold">A. Offline allocation (default)</dt><dd class="text-stone-600">Capacity is split into a Cloud pool and a Local offline reserve (the "local reserve"), so Reception keeps booking at normal speed during an outage without any chance of double booking.</dd></div>
            <div><dt class="font-semibold">B. Online authority required</dt><dd class="text-stone-600">Reception must reach Cloud to confirm this resource ("requires connectivity to book"). Other on-site operations are unaffected.</dd></div>
            <div><dt class="font-semibold">C. Pause online availability</dt><dd class="text-stone-600">The website stops offering this resource once the site heartbeat is staler than "stale after" seconds.</dd></div>
        </dl>
    </x-card>
    @unless ($canWrite)
        <x-pending-api title="Not offered to this account" :items="['Editing booking rules and the offline strategy needs the booking.configure permission']" />
    @endunless
    <x-pending-api :items="['Creating a resource (POST /bookings/resources) and blackout periods (POST /bookings/resources/{id}/blackouts) exist in the API; forms are not built yet']" />
</x-layouts.app>
