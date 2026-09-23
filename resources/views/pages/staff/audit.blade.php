<x-layouts.app title="Audit trail">
    <x-page-header title="Audit trail" subtitle="Append-only, hash-chained record of sensitive actions.">
        <x-slot:actions><x-btn variant="secondary" :href="request()->fullUrlWithQuery(['format' => 'csv'])">Export CSV</x-btn></x-slot:actions>
    </x-page-header>
    <x-staff-nav />
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div><label class="mb-1 block text-sm font-medium">Action contains</label><input name="action" value="{{ $q['action'] ?? '' }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium">Entity type</label><input name="entityType" value="{{ $q['entityType'] ?? '' }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium">From</label><input type="date" name="from" value="{{ $q['from'] ?? '' }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium">To</label><input type="date" name="to" value="{{ $q['to'] ?? '' }}" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm"></div>
        <x-btn variant="secondary">Filter</x-btn>
    </form>
    <x-card flush>
        <x-fetch :of="$audit" what="Audit trail" />
        @if ($audit->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="audit-table"><thead><tr><th>#</th><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>Change</th></tr></thead><tbody>
            @forelse ($audit->items() as $a)
                <tr><td>{{ $a['seq'] }}</td><td><x-time :at="$a['occurredAt']" /></td><td>{{ $a['actorName'] ?? 'system' }}</td><td class="font-medium">{{ $a['action'] }}</td><td>{{ $a['entityType'] }}<div class="text-xs text-stone-500">{{ $a['entityId'] ?? '' }}</div></td>
                    <td class="text-xs">@if ($a['before'] ?? null)<div class="text-red-800">- {{ json_encode($a['before']) }}</div>@endif @if ($a['after'] ?? null)<div class="text-emerald-800">+ {{ json_encode($a['after']) }}</div>@endif @if ($a['reason'] ?? null)<div>{{ $a['reason'] }}</div>@endif</td></tr>
            @empty<tr><td colspan="6" class="text-center text-stone-500">No entries.</td></tr>@endforelse
            </tbody></table></div>
            @if ($audit->next())<div class="p-3"><x-btn variant="secondary" :href="request()->fullUrlWithQuery(['cursor' => $audit->next()])">Older entries</x-btn></div>@endif
        @endif
    </x-card>
</x-layouts.app>
