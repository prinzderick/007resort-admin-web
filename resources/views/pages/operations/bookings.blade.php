<x-layouts.app title="Bookings" :range="true">
    <x-page-header title="Bookings" subtitle="Court, pitch, hall and salon bookings whose start time is in the selected period or the following month." />
    <x-card flush x-data="tableTools">
        <x-table-tools :csv="true" placeholder="Filter this page...">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                @foreach (request()->only(['from', 'to', 'range']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <div><label class="mb-1 block text-xs font-medium text-stone-600">Search booking / customer</label><input name="q" value="{{ $q }}" class="min-h-10 rounded-lg border border-stone-300 px-2 text-sm"></div>
                <div><label class="mb-1 block text-xs font-medium text-stone-600">Facility</label><select name="facility" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm"><option value="">All</option>@foreach ($facilities as $f)<option value="{{ $f['id'] ?? '' }}" @selected($facilityId === ($f['id'] ?? ''))>{{ $f['name'] ?? '' }}</option>@endforeach</select></div>
                <div><label class="mb-1 block text-xs font-medium text-stone-600">Status</label><select name="status" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm"><option value="">Any</option>@foreach (['HELD', 'CONFIRMED', 'CANCELLED', 'EXPIRED', 'COMPLETED'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div>
                <x-btn variant="secondary">Apply</x-btn>
            </form>
        </x-table-tools>
        <x-fetch :of="$bookings" what="Bookings" />
        @if ($bookings->ok())
            <div class="table-scroll"><table class="data-table" data-testid="bookings-table">
                <thead><tr><th data-sort>Booking</th><th data-sort>Resource</th><th>Facility</th><th data-sort>Starts</th><th>Customer</th><th>Status</th><th class="text-right">Total</th><th class="text-right">Paid</th><th></th></tr></thead>
                <tbody x-ref="body">
                @forelse ($bookings->items() as $b)
                    <tr data-row><td class="font-medium">{{ $b['number'] ?? '' }}</td><td>{{ $b['resourceName'] ?? '' }}@if (($b['quantity'] ?? 1) > 1) <span class="text-xs text-stone-500">x{{ $b['quantity'] }}</span>@endif</td><td>{{ $facilityNames[$b['facilityId'] ?? ''] ?? '' }}</td>
                        <td data-sort="{{ $b['start'] ?? '' }}"><x-time :at="$b['start'] ?? null" /></td><td>{{ $b['customer']['name'] ?? '-' }}<div class="text-xs text-stone-500">{{ $b['customer']['phone'] ?? '' }}</div></td><td><x-badge :status="$b['status'] ?? 'UNKNOWN'" /></td>
                        <td class="text-right"><x-money :value="$b['total'] ?? '0'" /></td><td class="text-right"><x-money :value="$b['amountPaid'] ?? '0'" /></td>
                        <td class="text-right"><x-detail :title="'Booking '.($b['number'] ?? '')" :fields="[['Resource', $b['resourceName'] ?? ''], ['Facility', $facilityNames[$b['facilityId'] ?? ''] ?? ''], ['Starts', \App\Support\Time::format($b['start'] ?? null)], ['Ends', \App\Support\Time::format($b['end'] ?? null)], ['Status', $b['status'] ?? ''], ['Customer', $b['customer']['name'] ?? '-'], ['Phone', $b['customer']['phone'] ?? '-'], ['Total', \App\Support\Money::format($b['total'] ?? '0')], ['Paid', \App\Support\Money::format($b['amountPaid'] ?? '0')], ['Source', $b['source'] ?? ''], ['Cancellation fee', \App\Support\Money::format($b['cancellationFee'] ?? '0')]]" /></td></tr>
                @empty<tr><td colspan="9"><x-empty title="No bookings in this period" text="Widen the date range or clear the filters." icon="calendar" /></td></tr>@endforelse
                </tbody></table></div>
            <x-pagination :count="count($bookings->items())" :next="$bookings->next()" />
        @endif
    </x-card>
</x-layouts.app>
