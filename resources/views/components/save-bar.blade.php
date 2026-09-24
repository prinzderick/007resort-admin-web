@props(['label' => 'Save changes'])
{{-- Sticky save bar for settings forms; needs the surrounding <form x-data="dirtyGuard">. --}}
<div class="sticky bottom-0 z-20 -mx-4 mt-6 flex flex-wrap items-center gap-4 border-t border-stone-200 bg-white/95 px-4 py-3 backdrop-blur lg:-mx-8 lg:px-8" data-component="save-bar">
    <x-btn>{{ $label }}</x-btn>{{ $slot }}
    <span class="inline-flex items-center gap-1.5 text-xs text-amber-800" x-show="dirty" x-cloak role="status"><span class="size-2 rounded-full bg-amber-500"></span>Unsaved changes</span>
    <span class="text-xs text-stone-400" x-show="!dirty">All changes saved</span>
</div>
