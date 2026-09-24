@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => null, 'min' => null, 'max' => null, 'placeholder' => 'e.g. 24 Sep 2026'])
{{-- Single date. Type "24/09/2026", "24 Sep 2026" or "2026-09-24", or pick from the calendar. Model/posted value is ISO "YYYY-MM-DD". --}}
@php
    $f = \App\Support\Form\FormField::resolve($attributes, get_defined_vars());
    $by = $f->describedBy();
@endphp
<x-form.field :f="$f" type="date" :x-data="$f->xData('fDate', ['min' => $min, 'max' => $max, 'required' => $required])" x-modelable="value" {{ $attributes }}>
    <div class="f-anchor" x-on:click.outside="closeCal()" x-on:keydown="key($event)">
        <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'">
            <input id="{{ $f->id }}" x-ref="input" type="text" autocomplete="off" class="f-input" value="{{ $value ? \App\Support\Form\Format::date((string) $value) : '' }}" x-model="text" placeholder="{{ $placeholder }}" @if ($bare && $label) aria-label="{{ $label }}" @endif
                   @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif @if ($required) aria-required="true" @endif :disabled="disabled" :readonly="readonly" x-on:blur="commit()">
            <button type="button" class="f-icon-btn" aria-label="Clear date" x-show="value && canEdit" x-cloak x-on:click="clear()"><x-form.icon name="x" /></button>
            <button type="button" class="f-icon-btn" aria-label="Open calendar" :aria-expanded="open ? 'true' : 'false'" :disabled="disabled || readonly" x-on:click="open ? closeCal() : openCal()"><x-form.icon name="calendar" /></button>
        </div>
        <div class="f-pop" x-show="open" x-cloak role="dialog" aria-label="Choose date">@include('components.form._calendar', ['range' => false, 'presets' => []])</div>
    </div>
    @if ($name)<input type="hidden" name="{{ $name }}" value="{{ $value }}" :value="value ?? ''" :disabled="disabled">@endif
</x-form.field>
