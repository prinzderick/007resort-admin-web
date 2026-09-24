@props(['label' => 'Row actions'])
{{-- Kebab menu: <x-row-menu><a href=...>Open</a><button ...>Copy</button></x-row-menu> --}}
<div class="relative inline-block text-left" x-data="{ open: false }" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open" class="flex size-9 items-center justify-center rounded-full text-stone-500 hover:bg-stone-100" aria-haspopup="true" :aria-expanded="open" aria-label="{{ $label }}"><svg class="size-5" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg></button>
    <div x-cloak x-show="open" @click.outside="open = false" @click="open = false" class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-stone-200 bg-white py-1 text-sm shadow-lg [&>*]:block [&>*]:w-full [&>*]:px-3 [&>*]:py-2 [&>*]:text-left [&>*:hover]:bg-stone-50">{{ $slot }}</div>
</div>
