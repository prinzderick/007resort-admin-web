<div x-data="{ open: false, title: '', fields: [], href: null, linkLabel: '' }"
     @open-drawer.window="title = $event.detail.title; fields = $event.detail.fields; href = $event.detail.href; linkLabel = $event.detail.linkLabel; open = true"
     @keydown.escape.window="open = false" x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 bg-stone-900/30" @click="open = false"></div>
    <aside x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Details" data-testid="drawer">
        <div class="flex items-center justify-between border-b border-stone-100 px-5 py-4"><h2 class="text-base font-semibold" x-text="title"></h2><button type="button" @click="open = false" class="flex size-9 items-center justify-center rounded-full hover:bg-stone-100" aria-label="Close"><x-icon name="x" /></button></div>
        <dl class="flex-1 divide-y divide-stone-100 overflow-y-auto px-5 text-sm">
            <template x-for="f in fields" :key="f[0]"><div class="py-3"><dt class="text-xs font-medium uppercase tracking-wide text-stone-500" x-text="f[0]"></dt><dd class="mt-0.5 break-words" x-text="f[1]"></dd></div></template>
        </dl>
        <div class="border-t border-stone-100 p-4" x-show="href"><a :href="href" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-medium text-white hover:bg-brand-700" x-text="linkLabel"></a></div>
    </aside>
</div>
