@props(['definitions' => [], 'values' => [], 'name' => 'rules', 'wire' => null, 'live' => false, 'groupBy' => 'group', 'disabled' => false, 'capabilities' => null, 'search' => false, 'options' => [], 'confirmWire' => null, 'sections' => true, 'fieldErrors' => []])
{{-- Schema-driven form: pass the rule definitions (and current values) and every rule renders with the right control, grouped into sections.
     name="rules"      -> inputs named rules[<key>]            (classic post: PUT /setup/facilities/{id}/rules)
     wire="rules"      -> wire:model="rules.<key>"             (Livewire; add :live="true" for wire:model.live)
     capabilities=[..] -> only rules whose capability is enabled at the facility are shown (the API rejects the rest)
     Changing a HIGH-impact rule reveals an acknowledgement checkbox (posts confirm=1 / wire:model="confirm") and blocks submit until ticked, matching the API's danger_confirmation_required. --}}
@php
    $defs = collect($definitions)->values();
    if (is_array($capabilities)) {
        $enabled = array_map('strtoupper', $capabilities);
        $defs = $defs->filter(function ($d) use ($enabled) {
            $caps = (array) ($d['capabilities'] ?? (isset($d['capability']) ? [$d['capability']] : []));

            return $caps === [] || array_intersect(array_map('strtoupper', $caps), $enabled) !== [];
        })->values();
    }
    $groups = $defs->groupBy(fn ($d) => $d[$groupBy] ?? 'General');
    $confirmModel = $confirmWire ?? ($wire ? 'confirm' : null);
@endphp
<div {{ $attributes->class(['f-schema']) }} x-data="fSchema()" data-component="rule-schema">
    @if ($search)
        <div class="f-box" style="max-width:24rem;margin-bottom:1.25rem"><x-form.icon name="search" class="size-4" style="align-self:center;margin-left:0.75rem;color:var(--color-stone-500)" />
            <input type="search" class="f-input" x-model="q" placeholder="Search settings" aria-label="Search settings" autocomplete="off"></div>
    @endif
    @foreach ($groups as $group => $items)
        @php
            $body = $items->map(function ($def) use ($values, $name, $wire, $live, $disabled, $options, $fieldErrors) {
                $key = $def['key'];
                $has = is_array($values) && array_key_exists($key, $values);

                return compact('def', 'key', 'has') + ['value' => $has ? $values[$key] : '__default__', 'error' => $fieldErrors[$key] ?? $fieldErrors['rules.'.$key] ?? null, 'options' => $options[$key] ?? null];
            });
        @endphp
        @if ($sections)
            <x-form.section :title="$group" :description="\Illuminate\Support\Arr::get(['Payments & cash' => 'How customers pay and how cash is controlled.', 'Approvals' => 'When a supervisor has to approve.', 'Booking' => 'How bookings are held, changed and cancelled.', 'Orders' => 'How orders are taken, sent and settled.'], $group)" :id="'grp-'.\Illuminate\Support\Str::slug($group)">
                @foreach ($body as $r)
                    <x-form.rule-field :def="$r['def']" :value="$r['value']" :name="$name ? $name.'['.$r['key'].']' : null" :wire="$wire ? $wire.'.'.$r['key'] : null" :live="$live" :error="$r['error']" :disabled="$disabled" :options="$r['options']" />
                @endforeach
            </x-form.section>
        @else
            @foreach ($body as $r)
                <x-form.rule-field :def="$r['def']" :value="$r['value']" :name="$name ? $name.'['.$r['key'].']' : null" :wire="$wire ? $wire.'.'.$r['key'] : null" :live="$live" :error="$r['error']" :disabled="$disabled" :options="$r['options']" />
            @endforeach
        @endif
    @endforeach
    @if ($defs->isEmpty())<p class="f-hint">No settings apply to this facility's enabled features.</p>@endif

    <div class="f-confirm-box" x-show="highCount > 0" x-cloak role="alert" data-testid="danger-confirm">
        <input type="checkbox" x-ref="ack" x-model="ack" id="schema-ack" style="width:1.25rem;height:1.25rem;margin-top:0.125rem;accent-color:var(--color-danger-600,#c9302c)" @if ($confirmModel) wire:model="{{ $confirmModel }}" @endif>
        <label for="schema-ack" style="font-size:0.875rem;font-weight:600"><span x-text="highCount === 1 ? '1 high-impact setting is changing.' : highCount + ' high-impact settings are changing.'"></span>
            <span style="display:block;font-weight:400;margin-top:0.125rem">I understand this affects money, stock or security controls and want to save it.</span></label>
    </div>
    @if (! $confirmWire && ! $wire)<input type="hidden" name="confirm" :value="ack ? '1' : '0'">@endif
</div>
