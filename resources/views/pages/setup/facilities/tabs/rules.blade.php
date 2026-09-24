@if ($eff->ok())
    <x-fetch :of="$defs" what="The rule definitions" />
    @if ($defs->ok() && $ruleGroups !== [])
        <p class="mb-4 max-w-3xl text-sm text-stone-600">These are the rules this facility runs by. Only the rules that apply to its capabilities are listed. A rule marked <x-badge tone="default">default</x-badge> is using the standard value; change it to override, or tick "Use the default" to go back. Changes take effect on new activity straight away.</p>
        <form method="POST" action="{{ route('setup.facilities.rules', $id) }}" x-data="dirtyGuard" data-testid="rules-form">
            @csrf @method('PUT')
            @foreach ($ruleGroups as $group => $items)
                <x-card :title="$group" data-rule-group="{{ $group }}">
                    <div class="divide-y divide-stone-100">
                    @foreach ($items as $it)
                        @php
                            $d = $it['def']; $key = $d['key']; $val = old('rules.'.$key, $it['value']); $type = $d['type'] ?? 'text';
                            $danger = ($d['dangerLevel'] ?? 'low') === 'high'; $enf = $d['enforcement'] ?? 'server';
                            $nm = "rules[{$key}]";
                        @endphp
                        <div class="grid gap-3 py-4 md:grid-cols-[minmax(0,1fr)_18rem]" data-rule="{{ $key }}">
                            <div>
                                <div class="flex flex-wrap items-center gap-2"><label for="r-{{ $key }}" class="text-sm font-semibold">{{ $d['label'] ?? $key }}</label>
                                    @if ($danger)<x-badge tone="bad">High risk</x-badge>@endif
                                    @if ($it['isDefault'])<x-badge>default</x-badge>@endif
                                    @if ($enf === 'planned')<x-badge tone="warn">Not enforced yet</x-badge>@elseif ($enf === 'client')<x-badge tone="info">Enforced by the apps</x-badge>@endif</div>
                                <p class="mt-1 text-xs text-stone-600">{{ $d['description'] ?? '' }}</p>
                                @if ($danger)<p class="mt-1 flex items-start gap-1 text-xs text-red-800"><x-icon name="alert" class="mt-0.5 size-3.5 shrink-0" />Affects money, stock or security. You will be asked to confirm.</p>@endif
                                @error('rules.'.$key)<p class="mt-1 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                @if ($type === 'bool')
                                    <input type="hidden" name="{{ $nm }}" value="0"><label class="flex min-h-11 items-center gap-3 text-sm"><input id="r-{{ $key }}" type="checkbox" name="{{ $nm }}" value="1" class="size-5 accent-brand-600" @checked(filter_var($val, FILTER_VALIDATE_BOOLEAN)) @disabled(! $canRules)> {{ filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 'On' : 'Off' }}</label>
                                @elseif ($type === 'enum')
                                    <select id="r-{{ $key }}" name="{{ $nm }}" class="min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm" @disabled(! $canRules)>@foreach ((array) ($d['allowed'] ?? []) as $o)<option value="{{ $o['value'] }}" @selected((string) $val === (string) $o['value'])>{{ $o['label'] }}</option>@endforeach</select>
                                @elseif ($type === 'multi_enum')
                                    <div class="space-y-1">@foreach ((array) ($d['allowed'] ?? []) as $o)<label class="flex min-h-9 items-center gap-2 text-sm"><input type="checkbox" name="{{ $nm }}[]" value="{{ $o['value'] }}" class="size-4 accent-brand-600" @checked(in_array($o['value'], (array) $val, true)) @disabled(! $canRules)> {{ $o['label'] }}</label>@endforeach</div>
                                @elseif ($type === 'facility')
                                    <select id="r-{{ $key }}" name="{{ $nm }}" class="min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm" @disabled(! $canRules)><option value="">(not set)</option>@foreach ($flat as $p)<option value="{{ $p['id'] ?? '' }}" @selected((string) $val === ($p['id'] ?? ''))>{{ $p['name'] ?? '' }}</option>@endforeach</select>
                                @elseif ($type === 'money')
                                    <div class="flex"><span class="flex items-center rounded-l-lg border border-r-0 border-stone-300 bg-stone-50 px-3 text-sm text-stone-500">{{ $d['unit'] ?? 'NGN' }}</span><input id="r-{{ $key }}" name="{{ $nm }}" value="{{ $val }}" inputmode="decimal" class="min-h-11 w-full rounded-r-lg border border-stone-300 px-3 text-sm tabular-nums" @disabled(! $canRules)></div>
                                @else
                                    <div class="flex"><input id="r-{{ $key }}" type="number" step="{{ ($d['integer'] ?? false) || $type === 'duration' ? 1 : 'any' }}" @if (isset($d['min'])) min="{{ $d['min'] }}" @endif @if (isset($d['max'])) max="{{ $d['max'] }}" @endif name="{{ $nm }}" value="{{ $val }}" class="min-h-11 w-full {{ ! empty($d['unit']) ? 'rounded-l-lg' : 'rounded-lg' }} border border-stone-300 px-3 text-sm tabular-nums" @disabled(! $canRules)>@if (! empty($d['unit']))<span class="flex items-center rounded-r-lg border border-l-0 border-stone-300 bg-stone-50 px-3 text-sm text-stone-500">{{ $d['unit'] }}</span>@endif</div>
                                    @if (isset($d['min']) || isset($d['max']))<p class="mt-1 text-[11px] text-stone-500">{{ isset($d['min']) ? 'Min '.$d['min'] : '' }}{{ isset($d['min'], $d['max']) ? ' - ' : '' }}{{ isset($d['max']) ? 'max '.$d['max'] : '' }}</p>@endif
                                @endif
                                <p class="mt-1 text-[11px] text-stone-500">Standard: {{ is_bool($d['default'] ?? null) ? (($d['default']) ? 'On' : 'Off') : (is_array($d['default'] ?? null) ? (empty($d['default']) ? 'none' : implode(', ', $d['default'])) : ($d['default'] ?? 'not set')) }}@if ($canRules && ! $it['isDefault']) &middot; <label class="inline-flex items-center gap-1"><input type="checkbox" name="reset[{{ $key }}]" value="1" class="size-3.5"> Use the default</label>@endif</p>
                            </div>
                        </div>
                    @endforeach
                    </div>
                </x-card>
            @endforeach
            @if ($notApplicable !== [])<p class="mb-4 text-xs text-stone-500">Hidden because this facility does not have the capability they need: {{ implode(', ', array_map(fn ($k) => $defMap[$k]['label'] ?? $k, array_slice($notApplicable, 0, 12))) }}{{ count($notApplicable) > 12 ? '...' : '' }}. Turn the capability on under Capabilities to see them.</p>@endif
            @if ($canRules)
                <x-save-bar label="Save rules"><label class="flex items-center gap-2 text-sm text-red-900"><input type="checkbox" name="confirmRisk" value="1" class="size-4"> I understand that changing a high-risk rule affects money, stock or security</label></x-save-bar>
            @else
                <x-pending-api :items="['Editing operating rules needs PUT /facilities/{facilityId}/operating-rules (permission config.manage.rules), which is not available to this account or not in the contract yet']" />
            @endif
        </form>
    @else
        @php $r = (array) ($eff->data['values'] ?? []); @endphp
        <x-card title="Operating rules (current values)"><dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">@foreach ($r as $k => $v)<div><dt class="text-stone-500">{{ $k }}</dt><dd>{{ is_scalar($v) ? (is_bool($v) ? ($v ? 'Yes' : 'No') : $v) : json_encode($v) }}</dd></div>@endforeach</dl></x-card>
    @endif
@endif
@if (! $eff->ok())
    @if ($legacy->ok())
        @php $r = (array) ($legacy->data['operatingRules'] ?? []); @endphp
        <x-card title="Operating rules (current values)" subtitle="Read-only for now" data-testid="legacy-rules">
            <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($r as $k => $v)<div><dt class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $k)) }}</dt><dd class="mt-0.5">{{ is_bool($v) ? ($v ? 'Yes' : 'No') : (is_array($v) ? (implode(', ', $v) ?: 'none') : ($v ?? '-')) }}</dd></div>@endforeach
            </dl>
        </x-card>
        <x-pending-api :items="['The rule form (proper inputs, units, defaults, danger warnings) appears here as soon as the API provides GET /organization/rule-definitions and PUT /facilities/{facilityId}/operating-rules']" />
    @else
        <x-fetch :of="$eff" what="Operating rules" />
        <x-fetch :of="$legacy" what="Operating rules" />
    @endif
@endif
