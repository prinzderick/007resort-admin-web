@props(['ignore' => [], 'labels' => []])
@php
    // Removable chips for whatever filters are in the address bar, plus "Clear all".
    $skip = array_merge(['cursor', 'format', 'page', 'tab', 'per', 'range', 'from', 'to'], $ignore);
    $active = collect(request()->query())->reject(fn ($v, $k) => in_array($k, $skip, true) || $v === null || $v === '' || is_array($v) && ! collect($v)->filter(fn ($x) => $x !== '' && $x !== null)->count())->all();
    $chips = [];
    foreach ($active as $k => $v) {
        if (is_array($v)) { foreach ($v as $kk => $vv) { if ($vv !== '' && $vv !== null) { $chips[] = [$k.'['.$kk.']', $k, $kk, $vv]; } } } else { $chips[] = [$k, $k, null, $v]; }
    }
@endphp
@if ($chips !== [])
    <div class="flex flex-wrap items-center gap-2 border-b border-stone-100 px-4 py-2" data-component="filter-bar" data-testid="filter-chips">
        @foreach ($chips as [$label, $k, $kk, $v])
            @php
                $q = request()->query();
                if ($kk === null) { unset($q[$k]); } else { unset($q[$k][$kk]); if (empty($q[$k])) { unset($q[$k]); } }
                unset($q['cursor']);
                $name = $labels[$k] ?? ($kk !== null ? $kk : $k);
                $shown = \Illuminate\Support\Str::limit((string) $v, 18, '...');
            @endphp
            <a href="{{ request()->url().($q ? '?'.http_build_query($q) : '') }}" class="inline-flex min-h-8 items-center gap-1.5 rounded-full bg-stone-100 px-3 text-xs font-medium text-stone-700 hover:bg-stone-200" title="Remove this filter"><span class="text-stone-500">{{ ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $name)) }}:</span> {{ $shown }} <span aria-hidden="true">&times;</span><span class="sr-only">remove</span></a>
        @endforeach
        <a href="{{ request()->url().(request()->has('range') || request()->has('from') ? '?'.http_build_query(request()->only(['range', 'from', 'to'])) : '') }}" class="text-xs font-medium text-brand-700 underline">Clear all</a>
    </div>
@endif
