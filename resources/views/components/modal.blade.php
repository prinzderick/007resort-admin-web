@props(['name', 'title', 'maxWidth' => 'max-w-lg'])
{{-- Open with: $dispatch('open-modal', 'name') --}}
<div x-data="{ open: false }" @open-modal.window="if ($event.detail === '{{ $name }}') open = true" @keydown.escape.window="open = false" x-cloak data-component="modal">
    <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/40 p-4" @click.self="open = false">
        <div x-show="open" x-transition class="w-full {{ $maxWidth }} rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="{{ $title }}">
            <div class="flex items-center justify-between border-b border-stone-100 px-5 py-4"><h2 class="t-section">{{ $title }}</h2><button type="button" class="flex size-9 items-center justify-center rounded-full hover:bg-stone-100" @click="open = false" aria-label="Close"><x-icon name="x" /></button></div>
            <div class="p-5">{{ $slot }}</div>
        </div>
    </div>
</div>
