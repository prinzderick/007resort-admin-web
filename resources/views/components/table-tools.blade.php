@props(['csv' => false, 'placeholder' => 'Filter rows on this page...', 'perPage' => true, 'selectable' => true])
{{-- Sits inside an element with x-data="tableTools". Server-side filters go in the slot (a GET form). Adds: quick filter, per-page size,
     density toggle, column show/hide, row selection with a bulk bar, CSV export, and removable filter chips. --}}
<div data-component="table-tools">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b border-stone-100 px-4 py-3">
        <div class="flex flex-wrap items-end gap-3">{{ $slot }}</div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative"><x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" /><input type="search" x-model="q" @input="filter()" placeholder="{{ $placeholder }}" aria-label="Filter rows on this page" class="min-h-10 w-52 rounded-lg border border-stone-200 pl-8 pr-2 text-sm focus:border-brand-500 focus:outline-none"></div>
            @if ($perPage)
                <form method="GET" class="flex items-center gap-1 text-xs text-stone-500">@foreach (request()->except(['per', 'cursor']) as $k => $v)@if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
                    <label for="per-page" class="sr-only">Rows per page</label><select id="per-page" name="per" onchange="this.form.submit()" class="min-h-10 rounded-lg border border-stone-200 bg-white px-2 text-sm text-stone-700">@foreach ([25, 50, 100, 200] as $n)<option value="{{ $n }}" @selected((int) request('per', 100) === $n)>{{ $n }} / page</option>@endforeach</select></form>
            @endif
            <div class="relative" x-data="{ open: false }"><button type="button" @click="open = !open" class="flex min-h-10 items-center gap-1 rounded-lg border border-stone-200 px-3 text-sm text-stone-700 hover:bg-stone-50" aria-haspopup="true" :aria-expanded="open" aria-label="Columns and density"><x-icon name="sliders" class="size-4" /> View</button>
                <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 z-30 mt-1 w-56 rounded-xl border border-stone-200 bg-white p-3 text-sm shadow-lg">
                    <div class="t-label mb-1">Density</div>
                    <div class="mb-3 flex gap-1"><button type="button" @click="setDensity('comfortable')" class="min-h-9 flex-1 rounded-lg border px-2 text-xs" :class="density === 'comfortable' ? 'border-brand-600 bg-brand-50 font-semibold text-brand-800' : 'border-stone-200'">Comfortable</button><button type="button" @click="setDensity('compact')" class="min-h-9 flex-1 rounded-lg border px-2 text-xs" :class="density === 'compact' ? 'border-brand-600 bg-brand-50 font-semibold text-brand-800' : 'border-stone-200'">Compact</button></div>
                    <div class="t-label mb-1">Columns</div>
                    <template x-for="c in columns" :key="c.i"><label class="flex min-h-8 items-center gap-2 text-sm"><input type="checkbox" class="size-4 accent-brand-600" :checked="!c.hidden" @change="toggleCol(c.i)"> <span x-text="c.label"></span></label></template>
                </div></div>
            @if ($csv)<x-btn variant="secondary" icon="download" :href="request()->fullUrlWithQuery(['format' => 'csv'])">CSV</x-btn>@endif
        </div>
    </div>
    <x-filter-bar />
    <div x-cloak x-show="selected > 0" class="flex flex-wrap items-center gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2 text-sm" data-testid="bulk-bar" role="status">
        <b x-text="selected + ' selected'"></b><button type="button" class="font-medium text-brand-800 underline" @click="exportSelected()">Export selected (CSV)</button><button type="button" class="text-stone-600 underline" @click="clearSelection()">Clear selection</button>
    </div>
</div>
