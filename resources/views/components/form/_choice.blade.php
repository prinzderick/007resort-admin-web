{{--
  Shared markup for segmented / radio-cards / checkbox-group. Needs: $f, $variant (segmented|pills|cards|checks), $opts (normalised), $multiple, $name, $cols, $block.
  Real <input type=radio|checkbox> elements sit invisibly over each option, so native keyboard and form posting keep working.
--}}
@php
    $type = $multiple ? 'checkbox' : 'radio';
    $group = $name ?: '_f_'.$f->id;
    $inputName = $name ? ($multiple ? $name.'[]' : $name) : $group;
    $current = $f->value;
    $on = fn ($v) => $multiple ? in_array((string) $v, array_map('strval', (array) $current), true) : ($current !== null && (string) $current === (string) $v);
    $by = $f->describedBy();
@endphp
<div class="{{ match ($variant) { 'cards' => 'f-cards', 'checks' => 'f-checks', default => 'f-seg' } }}" role="{{ $multiple ? 'group' : 'radiogroup' }}" aria-labelledby="{{ $f->id }}-label" @if ($by) aria-describedby="{{ $by }}" @endif
     @if ($variant === 'cards') data-cols="{{ $cols }}" @endif @if ($variant === 'segmented' || $variant === 'pills') data-block="{{ $block ? 'true' : 'false' }}" data-pills="{{ $variant === 'pills' ? 'true' : 'false' }}" @endif @if ($f->invalid()) aria-invalid="true" @endif>
    @foreach ($opts as $i => $o)
        @php $isOn = $on($o['value']); $dis = $f->disabled || $o['disabled']; @endphp
        @if ($variant === 'cards')
            <label class="f-card" data-on="{{ $isOn ? 'true' : 'false' }}" :data-on="isOn(options[{{ $i }}].value) ? 'true' : 'false'" data-disabled="{{ $dis ? 'true' : 'false' }}">
                <input type="{{ $type }}" name="{{ $inputName }}" value="{{ $o['value'] }}" x-ref="opt{{ $i }}" @checked($isOn) :checked="isOn(options[{{ $i }}].value)" x-on:change="pick({{ $i }})" :disabled="disabled || options[{{ $i }}].disabled" @if ($dis) disabled @endif @if (! $name) form="f-none" @endif>
                @if ($o['icon'])<span class="f-card-icon"><x-form.icon :name="$o['icon']" /></span>@endif
                <span class="f-card-body">
                    <span class="f-card-title">{{ $o['label'] }}@if ($o['badge'])<span class="f-badge" data-tone="low">{{ $o['badge'] }}</span>@endif</span>
                    @if ($o['description'])<span class="f-card-desc" style="display:block">{{ $o['description'] }}</span>@endif
                </span>
                <span class="f-mark" @if ($multiple) data-shape="square" @endif aria-hidden="true"><x-form.icon name="check" /></span>
            </label>
        @elseif ($variant === 'checks')
            <label class="f-check" data-on="{{ $isOn ? 'true' : 'false' }}" :data-on="isOn(options[{{ $i }}].value) ? 'true' : 'false'" data-disabled="{{ $dis ? 'true' : 'false' }}">
                <input type="checkbox" name="{{ $inputName }}" value="{{ $o['value'] }}" x-ref="opt{{ $i }}" @checked($isOn) :checked="isOn(options[{{ $i }}].value)" x-on:change="pick({{ $i }})" :disabled="disabled || options[{{ $i }}].disabled" @if ($dis) disabled @endif>
                <span class="f-mark" data-shape="square" aria-hidden="true"><x-form.icon name="check" /></span>
                <span><span class="f-check-label">{{ $o['label'] }}</span>@if ($o['description'])<span class="f-check-desc">{{ $o['description'] }}</span>@endif</span>
            </label>
        @else
            <label class="f-seg-item" data-on="{{ $isOn ? 'true' : 'false' }}" :data-on="isOn(options[{{ $i }}].value) ? 'true' : 'false'" data-disabled="{{ $dis ? 'true' : 'false' }}">
                <input type="{{ $type }}" name="{{ $inputName }}" value="{{ $o['value'] }}" x-ref="opt{{ $i }}" @checked($isOn) :checked="isOn(options[{{ $i }}].value)" x-on:change="pick({{ $i }})" :disabled="disabled || options[{{ $i }}].disabled" @if ($dis) disabled @endif @if (! $name) form="f-none" @endif
                       @if ($loop->first) id="{{ $f->id }}" @endif>
                @if ($o['icon'])<x-form.icon :name="$o['icon']" class="size-4" />@endif<span>{{ $o['label'] }}</span>
            </label>
        @endif
    @endforeach
</div>
