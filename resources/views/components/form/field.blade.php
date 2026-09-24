@props(['f', 'type' => 'field', 'labelFor' => true])
{{--
  The shell every x-form.* control renders inside: label row (required marker, "Edited" dirty dot, badges), plain-language description,
  the control (slot), default-value row with reset, help text and the error message. `bare` drops all of it (nested use).
  The element carries the control's x-data / x-modelable / wire:model attributes (passed through).
--}}
@php
    $meta = $f->meta;
    $badges = $meta['badges'] ?? [];
    $default = $meta['default'] ?? null;
    $invalid = $f->invalid();
@endphp
<div {{ $attributes->class(['f-field']) }} data-f="{{ $type }}" data-invalid="{{ $invalid ? 'true' : 'false' }}" @if ($f->loading) data-loading="true" @endif @if ($f->disabled) data-disabled="true" @endif>
    @unless ($f->bare)
        @if ($f->label)
            <div class="f-head">
                @if ($labelFor)
                    <label for="{{ $f->id }}" id="{{ $f->id }}-label" class="f-label">{{ $f->label }}@if ($f->required)<span class="f-req" aria-hidden="true">*</span><span class="sr-only"> (required)</span>@endif</label>
                @else
                    <span id="{{ $f->id }}-label" class="f-label">{{ $f->label }}@if ($f->required)<span class="f-req" aria-hidden="true">*</span><span class="sr-only"> (required)</span>@endif</span>
                @endif
                @if ($f->optional && ! $f->required)<span class="f-opt">Optional</span>@endif
                <span class="f-aside">
                    @foreach ($badges as $b)<span class="f-badge" data-tone="{{ $b['tone'] ?? 'neutral' }}" @if (! empty($b['title'])) title="{{ $b['title'] }}" @endif>{{ $b['text'] }}</span>@endforeach
                    <span class="f-dirty" x-show="$data.dirty" x-cloak>Edited</span>
                </span>
            </div>
        @endif
        @if (! empty($meta['description']))<p class="f-rule-desc" id="{{ $f->id }}-desc">{{ $meta['description'] }}</p>@endif
    @endunless
    {{ $slot }}
    @unless ($f->bare)
        @if ($default)
            <div class="f-default"><span>{{ $default['text'] }}</span><button type="button" class="f-link" x-show="$data.hasDefault && !$data.atDefault" x-cloak x-on:click="$data.resetDefault()" @if ($f->disabled) disabled @endif>Reset to default</button></div>
        @endif
        @if ($f->hint)<p class="f-hint" id="{{ $f->id }}-hint">{{ $f->hint }}</p>@endif
        @if ($invalid)<p class="f-error" id="{{ $f->id }}-error" role="alert">{{ $f->message() }}</p>@endif
        <p class="f-error" x-show="$data.localError" x-cloak x-text="$data.localError" role="alert" aria-live="polite"></p>
    @endunless
</div>
