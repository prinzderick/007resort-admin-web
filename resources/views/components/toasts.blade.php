@php
    $toasts = array_values(array_filter([
        session('success') ? ['tone' => 'success', 'text' => session('success')] : null,
        session('status') ? ['tone' => 'info', 'text' => session('status')] : null,
    ]));
@endphp
<div x-data="{ items: @js($toasts).map((t, i) => ({ ...t, id: i })), add(t) { const id = Date.now(); this.items.push({ ...t, id }); setTimeout(() => this.items = this.items.filter(x => x.id !== id), 6000) } }"
     x-init="items.forEach(t => setTimeout(() => items = items.filter(x => x.id !== t.id), 6000))" @toast.window="add($event.detail)"
     class="pointer-events-none fixed bottom-4 right-4 z-[60] flex w-full max-w-sm flex-col gap-2" aria-live="polite" data-component="toasts">
    <template x-for="t in items" :key="t.id"><div x-transition class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm shadow-lg" :class="t.tone === 'success' ? 'border-brand-200' : (t.tone === 'danger' ? 'border-red-300' : 'border-stone-200')" role="status"><span class="mt-1 size-2 shrink-0 rounded-full" :class="t.tone === 'success' ? 'bg-brand-600' : (t.tone === 'danger' ? 'bg-red-600' : 'bg-sky-600')"></span><span class="flex-1" x-text="t.text"></span><button type="button" class="text-stone-400 hover:text-stone-700" @click="items = items.filter(x => x.id !== t.id)" aria-label="Dismiss">&times;</button></div></template>
</div>
