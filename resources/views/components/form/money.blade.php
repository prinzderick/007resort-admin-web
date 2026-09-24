@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false,
        'id' => null, 'bare' => false, 'value' => null, 'currency' => '₦', 'scale' => 2, 'min' => null, 'max' => null, 'step' => '1', 'negative' => false, 'quick' => [], 'placeholder' => '0.00', 'loading' => false])
{{-- Naira amount input. The model/posted value is a DECIMAL STRING ("1234500.00"), never a number: thousands separators are display only. --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, get_defined_vars());
    $by = $f->describedBy();
    $shown = $value === null || $value === '' ? '' : \App\Support\Form\Format::money((string) $value, min(2, $scale));
@endphp
<x-form.field :f="$f" type="money" :x-data="$f->xData('fMoney', ['scale' => $scale, 'min' => $min, 'max' => $max, 'step' => $step, 'negative' => $negative, 'currency' => $currency, 'quick' => $quick, 'required' => $required])" x-modelable="value" {{ $attributes }}>
    <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'">
        <span class="f-affix" aria-hidden="true">{{ $currency }}</span>
        <input id="{{ $f->id }}" type="text" inputmode="decimal" autocomplete="off" class="f-input f-num" value="{{ $shown }}" x-model="text" placeholder="{{ $placeholder }}" @if ($by) aria-describedby="{{ $by }}" @endif
               @if ($f->invalid()) aria-invalid="true" @endif @if ($required) aria-required="true" @endif :disabled="disabled" :readonly="readonly" x-on:input="onInput($event)" x-on:blur="commit()" x-on:keydown="key($event)" x-on:keydown.enter="commit()">
    </div>
    @if ($quick)
        <div class="f-presets" style="margin-top:0.5rem">@foreach ($quick as $q)<button type="button" class="f-preset" x-on:click="quick(@js((string) $q))" :disabled="!canEdit">{{ $currency }}{{ \App\Support\Form\Format::money((string) $q, 0) }}</button>@endforeach</div>
    @endif
    @if ($name)<input type="hidden" name="{{ $name }}" value="{{ $value }}" :value="value ?? ''" :disabled="disabled" data-money="decimal-string">@endif
</x-form.field>
