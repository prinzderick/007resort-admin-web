<x-layouts.app title="Stock counts">
    <x-page-header title="Stock counts" subtitle="Physical count sheets. Posting one writes the variance as stock movements (large variances wait for approval).">
        <x-slot:actions>@if (auth_staff()->can('inventory.count.create'))<x-btn variant="secondary" :href="route('inventory.form', 'count')">New count</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-inventory-nav />
    <form method="GET" class="mb-5 flex items-end gap-3"><div><label class="mb-1 block text-sm font-medium">Status</label>
        <select name="status" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm" onchange="this.form.submit()"><option value="">Any</option>@foreach (['DRAFT', 'POSTED'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div></form>
    <x-card flush>
        <x-fetch :of="$counts" what="Stock counts" />
        @if ($counts->ok())
            <div class="overflow-x-auto"><table class="data-table" data-testid="counts-table"><thead><tr><th>Created</th><th>Location</th><th>Lines</th><th>Status</th><th>Posted</th><th></th></tr></thead><tbody>
            @forelse ($counts->items() as $c)
                <tr><td><x-time :at="$c['createdAt'] ?? null" /></td><td>{{ $locNames[$c['locationId'] ?? ''] ?? '' }}</td><td>{{ count($c['lines'] ?? []) }}</td><td><x-badge :status="$c['status'] ?? 'UNKNOWN'" /></td><td><x-time :at="$c['postedAt'] ?? null" /></td>
                    <td>@if (! empty($c['id']))<a class="underline" href="{{ route('inventory.count.show', $c['id']) }}">Open</a>@endif</td></tr>
            @empty<tr><td colspan="6" class="text-center text-stone-500">No counts yet.</td></tr>@endforelse
            </tbody></table></div>
            @if ($counts->next())<div class="p-3"><x-btn variant="secondary" :href="request()->fullUrlWithQuery(['cursor' => $counts->next()])">Older counts</x-btn></div>@endif
        @endif
    </x-card>
</x-layouts.app>
