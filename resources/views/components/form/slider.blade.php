@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false,
        'id' => null, 'bare' => false, 'value' => null, 'min' => 0, 'max' => 100, 'step' => 1, 'unit' => null, 'prefix' => null, 'unitGlue' => '', 'ticks' => 0, 'snap' => [], 'snapTolerance' => null,
        'number' => true, 'bubble' => true, 'format' => null, 'loading' => false])
{{-- Single-value slider: live value bubble, tick marks, snap points, unit suffix, and an editable number box synced both ways. Arrow keys step, PageUp/Down jump, Home/End go to the ends. --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, get_defined_vars());
    $labelledby = $f->id.'-label';
    $describedby = $f->describedBy();
    $start = $value ?? $meta['default']['value'] ?? $min;
@endphp
<x-form.field :f="$f" type="slider" :x-data="$f->xData('fSlider', ['min' => $min, 'max' => $max, 'step' => $step, 'unit' => $unit, 'prefix' => $prefix, 'unitGlue' => $unitGlue, 'ticks' => $ticks, 'snap' => $snap, 'snapTolerance' => $snapTolerance, 'format' => $format])" x-modelable="value" {{ $attributes }}>
    <div class="f-slider-row">
        <div class="f-slider-track-col">@include('components.form._track', ['labelledby' => $labelledby, 'describedby' => $describedby, 'bubble' => $bubble])</div>
        @if ($number)
            <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'">
                @if ($prefix)<span class="f-affix">{{ $prefix }}</span>@endif
                <input id="{{ $f->id }}" type="text" inputmode="decimal" autocomplete="off" class="f-input f-num" value="{{ $start }}" x-model="text" aria-label="{{ $label ?? 'Value' }}{{ $unit ? ' ('.$unit.')' : '' }}"
                       @if ($describedby) aria-describedby="{{ $describedby }}" @endif @if ($f->invalid()) aria-invalid="true" @endif :disabled="disabled" :readonly="readonly"
                       x-on:input="typeIn()" x-on:blur="commitText()" x-on:keydown.enter.prevent="commitText()">
                @if ($unit)<span class="f-affix">{{ $unit }}</span>@endif
            </div>
        @endif
    </div>
    @if ($name)<input type="hidden" name="{{ $name }}" value="{{ $start }}" :value="value ?? ''" :disabled="disabled">@endif
</x-form.field>
