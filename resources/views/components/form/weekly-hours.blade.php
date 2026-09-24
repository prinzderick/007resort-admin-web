@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => null, 'overnight' => false, 'exceptions' => true, 'defaultFrom' => '09:00', 'defaultTo' => '17:00', 'step' => 30])
{{-- 7-day opening-hours editor: open/closed switch, several intervals per day (split shifts), "copy to weekdays / all days", and an exceptions list (holidays, closures).
     Value: { weekly: { mon: { open: true, intervals: [{from:'08:00', to:'22:00'}] }, ... sun }, exceptions: [{ date:'2026-12-25', label:'Christmas', open:false, from:null, to:null }] }.
     Posted as ONE hidden input containing that JSON. --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, get_defined_vars());
    $by = $f->describedBy();
@endphp
<x-form.field :f="$f" type="weekly-hours" :label-for="false" :x-data="$f->xData('fWeek', ['overnight' => $overnight, 'defaultInterval' => ['from' => $defaultFrom, 'to' => $defaultTo]])" x-modelable="value" {{ $attributes }}>
    <div class="f-week" role="group" aria-labelledby="{{ $f->id }}-label" @if ($by) aria-describedby="{{ $by }}" @endif x-on:click.outside="copyOpen = null">
        <template x-for="d in days" :key="d">
            <div class="f-day-row" :data-invalid="errors[d] ? 'true' : 'false'" x-on:f-change="touch()">
                <div class="f-day-name">
                    <button type="button" role="switch" class="f-toggle" :aria-checked="value.weekly[d].open ? 'true' : 'false'" :aria-label="labels[d] + (value.weekly[d].open ? ': open' : ': closed')" :disabled="disabled || readonly" x-on:click="toggleDay(d)"><span class="f-switch"></span></button>
                    <span x-text="labels[d]"></span>
                </div>
                <div class="f-intervals" x-show="value.weekly[d].open" x-cloak>
                    <template x-for="(iv, i) in value.weekly[d].intervals" :key="i">
                        <div class="f-interval">
                            <x-form.time-range bare x-model="week.weekly[d].intervals[i]" :overnight="$overnight" :step="$step" :disabled="$disabled" :readonly="$readonly" />
                            <button type="button" class="f-icon-btn" :aria-label="'Remove interval ' + (i + 1) + ' for ' + labels[d]" :disabled="disabled || readonly" x-on:click="removeInterval(d, i)"><x-form.icon name="trash" /></button>
                        </div>
                    </template>
                    <div><button type="button" class="f-link" :disabled="disabled || readonly" x-on:click="addInterval(d)">+ Add hours</button></div>
                    <template x-for="m in (errors[d] || [])" :key="m"><p class="f-error" role="alert" x-text="m"></p></template>
                </div>
                <div class="f-closed" x-show="!value.weekly[d].open" x-cloak>Closed</div>
                <div class="f-day-tools f-anchor" x-show="value.weekly[d].open" x-cloak>
                    <button type="button" class="f-icon-btn" :aria-label="'Copy ' + labels[d] + ' hours to other days'" :aria-expanded="copyOpen === d ? 'true' : 'false'" :disabled="disabled || readonly" x-on:click="copyOpen = copyOpen === d ? null : d"><x-form.icon name="copy" /></button>
                    <div class="f-pop" data-right="true" style="min-width:14rem" x-show="copyOpen === d" x-cloak role="menu">
                        <div class="f-opt-item" role="menuitem" tabindex="0" x-on:click="copy(d, 'weekdays')" x-on:keydown.enter="copy(d, 'weekdays')">Copy to all weekdays (Mon-Fri)</div>
                        <div class="f-opt-item" role="menuitem" tabindex="0" x-on:click="copy(d, 'weekend')" x-on:keydown.enter="copy(d, 'weekend')">Copy to the weekend</div>
                        <div class="f-opt-item" role="menuitem" tabindex="0" x-on:click="copy(d, 'all')" x-on:keydown.enter="copy(d, 'all')">Copy to every day</div>
                    </div>
                </div>
            </div>
        </template>
    </div>
    <p class="f-hint" role="status" aria-live="polite" x-show="notice" x-cloak x-text="notice"></p>
    @if ($exceptions)
        <div class="f-exceptions" x-on:f-change="touch()" x-on:input.debounce.300ms="touch()">
            <div class="f-head"><span class="f-label" id="{{ $f->id }}-exc">Exceptions and holidays</span><span class="f-opt">Override the weekly hours on a date</span></div>
            <template x-for="(ex, i) in value.exceptions" :key="i">
                <div class="f-exc-row">
                    <x-form.date bare x-model="week.exceptions[i].date" label="Exception date" />
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;min-width:0">
                        <div class="f-box" style="flex:1 1 9rem"><input type="text" class="f-input" x-model="ex.label" placeholder="Label, e.g. Christmas Day" aria-label="Exception label" :disabled="disabled || readonly" maxlength="80"></div>
                        <div class="f-seg" role="radiogroup" aria-label="Open or closed">
                            <button type="button" role="radio" class="f-seg-item" :data-on="!ex.open ? 'true' : 'false'" :aria-checked="!ex.open ? 'true' : 'false'" :disabled="disabled || readonly" x-on:click="ex.open = false; touch()">Closed</button>
                            <button type="button" role="radio" class="f-seg-item" :data-on="ex.open ? 'true' : 'false'" :aria-checked="ex.open ? 'true' : 'false'" :disabled="disabled || readonly" x-on:click="ex.open = true; touch()">Special hours</button>
                        </div>
                        <template x-if="ex.open"><x-form.time-range bare x-model="week.exceptions[i]" :overnight="$overnight" :step="$step" style="flex:1 1 15rem" /></template>
                    </div>
                    <button type="button" class="f-icon-btn" aria-label="Remove exception" :disabled="disabled || readonly" x-on:click="removeException(i)"><x-form.icon name="trash" /></button>
                </div>
            </template>
            <button type="button" class="f-link" :disabled="disabled || readonly" x-on:click="addException()">+ Add an exception</button>
        </div>
    @endif
    @if ($name)<input type="hidden" name="{{ $name }}" :value="JSON.stringify(value)" :disabled="disabled">@endif
</x-form.field>
