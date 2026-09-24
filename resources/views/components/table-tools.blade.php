@props(['csv' => false, 'placeholder' => 'Filter rows on this page...'])
{{-- Sits inside an element with x-data="tableTools". Server-side filters go in the slot (a GET form); this adds the quick filter and the CSV export. --}}
<div class="flex flex-wrap items-end justify-between gap-3 border-b border-stone-100 px-4 py-3">
    <div class="flex flex-wrap items-end gap-3">{{ $slot }}</div>
    <div class="flex items-center gap-2">
        <div class="relative"><x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" /><input type="search" x-model="q" @input="filter()" placeholder="{{ $placeholder }}" aria-label="Filter rows on this page" class="min-h-10 w-56 rounded-lg border border-stone-200 pl-8 pr-2 text-sm focus:border-brand-500 focus:outline-none"></div>
        @if ($csv)<x-btn variant="secondary" icon="download" :href="request()->fullUrlWithQuery(['format' => 'csv'])">CSV</x-btn>@endif
    </div>
</div>
