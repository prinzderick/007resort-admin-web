@php
    $tpls = collect($templates->items());
    $caps = collect($capTypes->items());
    $capMeta = $caps->keyBy('code')->map(fn ($c) => ['label' => $c['label'] ?? '', 'requires' => (array) ($c['requires'] ?? []), 'group' => $c['group'] ?? 'Other', 'description' => $c['description'] ?? ''])->all();
    $tplJs = $tpls->keyBy('key')->all();
    $steps = ['Template', 'Details', 'Capabilities', 'Rules', 'Operating points', 'Review'];
@endphp
<x-layouts.app title="Add facility">
    <x-page-header title="Add a facility" subtitle="Pick a starting point, give it a name, and check what it can do. Everything can be changed afterwards." :crumbs="['Setup' => route('setup.index'), 'Facilities' => route('setup.facilities'), 'Add' => null]" />
    <x-fetch :of="$templates" what="Facility templates" />
    @unless ($canAdd)
        <x-pending-api title="Waiting on the API" :items="['Creating facilities needs POST /organization/facilities, which is not in the contract this portal was built against yet. The wizard below is ready and will work as soon as it is.']" />
    @endunless
    <form method="POST" action="{{ route('setup.facilities.store') }}" x-data="{
            step: 0, tpl: '', tpls: @js($tplJs), meta: @js($capMeta), kinds: @js($kinds), name: @js(old('name', '')), code: @js(old('code', '')), codeTouched: false, kind: @js(old('kind', '')), parentId: @js(old('parentId', '')), description: @js(old('description', '')), timezone: @js(old('timezone', $timezone)),
            caps: [], applyStarter: true, labels: @js($ruleLabels),
            pick(k) { this.tpl = k; const t = this.tpls[k]; if (t) { this.caps = [...t.capabilities]; this.kind = t.defaultKind || this.kind; } else { this.caps = []; } },
            slug() { if (!this.codeTouched) this.code = this.name.toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40); },
            toggle(c) { if (this.caps.includes(c)) { const dep = this.caps.filter(x => (this.meta[x]?.requires || []).includes(c)); if (dep.length) { alert('Cannot switch this off while these need it: ' + dep.map(d => this.meta[d]?.label || d).join(', ')); return } this.caps = this.caps.filter(x => x !== c) } else { this.caps.push(c); (this.meta[c]?.requires || []).forEach(r => { if (!this.caps.includes(r)) this.caps.push(r) }) } },
            tplRules() { const t = this.tpls[this.tpl]; return t ? Object.entries(t.operatingRules || {}) : [] },
            fmt(v) { return Array.isArray(v) ? (v.join(', ') || 'none') : (typeof v === 'boolean' ? (v ? 'On' : 'Off') : v) },
            valid() { return this.step !== 1 || (this.name.trim() !== '' && /^[A-Za-z][A-Za-z0-9_]{1,63}$/.test(this.code)) }
        }" x-effect="if (step === 0 && tpl === '' ) {}" data-testid="facility-wizard">
        @csrf
        <input type="hidden" name="templateKey" :value="tpl">
        <template x-for="c in caps" :key="c"><input type="hidden" name="capabilities[]" :value="c"></template>
        <input type="hidden" name="applyStarter" :value="applyStarter ? 1 : 0">

        <ol class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="Steps">
            @foreach ($steps as $i => $label)
                <li class="flex items-center gap-2 rounded-full border px-3 py-1.5" :class="step === {{ $i }} ? 'border-brand-600 bg-brand-50 font-semibold text-brand-800' : (step > {{ $i }} ? 'border-brand-200 text-brand-700' : 'border-stone-200 text-stone-500')"><span class="flex size-5 items-center justify-center rounded-full text-[11px]" :class="step > {{ $i }} ? 'bg-brand-600 text-white' : 'bg-stone-100'">{{ $i + 1 }}</span>{{ $label }}</li>
            @endforeach
        </ol>

        {{-- 1 Template --}}
        <section x-show="step === 0" data-step="template">
            <h2 class="mb-1 text-lg font-semibold">What kind of facility is it?</h2><p class="mb-4 text-sm text-stone-600">A template switches on the right capabilities and sensible rules. It is only a starting point.</p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($tpls as $t)
                    <button type="button" @click="pick('{{ $t['key'] ?? '' }}')" class="rounded-xl border p-4 text-left transition-colors" :class="tpl === '{{ $t['key'] ?? '' }}' ? 'border-brand-600 bg-brand-50' : 'border-stone-200 bg-white hover:border-brand-300'" data-template="{{ $t['key'] ?? '' }}">
                        <span class="block font-semibold">{{ $t['label'] ?? '' }}</span><span class="mt-1 block text-sm text-stone-600">{{ $t['description'] ?? '' }}</span></button>
                @endforeach
                <button type="button" @click="pick('')" class="rounded-xl border p-4 text-left transition-colors" :class="tpl === '' ? 'border-brand-600 bg-brand-50' : 'border-stone-200 bg-white hover:border-brand-300'" data-template="blank"><span class="block font-semibold">Blank facility</span><span class="mt-1 block text-sm text-stone-600">Start with nothing switched on and choose everything yourself.</span></button>
            </div>
        </section>

        {{-- 2 Details --}}
        <section x-show="step === 1" x-cloak data-step="details">
            <h2 class="mb-4 text-lg font-semibold">Name it</h2>
            <div class="grid gap-x-4 sm:grid-cols-2">
                <div class="mb-3"><label class="mb-1 block text-sm font-medium" for="w-name">Name <span class="text-red-600">*</span></label><input id="w-name" name="name" x-model="name" @input="slug()" required maxlength="200" class="min-h-11 w-full rounded-lg border border-stone-300 px-3 text-sm">@error('name')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                <div class="mb-3"><label class="mb-1 block text-sm font-medium" for="w-code">Code <span class="text-red-600">*</span></label><input id="w-code" name="code" x-model="code" @input="codeTouched = true" required class="min-h-11 w-full rounded-lg border border-stone-300 px-3 font-mono text-sm uppercase"><p class="mt-1 text-xs text-stone-500">Letters, digits and underscores, starting with a letter. Cannot be changed later.</p>@error('code')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                <div class="mb-3"><label class="mb-1 block text-sm font-medium" for="w-kind">Kind</label><select id="w-kind" name="kind" x-model="kind" class="min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">(from the template)</option><template x-for="k in kinds" :key="k"><option :value="k" x-text="k.replace(/_/g, ' ')"></option></template></select></div>
                <div class="mb-3"><label class="mb-1 block text-sm font-medium" for="w-parent">Sits under</label><select id="w-parent" name="parentId" x-model="parentId" class="min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm"><option value="">(top level)</option>@foreach ($facilities as $p)<option value="{{ $p['id'] ?? '' }}">{{ $p['name'] ?? '' }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="mb-1 block text-sm font-medium" for="w-tz">Time zone</label><input id="w-tz" name="timezone" x-model="timezone" class="min-h-11 w-full rounded-lg border border-stone-300 px-3 text-sm"></div>
                <div class="mb-3 sm:col-span-2"><label class="mb-1 block text-sm font-medium" for="w-desc">Description</label><textarea id="w-desc" name="description" x-model="description" rows="2" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm"></textarea></div>
            </div>
        </section>

        {{-- 3 Capabilities --}}
        <section x-show="step === 2" x-cloak data-step="capabilities">
            <h2 class="mb-1 text-lg font-semibold">What can it do?</h2><p class="mb-4 text-sm text-stone-600">Switch on what applies. Turning something on also turns on what it needs.</p>
            @foreach ($caps->groupBy(fn ($c) => $c['group'] ?? 'Other') as $group => $items)
                <h3 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wider text-stone-500">{{ $group }}</h3>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($items as $c)
                        <label class="flex cursor-pointer gap-3 rounded-xl border p-4" :class="caps.includes('{{ $c['code'] }}') ? 'border-brand-300 bg-brand-50/50' : 'border-stone-200 bg-white'">
                            <input type="checkbox" class="mt-1 size-5 accent-brand-600" :checked="caps.includes('{{ $c['code'] }}')" @click.prevent="toggle('{{ $c['code'] }}')">
                            <span><span class="block text-sm font-semibold">{{ $c['label'] ?? $c['code'] }}</span><span class="mt-0.5 block text-xs text-stone-600">{{ $c['description'] ?? '' }}</span>@if (! empty($c['requires']))<span class="mt-1 block text-[11px] text-stone-500">Needs: {{ implode(', ', array_map(fn ($r) => $caps->firstWhere('code', $r)['label'] ?? $r, $c['requires'])) }}</span>@endif</span></label>
                    @endforeach
                </div>
            @endforeach
        </section>

        {{-- 4 Rules --}}
        <section x-show="step === 3" x-cloak data-step="rules">
            <h2 class="mb-1 text-lg font-semibold">Starting rules</h2><p class="mb-4 text-sm text-stone-600">These come from the template. You can fine-tune every rule (with proper inputs and explanations) on the facility's Operating rules tab right after it is created.</p>
            <template x-if="tplRules().length === 0"><p class="rounded-lg border border-dashed border-stone-300 p-4 text-sm text-stone-600">No template rules: the facility starts with the standard defaults.</p></template>
            <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2"><template x-for="[k, v] in tplRules()" :key="k"><div class="rounded-lg border border-stone-200 p-3"><dt class="text-xs font-medium uppercase tracking-wide text-stone-500" x-text="labels[k] || k.replace(/_/g, ' ')"></dt><dd class="mt-0.5 font-medium" x-text="fmt(v)"></dd></div></template></dl>
        </section>

        {{-- 5 Operating points --}}
        <section x-show="step === 4" x-cloak data-step="points">
            <h2 class="mb-1 text-lg font-semibold">Operating points</h2><p class="mb-4 text-sm text-stone-600">Counters, table areas and gates where staff work. The template can create the usual ones for you.</p>
            <template x-if="tpls[tpl] && (tpls[tpl].starterOperatingPoints || []).length"><div>
                <label class="mb-3 flex items-center gap-2 text-sm font-medium"><input type="checkbox" x-model="applyStarter" class="size-5 accent-brand-600"> Create the starter operating points{{ '' }}</label>
                <ul class="grid gap-2 sm:grid-cols-2"><template x-for="p in tpls[tpl].starterOperatingPoints" :key="p.code"><li class="rounded-lg border border-stone-200 p-3 text-sm"><b x-text="p.name"></b> <span class="text-xs text-stone-500" x-text="'(' + p.kind.replace(/_/g, ' ').toLowerCase() + ')'"></span></li></template></ul>
                <template x-if="tpls[tpl].starterKdsStation"><p class="mt-3 text-sm text-stone-600">A kitchen/bar screen station <b x-text="tpls[tpl].starterKdsStation.name"></b> is created too.</p></template>
            </div></template>
            <template x-if="!(tpls[tpl] && (tpls[tpl].starterOperatingPoints || []).length)"><p class="rounded-lg border border-dashed border-stone-300 p-4 text-sm text-stone-600">Nothing to create automatically. Add operating points from the facility page afterwards.</p></template>
        </section>

        {{-- 6 Review --}}
        <section x-show="step === 5" x-cloak data-step="review">
            <h2 class="mb-4 text-lg font-semibold">Review</h2>
            <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Name</dt><dd class="font-medium" x-text="name || '-'"></dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Code</dt><dd class="font-mono" x-text="code.toUpperCase() || '-'"></dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Template</dt><dd x-text="tpls[tpl] ? tpls[tpl].label : 'Blank'"></dd></div>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Capabilities</dt><dd x-text="caps.length ? caps.map(c => meta[c]?.label || c).join(', ') : 'none'"></dd></div>
            </dl>
            <p class="mt-4 rounded-lg bg-stone-50 p-3 text-sm text-stone-600">Creating the facility is recorded in the audit log. You can change every setting afterwards.</p>
        </section>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-stone-200 pt-4">
            <div><x-btn type="button" variant="secondary" x-show="step > 0" @click="step--">Back</x-btn></div>
            <div class="flex items-center gap-3">
                <a class="text-sm text-stone-600 underline" href="{{ route('setup.facilities') }}">Cancel</a>
                <x-btn type="button" x-show="step < 5" @click="if (valid()) step++; else alert('Give the facility a name and a code first.')">Next</x-btn>
                <x-btn x-show="step === 5" x-cloak :disabled="! $canAdd" class="disabled:opacity-50">Create facility</x-btn>
            </div>
        </div>
    </form>
</x-layouts.app>
