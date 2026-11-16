@props(['label' => 'Row actions'])
{{-- Kebab menu: <x-row-menu><a href=...>Open</a><button ...>Copy</button></x-row-menu>
     The panel is position:fixed (placed from the button's position, flipped upward near the bottom of the screen), so a
     scrolling table box around it can never clip it, however few rows the table has. --}}
<div class="relative inline-block text-left"
     x-data="{
        open: false, top: 0, right: 0,
        place() {
            const r = this.$refs.btn.getBoundingClientRect();
            const h = this.$refs.panel.offsetHeight || 160;
            this.top = (window.innerHeight - r.bottom) < h + 12 ? Math.max(8, r.top - h - 4) : r.bottom + 4;
            this.right = Math.max(8, window.innerWidth - r.right);
        },
        toggle() { this.open = !this.open; if (this.open) { this.$nextTick(() => this.place()); } },
     }"
     @keydown.escape.window="open = false"
     @scroll.window.capture="open = false"
     @resize.window="open = false">
    <button type="button" x-ref="btn" @click="toggle()" class="flex size-9 items-center justify-center rounded-full text-stone-500 hover:bg-stone-100" aria-haspopup="true" :aria-expanded="open" aria-label="{{ $label }}"><svg class="size-5" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg></button>
    <div x-cloak x-show="open" x-ref="panel" @click.outside="open = false" @click="open = false" :style="`top:${top}px;right:${right}px`" class="fixed z-50 w-44 rounded-lg border border-stone-200 bg-white py-1 text-sm shadow-lg [&>*]:block [&>*]:w-full [&>*]:px-3 [&>*]:py-2 [&>*]:text-left [&>*:hover]:bg-stone-50">{{ $slot }}</div>
</div>
