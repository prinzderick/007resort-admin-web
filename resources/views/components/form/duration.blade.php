@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false,
        'id' => null, 'bare' => false, 'value' => null, 'unit' => 'seconds', 'units' => ['minutes', 'hours', 'days'], 'min' => null, 'max' => null, 'integer' => true, 'slider' => false, 'nullable' => false, 'loading' => false])
{{-- A length of time. The model/posted value is an integer in `unit` (the API's unit, e.g. seconds); people edit it in minutes/hours/days with a unit switcher. --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, get_defined_vars());
    $by = $f->describedBy();
    $names = ['seconds' => 'Seconds', 'minutes' => 'Minutes', 'hours' => 'Hours', 'days' => 'Days'];
    $initialUnit = $units[0];
    if ($value !== null && $value !== '' && is_numeric($value)) {
        $sec = (float) $value * ['seconds' => 1, 'minutes' => 60, 'hours' => 3600, 'days' => 86400][$unit];
        foreach (['days' => 86400, 'hours' => 3600, 'minutes' => 60, 'seconds' => 1] as $u => $size) {
            if (in_array($u, $units, true) && fmod($sec, $size) === 0.0) { $initialUnit = $u; break; }
        }
        $shownAmount = rtrim(rtrim(number_format($sec / ['seconds' => 1, 'minutes' => 60, 'hours' => 3600, 'days' => 86400][$initialUnit], 4, '.', ''), '0'), '.');
    }
    $human = ($value !== null && $value !== '' && is_numeric($value)) ? \App\Support\Form\Format::duration((float) $value * ['seconds' => 1, 'minutes' => 60, 'hours' => 3600, 'days' => 86400][$unit]) : '';
@endphp
<x-form.field :f="$f" type="duration" :x-data="$f->xData('fDuration', ['unit' => $unit, 'units' => $units, 'min' => $min, 'max' => $max, 'integer' => $integer, 'slider' => $slider, 'nullable' => $nullable])" x-modelable="value" {{ $attributes }}>
    <div class="f-duration">
        <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'">
            <input id="{{ $f->id }}" type="text" inputmode="decimal" autocomplete="off" class="f-input f-num" value="{{ $shownAmount ?? '' }}" x-model="amount" @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif
                   :disabled="disabled" :readonly="readonly" x-on:input="typeIn()" x-on:blur="commit()" x-on:keydown="key($event)" x-on:keydown.enter.prevent="commit()">
        </div>
        <div class="f-seg" role="radiogroup" aria-label="Unit">
            @foreach ($units as $u)
                <button type="button" role="radio" class="f-seg-item" data-on="{{ $u === $initialUnit ? 'true' : 'false' }}" :data-on="u === '{{ $u }}' ? 'true' : 'false'" aria-checked="{{ $u === $initialUnit ? 'true' : 'false' }}" :aria-checked="u === '{{ $u }}' ? 'true' : 'false'"
                        x-on:click="switchUnit('{{ $u }}')" :disabled="disabled || readonly">{{ $names[$u] ?? ucfirst($u) }}</button>
            @endforeach
        </div>
        <span class="f-duration-human" aria-live="polite" x-text="human">{{ $human }}</span>
    </div>
    @if ($slider)
        <template x-for="k in (showSlider ? [u] : [])" :key="k">
            <div x-data="fSlider({ min: sliderMin, max: sliderMax, step: 1, id: null, name: null, disabled: !canEdit, readonly: false, unit: k === 'minutes' ? ' min' : (k === 'hours' ? ' h' : (k === 'days' ? ' d' : ' s')), ticks: 0 })" x-modelable="value" x-model="sliderModel">
                @include('components.form._track', ['labelledby' => $f->id.'-label', 'describedby' => '', 'bubble' => true])
            </div>
        </template>
    @endif
    @if ($name)<input type="hidden" name="{{ $name }}" value="{{ $value }}" :value="value ?? ''" :disabled="disabled">@endif
</x-form.field>
