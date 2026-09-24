@props(['submit' => 'Save changes', 'cancel' => 'Discard', 'loadingText' => 'Saving...', 'loading' => false, 'showCancel' => true, 'flush' => false, 'savedText' => 'All changes saved', 'dirtyText' => 'Unsaved changes', 'disabled' => false, 'variant' => 'primary'])
{{-- Sticky save bar. Put it inside the <form>: it listens to every control's dirty state, shows "Unsaved changes", disables the buttons while a save is in flight (a second submit is
     swallowed), and Discard reverts every control to its loaded value. Livewire: dispatch('form-saved') after a successful save to re-baseline the controls. --}}
<div {{ $attributes->class(['f-actions']) }} x-data="fActions({ loading: {{ $loading ? 'true' : 'false' }} })" :data-dirty="dirty ? 'true' : 'false'" data-flush="{{ $flush ? 'true' : 'false' }}" data-component="save-bar" role="region" aria-label="Form actions">
    <div class="f-actions-status" :data-dirty="dirty ? 'true' : 'false'" role="status" aria-live="polite">
        <i></i>
        <span x-show="dirty" x-cloak><span x-text="dirtyCount === 1 ? '1 unsaved change' : dirtyCount + ' unsaved changes'">{{ $dirtyText }}</span></span>
        <span x-show="!dirty && !busy">{{ $savedText }}</span>
        <span x-show="busy" x-cloak>{{ $loadingText }}</span>
    </div>
    {{ $slot }}
    <div class="f-actions-buttons">
        @if ($showCancel)<button type="button" class="f-btn" x-on:click="cancel()" :disabled="!dirty || busy">{{ $cancel }}</button>@endif
        <button type="submit" class="f-btn" data-variant="{{ $variant }}" :data-loading="busy ? 'true' : 'false'" :disabled="busy" :aria-busy="busy ? 'true' : 'false'" @if ($disabled) disabled @endif><span x-text="busy ? '{{ $loadingText }}' : '{{ $submit }}'">{{ $submit }}</span></button>
    </div>
</div>
