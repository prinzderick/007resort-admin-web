@props(['name' => null, 'label' => null, 'hint' => null, 'description' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false,
        'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'value' => false, 'onText' => 'On', 'offText' => 'Off', 'card' => false, 'showState' => true, 'loading' => false, 'hideLabel' => false])
{{-- iOS-style switch: label + description + on/off text. Posts "1"/"0"; Livewire/Alpine model is a boolean. --}}
@php
    $on = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    $desc = $description ?? ($meta['description'] ?? null);
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['label' => null, 'meta' => array_diff_key((array) $meta, ['description' => 1]), 'value' => $on]));
    $by = trim($f->describedBy().($desc ? ' '.$f->id.'-desc' : ''));
@endphp
<x-form.field :f="$f" type="toggle" :x-data="$f->xData('fToggle')" x-modelable="value" {{ $attributes }}>
    <button type="button" id="{{ $f->id }}" role="switch" class="f-toggle" data-card="{{ $card ? 'true' : 'false' }}" aria-checked="{{ $on ? 'true' : 'false' }}" :aria-checked="value ? 'true' : 'false'"
            aria-labelledby="{{ $f->id }}-label" @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif x-on:click="toggle()" :disabled="disabled" @if ($disabled) disabled @endif>
        <span class="f-switch" aria-hidden="true"></span>
        <span class="f-toggle-text {{ $hideLabel ? 'sr-only' : '' }}">
            <span class="f-toggle-title" id="{{ $f->id }}-label">{{ $label }}@if ($required)<span class="f-req" aria-hidden="true">*</span>@endif <x-form.aside :f="$f" /></span>
            @if ($desc)<span class="f-toggle-desc" id="{{ $f->id }}-desc">{{ $desc }}</span>@endif
        </span>
        @if ($showState)<span class="f-state" aria-hidden="true" x-text="value ? @js($onText) : @js($offText)">{{ $on ? $onText : $offText }}</span>@endif
    </button>
    @if ($name)<input type="hidden" name="{{ $name }}" value="{{ $on ? '1' : '0' }}" :value="value ? '1' : '0'" :disabled="disabled">@endif
</x-form.field>
