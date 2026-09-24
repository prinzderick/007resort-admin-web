<x-fetch :of="$caps" what="Capabilities" />
<x-fetch :of="$capTypes" what="The capability catalogue" />
@if ($caps->ok())
    @php
        $types = collect($capTypes->items());
        // If the API has no catalogue endpoint yet, show the enabled ones read-only.
        $groups = $types->groupBy(fn ($t) => $t['group'] ?? 'Other');
        $meta = $types->keyBy('code')->map(fn ($t) => ['requires' => (array) ($t['requires'] ?? [])])->all();
    @endphp
    @if ($capTypes->ok() && $types->isNotEmpty())
        <p class="mb-4 max-w-3xl text-sm text-stone-600">Capabilities decide what this facility can do, and which operating rules and screens apply to it. Switching one on also switches on anything it needs. Switching one off is refused while another enabled capability still needs it.</p>
        <form method="POST" action="{{ route('setup.facilities.capabilities', $id) }}" x-data="{ on: @js(array_values($enabled)), start: @js(array_values($enabled)), meta: @js($meta), labels: @js($types->pluck('label', 'code')->all()), note: '',
                get dirty() { return [...this.on].sort().join() !== [...this.start].sort().join() },
                toggle(code) {
                    this.note = '';
                    if (this.on.includes(code)) {
                        const dependents = this.on.filter(c => (this.meta[c]?.requires || []).includes(code));
                        if (dependents.length) { this.note = 'You cannot switch off ' + this.labels[code] + ' while ' + dependents.map(d => this.labels[d]).join(', ') + ' ' + (dependents.length > 1 ? 'need' : 'needs') + ' it. Switch ' + (dependents.length > 1 ? 'those' : 'that') + ' off first.'; return }
                        this.on = this.on.filter(c => c !== code)
                    } else {
                        this.on.push(code); const added = [];
                        (this.meta[code]?.requires || []).forEach(r => { if (!this.on.includes(r)) { this.on.push(r); added.push(this.labels[r]) } });
                        if (added.length) this.note = this.labels[code] + ' needs ' + added.join(', ') + ', so that was switched on as well.'
                    }
                } }" data-testid="capabilities-form">
            @csrf @method('PUT')
            <input type="hidden" name="etag" value="{{ $capEtag }}">
            <template x-for="c in on" :key="c"><input type="hidden" name="capabilities[]" :value="c"></template>
            @foreach ($groups as $group => $items)
                <h3 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wider text-stone-500">{{ $group }}</h3>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($items as $t)
                        @php $code = $t['code'] ?? ''; @endphp
                        <label class="flex cursor-pointer gap-3 rounded-xl border p-4 transition-colors" :class="on.includes('{{ $code }}') ? 'border-brand-300 bg-brand-50/50' : 'border-stone-200 bg-white'" data-capability="{{ $code }}">
                            <input type="checkbox" class="mt-1 size-5 shrink-0 accent-brand-600" :checked="on.includes('{{ $code }}')" @click.prevent="{{ $canCaps ? "toggle('{$code}')" : '' }}" @disabled(! $canCaps)>
                            <span><span class="block text-sm font-semibold">{{ $t['label'] ?? $code }}</span><span class="mt-0.5 block text-xs text-stone-600">{{ $t['description'] ?? '' }}</span>
                                @if (! empty($t['requires']))<span class="mt-1 block text-[11px] text-stone-500">Needs: {{ implode(', ', array_map(fn ($r) => $types->firstWhere('code', $r)['label'] ?? $r, $t['requires'])) }}</span>@endif</span>
                        </label>
                    @endforeach
                </div>
            @endforeach
            <p x-show="note" x-cloak x-text="note" class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950" role="status" data-testid="capability-note"></p>
            <p class="mt-4 max-w-3xl text-xs text-stone-500">Switching a capability off is refused while it is in use (open orders, open cash sessions, stock on hand, upcoming bookings). If that happens you will see exactly what to close first. Its rules are kept and come back when you switch it on again.</p>
            @if ($canCaps)<div class="f-actions" data-flush="false" data-testid="cap-actions"><div class="f-actions-status" :data-dirty="dirty ? 'true' : 'false'"><i></i><span x-show="dirty" x-cloak>Unsaved changes</span><span x-show="!dirty">All changes saved</span></div><div class="f-actions-buttons"><button type="button" class="f-btn" x-on:click="on = [...start]; note = ''" :disabled="!dirty">Discard</button><button type="submit" class="f-btn" data-variant="primary" :disabled="!dirty">Save capabilities</button></div></div>
            @else<x-pending-api class="mt-6" title="Read-only" :items="['You need the config.manage.capabilities permission to change capabilities.']" />@endif
        </form>
    @else
        <x-card title="Enabled capabilities"><div class="flex flex-wrap gap-2">@forelse ($enabled as $c)<x-badge tone="info">{{ ucwords(strtolower(str_replace('_', ' ', $c))) }}</x-badge>@empty<span class="text-sm text-stone-500">None</span>@endforelse</div></x-card>
    @endif
@endif
