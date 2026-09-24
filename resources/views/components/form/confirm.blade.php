@props(['phrase' => null, 'title' => 'Are you sure?', 'message' => null, 'confirmLabel' => 'Confirm', 'cancelLabel' => 'Cancel', 'tone' => 'danger', 'wire' => null, 'form' => null, 'args' => [], 'id' => null, 'caseSensitive' => true])
{{-- Typed confirmation for dangerous changes. The trigger slot opens a modal; the confirm button stays disabled until the person types the phrase exactly.
     On confirm it (1) dispatches a bubbling "f-confirmed" event, (2) calls $wire[wire](...args) when :wire is set, (3) submits the form with id :form when set.
     <x-form.confirm phrase="DISABLE PAYMENTS" wire="disablePayments"><x-slot:trigger><button type="button" class="f-btn" data-variant="danger">Disable</button></x-slot:trigger></x-form.confirm> --}}
@php $cid = $id ?? 'confirm-'.substr(md5((string) $title.(string) $phrase), 0, 6); @endphp
<div x-data="fConfirm({ phrase: @js($phrase), id: @js($cid), wire: @js($wire), form: @js($form), args: @js($args), caseSensitive: @js($caseSensitive) })" x-on:keydown.escape.window="open && hide()" {{ $attributes }}>
    <span x-ref="trigger" x-on:click.prevent="show()" style="display:contents">{{ $trigger ?? '' }}</span>
    <template x-teleport="body">
        <div class="f-modal-back" x-show="open" x-cloak x-trap.noscroll="open" role="presentation" x-on:mousedown.self="hide()">
            <div class="f-modal" role="alertdialog" aria-modal="true" aria-labelledby="{{ $cid }}-t" @if ($message) aria-describedby="{{ $cid }}-d" @endif style="font-size:0.875rem;color:var(--color-stone-900)">
                <h2 id="{{ $cid }}-t">{{ $title }}</h2>
                @if ($message)<p id="{{ $cid }}-d">{{ $message }}</p>@endif
                {{ $slot }}
                @if ($phrase)
                    <div class="f-field" style="margin-top:1rem;--f-brand:var(--color-brand-600,#0f7d4f);--f-ring:rgb(31 149 96 / 0.3);--f-border:var(--color-stone-300,#cfd5dd);--f-radius:0.625rem;--f-h:2.75rem;--f-muted:var(--color-stone-500,#6b7583)">
                        <label class="f-label" for="{{ $cid }}-in" style="display:block;margin-bottom:0.375rem">Type <span class="f-phrase">{{ $phrase }}</span> to confirm</label>
                        <div class="f-box"><input id="{{ $cid }}-in" x-ref="typed" type="text" class="f-input" x-model="typed" autocomplete="off" autocapitalize="off" spellcheck="false" x-on:keydown.enter.prevent="ok()"></div>
                    </div>
                @endif
                <div class="f-modal-actions">
                    <button type="button" class="f-btn" x-on:click="hide()">{{ $cancelLabel }}</button>
                    <button type="button" x-ref="ok" class="f-btn" data-variant="{{ $tone }}" :disabled="!matches" x-on:click="ok()">{{ $confirmLabel }}</button>
                </div>
            </div>
        </div>
    </template>
</div>
