<x-layouts.app title="Attendance">
    <x-page-header title="Attendance" subtitle="Clock-ins from the gate terminal, and correction requests.">
        <x-slot:actions><x-btn variant="secondary" :href="request()->fullUrlWithQuery(['format' => 'csv'])">Export CSV</x-btn></x-slot:actions>
    </x-page-header>
    <x-staff-nav />
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div><label class="mb-1 block text-sm font-medium">From</label><input type="date" name="from" value="{{ $from }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium">To</label><input type="date" name="to" value="{{ $to }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium">Status</label><select name="status" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">Any</option>@foreach (['OPEN', 'CLOSED', 'NEEDS_REVIEW'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div>
        <x-btn variant="secondary">Apply</x-btn>
    </form>
    <x-card flush>
        <x-fetch :of="$records" what="Attendance" />
        @if ($records->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="attendance-table"><thead><tr><th>Date</th><th>Staff</th><th>In</th><th>Out</th><th class="text-right">Minutes</th><th>Status</th></tr></thead><tbody>
            @forelse ($records->items() as $r)<tr><td>{{ $r['workDate'] }}</td><td>{{ $r['staffName'] ?? $r['staffId'] }}</td><td><x-time :at="$r['clockIn'] ?? null" /></td><td><x-time :at="$r['clockOut'] ?? null" /></td><td class="text-right tabular-nums">{{ $r['minutesWorked'] ?? '' }}</td><td><x-badge :status="$r['status']" /></td></tr>@empty<tr><td colspan="6" class="text-center text-stone-500">No records.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
    <x-card title="Pending corrections" flush>
        <x-fetch :of="$corrections" what="Corrections" />
        @if ($corrections->ok())
            <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Date</th><th>Staff</th><th>Requested times</th><th>Reason</th><th></th></tr></thead><tbody>
            @forelse ($corrections->items() as $c)
                <tr><td>{{ $c['workDate'] }}</td><td class="text-xs">{{ $c['staffId'] }}</td><td><x-time :at="$c['clockIn'] ?? null" /> &ndash; <x-time :at="$c['clockOut'] ?? null" /></td><td>{{ $c['reason'] }}</td>
                    <td>@if (auth_staff()->can('staff.clock_correction.approve'))<div class="flex gap-2">
                        <form method="POST" action="{{ route('staff.correction', [$c['id'], 'approve']) }}">@csrf<x-btn class="min-h-10">Approve</x-btn></form>
                        <form method="POST" action="{{ route('staff.correction', [$c['id'], 'reject']) }}">@csrf<x-btn variant="secondary" class="min-h-10">Reject</x-btn></form></div>@endif</td></tr>
            @empty<tr><td colspan="5" class="text-center text-stone-500">No pending corrections.</td></tr>@endforelse
            </tbody></table></div>
        @endif
    </x-card>
</x-layouts.app>
