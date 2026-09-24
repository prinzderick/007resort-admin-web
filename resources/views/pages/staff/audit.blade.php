<x-layouts.app title="Audit trail">
    <x-page-header title="Audit trail" subtitle="Append-only, hash-chained record of sensitive actions.">
        <x-slot:actions><x-btn variant="secondary" :href="request()->fullUrlWithQuery(['format' => 'csv'])">Export CSV</x-btn></x-slot:actions>
    </x-page-header>
    <x-filter-form :reset="route('audit.index')">
        @if (! empty($q['entityId']))<input type="hidden" name="entityId" value="{{ $q['entityId'] }}"><input type="hidden" name="entityType" value="{{ $q['entityType'] ?? '' }}"><span class="mb-2 inline-flex items-center gap-2 rounded-full bg-sky-50 px-3 py-1.5 text-xs font-medium text-sky-900">History of one {{ strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $q['entityType'] ?? 'item')) }}</span>@endif
        <x-filter-select name="area" label="What changed" :options="$areas" :value="$q['area'] ?? null" all="Everything" width="15rem" />
        <x-filter-select name="actor" label="Who" :options="$names" :value="$q['actor'] ?? null" all="Anyone" width="12rem" />
        <x-filter-text name="action" label="Action" :value="$q['action'] ?? null" placeholder="e.g. payment.refund" width="12rem" />
        <x-filter-date name="from" label="From" :value="$q['from'] ?? null" />
        <x-filter-date name="to" label="To" :value="$q['to'] ?? null" />
    </x-filter-form>
    <x-card flush x-data="tableTools">
        <x-table-tools :csv="true" />
        <x-fetch :of="$audit" what="Audit trail" />
        @if ($audit->ok())
            <div class="table-scroll"><table class="data-table" data-testid="audit-table"><thead><tr><th>#</th><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>Change</th></tr></thead><tbody>
            @forelse ($audit->items() as $a)
                @php $actor = $a['actorStaffId'] ?? null; @endphp
                <tr data-row><td>{{ $a['seq'] ?? '' }}</td><td><x-time :at="$a['occurredAt'] ?? null" /></td><td>{{ $actor ? ($names[$actor] ?? \App\Services\Portal\Directory::short($actor)) : (! empty($a['deviceId']) ? 'device' : 'system') }}</td><td class="font-medium">{{ $a['action'] ?? '' }}@if (! empty($a['approvalId']))<div class="text-xs font-normal text-stone-500">approval {{ \App\Services\Portal\Directory::short($a['approvalId']) }}</div>@endif</td><td>{{ $a['entityType'] ?? '' }}<div class="text-xs text-stone-500">{{ $a['entityId'] ?? '' }}</div></td>
                    <td class="max-w-lg break-words text-xs">
                        @php
                            $old = (array) ($a['oldValue'] ?? []); $new = (array) ($a['newValue'] ?? []);
                            $show = fn ($v) => is_bool($v) ? ($v ? 'on' : 'off') : (is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES) : ($v === null ? 'none' : (string) $v));
                            $keys = array_values(array_unique(array_merge(array_keys($new), array_keys($old))));
                            $changed = array_values(array_filter($keys, fn ($k) => ($old[$k] ?? null) !== ($new[$k] ?? null)));
                        @endphp
                        @if ($old === [] && $new !== [])
                            @foreach (array_slice($new, 0, 5, true) as $k => $v)@continue(in_array($k, ['id', 'rowVersion'], true) || is_array($v))<div class="leading-5"><span class="text-stone-500">{{ trim(preg_replace('/(?<!^)[A-Z]/', ' $0', str_replace('_', ' ', $k))) }}</span> <span class="font-medium text-stone-800">{{ \Illuminate\Support\Str::limit($show($v), 48) }}</span></div>@endforeach
                            <span class="text-stone-400">created</span>
                        @elseif ($changed !== [] && count($changed) <= 6)
                            @foreach ($changed as $k)<div class="leading-5"><span class="font-medium text-stone-700">{{ trim(preg_replace('/(?<!^)[A-Z_]/', ' $0', str_replace('_', ' ', $k))) }}</span>: <span class="text-red-800 line-through decoration-red-300">{{ \Illuminate\Support\Str::limit($show($old[$k] ?? null), 40) }}</span> <span aria-hidden="true">&rarr;</span> <span class="font-medium text-brand-800">{{ \Illuminate\Support\Str::limit($show($new[$k] ?? null), 60) }}</span></div>@endforeach
                        @elseif ($changed !== [])
                            <details><summary class="cursor-pointer text-stone-600">{{ count($changed) }} fields changed</summary><pre class="mt-1 whitespace-pre-wrap text-[11px]">{{ json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                        @else<span class="text-stone-400">-</span>@endif
                    </td></tr>
            @empty<tr><td colspan="6" class="text-center text-stone-500">No entries.</td></tr>@endforelse
            </tbody></table></div>
            <x-pagination :count="count($audit->items())" :next="$audit->next()" />
        @endif
    </x-card>
</x-layouts.app>
