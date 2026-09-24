{{-- Single-handle track shared by x-form.slider and the duration control. Needs the fSlider scope (value, pos, min, max, label, down, key). --}}
<div class="f-track-wrap" :data-disabled="!canEdit ? 'true' : 'false'">
    <div class="f-track" x-ref="track" x-on:pointerdown="down($event)">
        <div class="f-fill" :style="`left:0;width:${pos}%`"></div>
        <template x-for="t in ticksList" :key="t"><span class="f-tick" :style="`left:${(t - min) / (max - min) * 100}%`" x-text="tickLabel(t)"></span></template>
        <template x-for="p in points" :key="'p' + p"><span class="f-snap" :style="`left:${(p - min) / (max - min) * 100}%`"></span></template>
        <div class="f-thumb" x-ref="thumb" role="slider" tabindex="0" :tabindex="disabled ? -1 : 0" :style="`left:${pos}%`" :aria-valuemin="min" :aria-valuemax="max" :aria-valuenow="value" :aria-valuetext="label"
             aria-orientation="horizontal" :aria-disabled="disabled ? 'true' : 'false'" :aria-readonly="readonly ? 'true' : 'false'" :data-active="active ? 'true' : 'false'" x-on:keydown="key($event)"
             aria-labelledby="{{ $labelledby ?? '' }}" @if (! empty($describedby)) aria-describedby="{{ $describedby }}" @endif>
            @if ($bubble ?? true)<span class="f-bubble" x-text="label" aria-hidden="true"></span>@endif
        </div>
    </div>
</div>
