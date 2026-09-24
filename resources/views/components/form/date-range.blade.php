@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false, 'id' => null, 'bare' => false, 'loading' => false, 'value' => null, 'min' => null, 'max' => null, 'maxDays' => null, 'presets' => ['today' => 'Today', 'yesterday' => 'Yesterday', 'last7' => 'Last 7 days', 'last30' => 'Last 30 days', 'thisMonth' => 'This month', 'lastMonth' => 'Last month'], 'placeholder' => 'Choose dates'])
{{-- From-to dates with quick presets. Value {from, to} as ISO dates. Click a start day, then an end day. Posts name[from] / name[to]. --}}
@php
    $v = is_array($value) ? $value : ['from' => null, 'to' => null];
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => $v]));
    $by = $f->describedBy();
    $plist = collect($presets)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values()->all();
@endphp
<x-form.field :f="$f" type="date-range" :x-data="$f->xData('fDateRange', ['min' => $min, 'max' => $max, 'maxDays' => $maxDays, 'presets' => $plist])" x-modelable="value" {{ $attributes }}>
    <div class="f-anchor" x-on:click.outside="closeCal()" x-on:keydown="key($event)">
        <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'">
            <button type="button" id="{{ $f->id }}" class="f-select-btn" aria-haspopup="dialog" :aria-expanded="open ? 'true' : 'false'" :disabled="disabled || readonly" x-on:click="open ? closeCal() : openCal()" @if ($by) aria-describedby="{{ $by }}" @endif @if ($f->invalid()) aria-invalid="true" @endif>
                <x-form.icon name="calendar" class="size-4" /><span x-text="label || '{{ $placeholder }}'" :class="label ? '' : 'f-placeholder'">{{ $v['from'] ? \App\Support\Form\Format::date($v['from']).' - '.\App\Support\Form\Format::date($v['to'] ?? '') : $placeholder }}</span>
                <x-form.icon name="chevron" class="f-caret" />
            </button>
            <button type="button" class="f-icon-btn" aria-label="Clear dates" x-show="(value.from || value.to) && canEdit" x-cloak x-on:click="clear()"><x-form.icon name="x" /></button>
        </div>
        <div class="f-pop" x-show="open" x-cloak role="dialog" aria-label="Choose date range" x-on:mouseleave="hover = null">@include('components.form._calendar', ['range' => true, 'presets' => $plist])</div>
    </div>
    @if ($name)<input type="hidden" name="{{ $name }}[from]" value="{{ $v['from'] ?? '' }}" :value="value?.from ?? ''" :disabled="disabled"><input type="hidden" name="{{ $name }}[to]" value="{{ $v['to'] ?? '' }}" :value="value?.to ?? ''" :disabled="disabled">@endif
</x-form.field>
