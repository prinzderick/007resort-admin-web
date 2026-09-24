@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => null, 'step' => 30, 'min' => '00:00', 'max' => null, 'format' => '12', 'overnight' => false, 'minSpan' => null, 'allow24' => true, 'fromLabel' => 'Opens', 'toLabel' => 'Closes'])
{{-- From-to times (opening hours, shift, happy hour). Value {from, to} as "HH:MM". :overnight lets the end be after midnight (22:00 - 02:00). Posts name[from] / name[to]. --}}
@php
    $v = is_array($value) ? $value : ['from' => null, 'to' => null];
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => $v]));
    $by = $f->describedBy();
@endphp
<x-form.field :f="$f" type="time-range" :label-for="false" :x-data="$f->xData('fTimeRange', ['overnight' => $overnight, 'minSpan' => $minSpan])" x-modelable="value" {{ $attributes }}>
    <div class="f-time-range" role="group" aria-labelledby="{{ $f->id }}-label" @if ($by) aria-describedby="{{ $by }}" @endif>
        <x-form.time bare x-model="from" :label="$fromLabel" :step="$step" :min="$min" :max="$max" :format="$format" :disabled="$disabled" :readonly="$readonly" :allow24="false" placeholder="{{ $fromLabel }}" />
        <span class="f-range-sep" aria-hidden="true">to</span>
        <x-form.time bare x-model="to" :label="$toLabel" :step="$step" :min="$min" :max="$max" :format="$format" :disabled="$disabled" :readonly="$readonly" :allow24="$allow24" placeholder="{{ $toLabel }}" />
        <span class="f-time-note" x-show="span" x-text="span" x-cloak></span>
    </div>
    @if ($name)<input type="hidden" name="{{ $name }}[from]" value="{{ $v['from'] ?? '' }}" :value="value?.from ?? ''" :disabled="disabled"><input type="hidden" name="{{ $name }}[to]" value="{{ $v['to'] ?? '' }}" :value="value?.to ?? ''" :disabled="disabled">@endif
</x-form.field>
