@props(['name', 'title', 'maxWidth' => 'max-w-lg', 'subtitle' => null])
{{-- A modal whose body is only built when it opens (so a table can have one edit dialog per row without paying for hundreds of controls).
     Open with: $dispatch('open-modal', 'name'). Content is your slot; put a <form> in it. --}}
<div x-data="{ open: false }" @open-modal.window="if ($event.detail === '{{ $name }}') open = true" @keydown.escape.window="open = false" x-cloak data-component="dialog" data-dialog="{{ $name }}">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-stone-900/40 p-4 sm:items-center" @click.self="open = false" x-trap.noscroll="open">
            <div class="my-6 w-full {{ $maxWidth }} rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="{{ $title }}">
                <div class="flex items-start justify-between gap-3 border-b border-stone-100 px-5 py-4"><div><h2 class="t-section">{{ $title }}</h2>@if ($subtitle)<p class="mt-0.5 text-xs text-stone-500">{{ $subtitle }}</p>@endif</div><button type="button" class="flex size-9 shrink-0 items-center justify-center rounded-full hover:bg-stone-100" @click="open = false" aria-label="Close"><x-icon name="x" /></button></div>
                <template x-if="open"><div class="p-5">{{ $slot }}</div></template>
            </div>
        </div>
    </template>
</div>
