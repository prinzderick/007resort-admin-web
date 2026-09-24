<x-layouts.app title="Memberships" :range="true">
    <x-page-header title="Memberships" subtitle="Members, their plan, validity and visits. Plans (prices, limits, facilities) are managed under Setup.">
        <x-slot:actions>@if (auth_staff()->canAny('membership.plan.manage', 'config.manage'))<x-btn variant="secondary" :href="route('setup.memberships')" icon="edit">Manage plans</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-fetch :of="$summary" what="Membership summary" />
    @if ($summary->ok())
        @php $sm = (array) $summary->data; @endphp
        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach (($sm['byStatus'] ?? []) as $r)<x-stat :label="'Members '.strtolower((string) ($r['status'] ?? ''))" :value="$r['count'] ?? 0" />@endforeach
            <x-stat label="Expiring within {{ $sm['expiringWithinDays'] ?? 7 }} days" :value="$sm['expiring'] ?? 0" :tone="($sm['expiring'] ?? 0) > 0 ? 'warn' : 'default'" />
            <x-stat label="Plans" :value="count($plans->items())" />
        </div>
    @endif
    <x-card flush x-data="tableTools">
        <x-table-tools placeholder="Filter this page...">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div><label class="mb-1 block text-xs font-medium text-stone-600">Search number / name / phone</label><input name="q" value="{{ $q }}" class="min-h-10 rounded-lg border border-stone-300 px-2 text-sm"></div>
                <div><label class="mb-1 block text-xs font-medium text-stone-600">Status</label><select name="status" class="min-h-10 rounded-lg border border-stone-300 bg-white px-2 text-sm"><option value="">Any</option>@foreach (['ACTIVE', 'SUSPENDED', 'EXPIRED', 'CANCELLED'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div>
                <x-btn variant="secondary">Apply</x-btn>
            </form>
        </x-table-tools>
        <x-fetch :of="$members" what="Members" />
        @if ($members->ok())
            <div class="table-scroll"><table class="data-table" data-testid="members-table">
                <thead><tr><th data-sort>Member</th><th data-sort>Plan</th><th>Status</th><th>Valid</th><th class="text-right">Visits</th><th class="text-right">Discount</th><th></th></tr></thead>
                <tbody x-ref="body">
                @forelse ($members->items() as $m)
                    <tr data-row><td class="font-medium">{{ $m['holderName'] ?? '-' }}<div class="text-xs font-normal text-stone-500">{{ $m['number'] ?? '' }}</div></td><td>{{ $m['planName'] ?? '' }}</td><td><x-badge :status="$m['status'] ?? 'UNKNOWN'" />@if ($m['inGrace'] ?? false) <x-badge tone="warn">grace</x-badge>@endif</td>
                        <td class="text-xs"><x-time :at="$m['validFrom'] ?? null" /> &ndash; <x-time :at="$m['validUntil'] ?? null" /></td>
                        <td class="text-right tabular-nums">{{ $m['visitsUsed'] ?? 0 }}{{ isset($m['visitLimit']) ? ' / '.$m['visitLimit'] : '' }}</td><td class="text-right">{{ $m['memberDiscountPercent'] ?? '0' }}%</td>
                        <td class="text-right"><x-detail :title="($m['holderName'] ?? 'Member').' - '.($m['number'] ?? '')" :fields="[['Plan', $m['planName'] ?? ''], ['Status', $m['status'] ?? ''], ['Valid from', \App\Support\Time::format($m['validFrom'] ?? null)], ['Valid until', \App\Support\Time::format($m['validUntil'] ?? null)], ['Grace until', \App\Support\Time::format($m['graceUntil'] ?? null)], ['Visits used', ($m['visitsUsed'] ?? 0).(isset($m['visitLimit']) ? ' of '.$m['visitLimit'] : ' (unlimited)')], ['Guests per visit', $m['guestAllowance'] ?? 0], ['Price paid', \App\Support\Money::format($m['pricePaid'] ?? '0')], ['Renewals', $m['renewalCount'] ?? 0]]" /></td></tr>
                @empty<tr><td colspan="7"><x-empty title="No members yet" text="Members are signed up at Reception. Create or adjust the plans they can buy under Setup > Membership plans." icon="users" :action="auth_staff()->can('membership.plan.manage') ? 'Manage plans' : null" :href="route('setup.memberships')" /></td></tr>@endforelse
                </tbody></table></div>
            <x-pagination :count="count($members->items())" :next="$members->next()" />
        @endif
    </x-card>
</x-layouts.app>
