{{-- Month grid shared by date and date-range. $range = true adds range highlighting. Needs the calendar mixin (view, cells, pick, moveFocus...). --}}
<div class="f-cal" x-ref="cal">
    @if ($range && ! empty($presets))
        <div class="f-presets">@foreach ($presets as $p)<button type="button" class="f-preset" :data-on="activePreset() === '{{ $p['key'] }}' ? 'true' : 'false'" x-on:click="preset('{{ $p['key'] }}')">{{ $p['label'] }}</button>@endforeach</div>
    @endif
    <div class="f-cal-head">
        <button type="button" class="f-icon-btn" aria-label="Previous month" x-on:click="shift(-1)"><x-form.icon name="chevron-left" /></button>
        <span x-text="monthLabel" aria-live="polite"></span>
        <button type="button" class="f-icon-btn" aria-label="Next month" x-on:click="shift(1)"><x-form.icon name="chevron-right" /></button>
    </div>
    <div class="f-cal-grid" role="grid">
        <template x-for="d in dows" :key="d"><div class="f-cal-dow" role="columnheader" x-text="d"></div></template>
        <template x-for="c in cells" :key="c.iso">
            <button type="button" class="f-day" :data-iso="c.iso" :data-out="c.out ? 'true' : 'false'" :data-today="c.iso === today ? 'true' : 'false'"
                    @if ($range) :data-in-range="inRange(c.iso) ? 'true' : 'false'" :data-edge="isEdge(c.iso) ? 'true' : 'false'" x-on:mouseenter="hover = c.iso"
                    @else :data-edge="c.iso === value ? 'true' : 'false'" @endif
                    :disabled="disabledDay(c.iso)" :tabindex="tabIso === c.iso ? 0 : -1" :aria-label="c.iso" :aria-pressed="@if ($range) isEdge(c.iso) @else c.iso === value @endif ? 'true' : 'false'"
                    x-on:click="pick(c.iso)" x-on:keydown="moveFocus($event, c.iso)" x-text="c.day"></button>
        </template>
    </div>
    <div style="display:flex;justify-content:space-between;margin-top:0.5rem">
        <button type="button" class="f-link" x-on:click="@if ($range) preset('today') @else pick(today) @endif">Today</button>
        <button type="button" class="f-link" x-on:click="clear(); closeCal()" x-show="@if ($range) value.from @else value @endif">Clear</button>
    </div>
</div>
