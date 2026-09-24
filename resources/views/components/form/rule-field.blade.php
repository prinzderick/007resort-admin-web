@props(['def', 'value' => '__default__', 'name' => null, 'wire' => null, 'live' => false, 'error' => null, 'disabled' => false, 'options' => null, 'control' => null, 'id' => null, 'showKey' => false])
{{-- Renders ONE backend rule definition with the right control: label, plain-language description, danger badge, "Default: ..." with reset, error state. See App\Support\Form\RuleControl for the mapping. --}}
@php
    $d = \App\Support\Form\RuleControl::describe($def, $control);
    $val = $value === '__default__' ? $d['default'] : $value;
    $props = $d['props'];
    if ($options !== null && (isset($props['options']) || $d['control'] === 'select')) {
        $props['options'] = \App\Support\Form\FormField::options($options);
    }
    $meta = ['badges' => $d['badges'], 'danger' => $def['dangerLevel'] ?? null, 'default' => ['text' => $d['defaultText'], 'value' => $d['default']]];
    $key = $def['key'] ?? 'rule';
    $isToggle = $d['control'] === 'toggle';
    if (! $isToggle) {
        $meta['description'] = $def['description'] ?? null;
    } else {
        $props['description'] = $def['description'] ?? null;
    }
    $attributes = $attributes->merge($props + ($wire ? [($live ? 'wire:model.live' : 'wire:model') => $wire] : []), false);
    $search = \Illuminate\Support\Js::from(mb_strtolower(($def['label'] ?? '').' '.($def['description'] ?? '').' '.$key.' '.($def['group'] ?? '')));
@endphp
<div class="f-rule" data-danger="{{ $def['dangerLevel'] ?? 'low' }}" data-rule="{{ $key }}" x-show="$data.match ? $data.match({{ $search }}) : true">
    <x-dynamic-component :component="'form.'.$d['control']" :id="$id ?? 'rule-'.str_replace('_', '-', $key)" :name="$name" :label="$def['label'] ?? $key" :meta="$meta" :value="$val" :error="$error" :disabled="$disabled" {{ $attributes }} />
    @if ($showKey)<code class="f-hint" style="display:block">{{ $key }}</code>@endif
</div>
