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
        <form method="POST" action="{{ route('setup.facilities.capabilities', $id) }}" x-data="{ on: @js(array_values($enabled)), meta: @js($meta),
                toggle(code) { if (this.on.includes(code)) { const dependents = this.on.filter(c => (this.meta[c]?.requires || []).includes(code)); if (dependents.length) { alert('Cannot switch this off while these need it: ' + dependents.join(', ')); return } this.on = this.on.filter(c => c !== code) } else { this.on.push(code); (this.meta[code]?.requires || []).forEach(r => { if (!this.on.includes(r)) this.on.push(r) }) } } }" data-testid="capabilities-form">
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
            @if ($canCaps)<div class="mt-6"><x-btn>Save capabilities</x-btn></div>
            @else<x-pending-api class="mt-6" :items="['Changing capabilities needs PUT /facilities/{facilityId}/capabilities (permission config.manage.capabilities), which is not available to this account or not in the contract yet']" />@endif
        </form>
    @else
        <x-card title="Enabled capabilities"><div class="flex flex-wrap gap-2">@forelse ($enabled as $c)<x-badge tone="info">{{ ucwords(strtolower(str_replace('_', ' ', $c))) }}</x-badge>@empty<span class="text-sm text-stone-500">None</span>@endforelse</div></x-card>
        <x-pending-api :items="['Editing capabilities: the API has no capability catalogue or write endpoint in the contract yet']" />
    @endif
@endif
