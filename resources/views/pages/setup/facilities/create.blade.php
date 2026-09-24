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
        <x-pending-api title="Read-only" :items="['You need the facility.manage permission to add a facility.']" />
    @endunless
    <form method="POST" action="{{ route('setup.facilities.store') }}" x-data="{
            init() { this.$watch('facName', () => this.slug()) }, step: {{ $errors->any() ? (($errors->has('name') || $errors->has('code')) ? 1 : 5) : 0 }}, note: '', tpl: @js(old('templateKey', '')), tpls: @js($tplJs), meta: @js($capMeta), kinds: @js($kinds), facName: @js(old('name', '')), code: @js(old('code', '')), codeTouched: false, kind: @js(old('kind', '')), parentId: @js(old('parentId', '')), description: @js(old('description', '')), timezone: @js(old('timezone', $timezone)),
            caps: @js(old('capabilities', [])), applyStarter: true, labels: @js($ruleLabels),
            pick(k) { this.tpl = k; const t = this.tpls[k]; if (t) { this.caps = [...t.capabilities]; this.kind = t.defaultKind || this.kind; } else { this.caps = []; } },
            slug() { if (!this.codeTouched) this.code = this.facName.toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40); },
            toggle(c) { this.note = ''; if (this.caps.includes(c)) { const dep = this.caps.filter(x => (this.meta[x]?.requires || []).includes(c)); if (dep.length) { this.note = 'You cannot switch off ' + (this.meta[c]?.label || c) + ' while ' + dep.map(d => this.meta[d]?.label || d).join(', ') + ' needs it. Switch that off first.'; return } this.caps = this.caps.filter(x => x !== c) } else { this.caps.push(c); const added = []; (this.meta[c]?.requires || []).forEach(r => { if (!this.caps.includes(r)) { this.caps.push(r); added.push(this.meta[r]?.label || r) } }); if (added.length) this.note = (this.meta[c]?.label || c) + ' needs ' + added.join(', ') + ', so that was switched on too.' } },
            tplRules() { const t = this.tpls[this.tpl]; return t ? Object.entries(t.operatingRules || {}) : [] },
            fmt(v) { return Array.isArray(v) ? (v.join(', ') || 'none') : (typeof v === 'boolean' ? (v ? 'On' : 'Off') : v) },
            nameError: '', valid() { if (this.step !== 1) return true; if (this.facName.trim() === '') { this.nameError = 'Give the facility a name.'; return false } if (!/^[A-Za-z][A-Za-z0-9_]{1,63}$/.test(this.code)) { this.nameError = 'The code needs letters, digits or underscores, starting with a letter (at least 2 characters).'; return false } this.nameError = ''; return true }
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
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.text name="name" label="Name" required x-model="facName" :maxlength="200" placeholder="e.g. Rooftop Bar" />
                <x-form.text name="code" label="Code" required x-model="code" x-on:input="codeTouched = true" :maxlength="64" hint="Letters, digits and underscores, starting with a letter. Cannot be changed later." />
                <x-form.select name="kind" label="Kind" :options="collect($kinds)->map(fn ($k) => ['value' => $k, 'label' => ucwords(strtolower(str_replace('_', ' ', $k)))])->all()" x-model="kind" :clearable="true" placeholder="From the template" />
                <x-form.select name="parentId" label="Sits under" :options="collect($facilities)->map(fn ($p) => ['value' => $p['id'], 'label' => $p['name'] ?? $p['code']])->all()" x-model="parentId" :clearable="true" placeholder="Top level" />
                <x-form.text name="timezone" label="Time zone" x-model="timezone" hint="Leave as is unless this facility is in another zone." />
                <div class="sm:col-span-2"><x-form.text name="description" label="Description" x-model="description" :multiline="true" :rows="2" :maxlength="500" /></div>
            </div>
            <p class="mt-3 text-sm text-red-700" x-show="nameError" x-cloak x-text="nameError" role="alert"></p>
        </section>

        {{-- 3 Capabilities --}}
        <section x-show="step === 2" x-cloak data-step="capabilities">
            <h2 class="mb-1 text-lg font-semibold">What can it do?</h2><p class="mb-4 text-sm text-stone-600">Switch on what applies. Turning something on also turns on what it needs.</p>
            <p x-show="note" x-cloak x-text="note" class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950" role="status"></p>
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
                <div class="mb-3 max-w-xl"><x-form.toggle name="_starter" label="Create the starter operating points" description="The usual counters and table areas for this kind of facility." x-model="applyStarter" :value="true" /></div>
                <ul class="grid gap-2 sm:grid-cols-2"><template x-for="p in tpls[tpl].starterOperatingPoints" :key="p.code"><li class="rounded-lg border border-stone-200 p-3 text-sm"><b x-text="p.name"></b> <span class="text-xs text-stone-500" x-text="'(' + p.kind.replace(/_/g, ' ').toLowerCase() + ')'"></span></li></template></ul>
                <template x-if="tpls[tpl].starterKdsStation"><p class="mt-3 text-sm text-stone-600">A kitchen/bar screen station <b x-text="tpls[tpl].starterKdsStation.name"></b> is created too.</p></template>
            </div></template>
            <template x-if="!(tpls[tpl] && (tpls[tpl].starterOperatingPoints || []).length)"><p class="rounded-lg border border-dashed border-stone-300 p-4 text-sm text-stone-600">Nothing to create automatically. Add operating points from the facility page afterwards.</p></template>
        </section>

        {{-- 6 Review --}}
        <section x-show="step === 5" x-cloak data-step="review">
            <h2 class="mb-4 text-lg font-semibold">Review</h2>
            <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Name</dt><dd class="font-medium" x-text="facName || '-'"></dd></div>
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
                <x-btn type="button" x-show="step < 5" @click="if (valid()) step++">Next</x-btn>
                <x-btn x-show="step === 5" x-cloak :disabled="! $canAdd" class="disabled:opacity-50">Create facility</x-btn>
            </div>
        </div>
    </form>
</x-layouts.app>
