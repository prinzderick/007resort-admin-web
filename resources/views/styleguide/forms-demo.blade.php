<form wire:submit="save" x-data class="grid" style="gap:1.25rem" data-testid="livewire-demo">
    <x-form.section title="Live binding" description="Every control below is bound with wire:model. Try a hold time of 5 seconds or a price over 1,000,000 and press Save: the 422-style messages appear on the field." stacked>
        <div class="f-sg-grid">
            @foreach ($definitions as $def)
                <x-form.rule-field :def="$def" :value="$settings[$def['key']]" :wire="'settings.'.$def['key']" :live="in_array($def['key'], ['cancel_fee_percent'])" />
            @endforeach
            <x-form.money wire:model="price" label="Package price" hint="Stays a decimal string in the component." />
            <x-form.range wire:model="band" label="Group size band" :min="0" :max="100" :step="5" unit=" guests" :min-gap="10" />
            <x-form.stepper wire:model="seats" label="Seats per table" :min="1" :max="20" />
            <x-form.toggle wire:model="confirm" label="I understand" description="Needed only when the cash-session control is switched off." />
        </div>
        @if ($saved)<p class="f-hint" style="color:var(--color-brand-800)" role="status" data-testid="saved">{{ $saved }}</p>@endif
        <p class="f-hint" data-testid="wire-state">Component state: price=<code>{{ json_encode($price) }}</code> band=<code>{{ json_encode($band) }}</code> seats=<code>{{ $seats }}</code> hold=<code>{{ json_encode($settings['hold_ttl_seconds']) }}</code></p>
    </x-form.section>
    <x-form.actions />
</form>
