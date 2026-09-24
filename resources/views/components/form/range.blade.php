@props(['name' => null, 'label' => null, 'hint' => null, 'meta' => null, 'error' => null, 'errorKey' => null, 'required' => false, 'optional' => false, 'disabled' => false, 'readonly' => false,
        'id' => null, 'bare' => false, 'value' => null, 'min' => 0, 'max' => 100, 'step' => 1, 'minGap' => 0, 'maxGap' => null, 'unit' => null, 'prefix' => null, 'unitGlue' => '', 'ticks' => 0,
        'snap' => [], 'format' => null, 'fromLabel' => 'From', 'toLabel' => 'To', 'loading' => false])
{{-- Dual-handle from-to range (price band, hours, capacity band). Value is {from, to}. minGap/maxGap validate the span. Posts name[from] and name[to]. --}}
@php
    $v = is_array($value) ? $value : ['from' => $min, 'to' => $max];
    $f = \App\Support\Form\FormField::resolve($attributes, array_replace(get_defined_vars(), ['value' => $v]));
    $by = $f->describedBy();
@endphp
<x-form.field :f="$f" type="range" :label-for="false" :x-data="$f->xData('fRange', ['min' => $min, 'max' => $max, 'step' => $step, 'minGap' => $minGap, 'maxGap' => $maxGap, 'unit' => $unit, 'prefix' => $prefix, 'unitGlue' => $unitGlue, 'ticks' => $ticks, 'snap' => $snap, 'format' => $format, 'default' => $meta['default']['value'] ?? null])" x-modelable="value" {{ $attributes }}>
    <div role="group" aria-labelledby="{{ $f->id }}-label" @if ($by) aria-describedby="{{ $by }}" @endif>
        <div class="f-track-wrap" :data-disabled="!canEdit ? 'true' : 'false'">
            <div class="f-track" x-ref="track" x-on:pointerdown="down($event)">
                <div class="f-fill" :style="fillStyle"></div>
                <template x-for="t in ticksList" :key="t"><span class="f-tick" :style="`left:${(t - min) / (max - min) * 100}%`" x-text="tickLabel(t)"></span></template>
                @foreach (['from' => $fromLabel, 'to' => $toLabel] as $end => $endLabel)
                    <div class="f-thumb" x-ref="{{ $end }}" role="slider" :tabindex="disabled ? -1 : 0" tabindex="0" :style="`left:${pos('{{ $end }}')}%;z-index:${top === '{{ $end }}' ? 3 : 2}`" :aria-valuemin="{{ $end === 'to' ? 'value.from' : 'min' }}" :aria-valuemax="{{ $end === 'from' ? 'value.to' : 'max' }}"
                         :aria-valuenow="value.{{ $end }}" :aria-valuetext="fmt(value.{{ $end }})" aria-label="{{ $endLabel }}" aria-orientation="horizontal" :data-active="active === '{{ $end }}' ? 'true' : 'false'" x-on:keydown="key($event, '{{ $end }}')">
                        <span class="f-bubble" x-text="fmt(value.{{ $end }})" aria-hidden="true"></span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="f-range-summary">
            @foreach (['from' => $fromLabel, 'to' => $toLabel] as $end => $endLabel)
                @if ($end === 'to')<span class="f-range-sep" aria-hidden="true">to</span>@endif
                <div class="f-box" :data-disabled="disabled ? 'true' : 'false'" :data-readonly="readonly ? 'true' : 'false'">
                    @if ($prefix)<span class="f-affix">{{ $prefix }}</span>@endif
                    <input type="text" inputmode="decimal" autocomplete="off" class="f-input f-num" value="{{ $v[$end] ?? '' }}" x-model="texts.{{ $end }}" aria-label="{{ $endLabel }}{{ $label ? ' ('.$label.')' : '' }}" :disabled="disabled" :readonly="readonly"
                           x-on:blur="commitText('{{ $end }}')" x-on:keydown.enter.prevent="commitText('{{ $end }}')">
                    @if ($unit)<span class="f-affix">{{ $unit }}</span>@endif
                </div>
            @endforeach
        </div>
    </div>
    @if ($name)<input type="hidden" name="{{ $name }}[from]" value="{{ $v['from'] ?? '' }}" :value="value.from" :disabled="disabled"><input type="hidden" name="{{ $name }}[to]" value="{{ $v['to'] ?? '' }}" :value="value.to" :disabled="disabled">@endif
</x-form.field>
