@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false,
        'id' => null, 'bare' => false, 'value' => null, 'min' => null, 'max' => null, 'step' => 1, 'unit' => null, 'nullable' => false, 'loading' => false, 'placeholder' => null])
{{-- Minus / number / plus. Type a number, use ArrowUp/ArrowDown, or hold a button to repeat. --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, get_defined_vars());
    $by = $f->describedBy();
@endphp
<x-form.field :f="$f" type="stepper" :x-data="$f->xData('fStepper', ['min' => $min, 'max' => $max, 'step' => $step, 'nullable' => $nullable])" x-modelable="value" {{ $attributes }}>
    <div class="f-stepper" data-disabled="{{ $disabled ? 'true' : 'false' }}" :data-disabled="disabled ? 'true' : 'false'">
        <button type="button" class="f-icon-btn" tabindex="-1" aria-label="Decrease{{ $label ? ' '.$label : '' }}" :disabled="disabled || readonly || atMin" x-on:pointerdown.prevent="hold(-1)" x-on:pointerup="release()" x-on:pointerleave="release()" x-on:pointercancel="release()"><x-form.icon name="minus" /></button>
        <input id="{{ $f->id }}" type="text" inputmode="decimal" autocomplete="off" role="spinbutton" class="f-input f-num" value="{{ $value }}" x-model="text" placeholder="{{ $placeholder }}"
               @if ($min !== null) aria-valuemin="{{ $min }}" @endif @if ($max !== null) aria-valuemax="{{ $max }}" @endif :aria-valuenow="value" @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif
               @if ($required) aria-required="true" @endif :disabled="disabled" :readonly="readonly" x-on:blur="commit()" x-on:keydown="key($event)" x-on:keydown.enter.prevent="commit()">
        @if ($unit)<span class="f-affix" data-plain="true">{{ $unit }}</span>@endif
        <button type="button" class="f-icon-btn" tabindex="-1" aria-label="Increase{{ $label ? ' '.$label : '' }}" :disabled="disabled || readonly || atMax" x-on:pointerdown.prevent="hold(1)" x-on:pointerup="release()" x-on:pointerleave="release()" x-on:pointercancel="release()"><x-form.icon name="plus" /></button>
    </div>
    @if ($name)<input type="hidden" name="{{ $name }}" value="{{ $value }}" :value="value ?? ''" :disabled="disabled">@endif
</x-form.field>
