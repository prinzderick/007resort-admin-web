{{-- Where the "Get tickets" button goes: nowhere, an outside web page, or one of the resort's own ticket products. --}}
<div class="grid gap-4" data-testid="ticket-picker">
    <x-form.radio-cards name="ticketMode" label="Ticket button" :options="[
        ['value' => 'none', 'label' => 'No ticket button', 'description' => 'Free or walk-in events.'],
        ['value' => 'url', 'label' => 'A web page', 'description' => 'Send people to another site.'],
        ['value' => 'product', 'label' => 'The resort\'s tickets', 'description' => 'Sell through your own ticket product.'],
    ]" :value="old('ticketMode', $values['ticketMode'] ?? 'none')" x-model="f['ticketMode']" :disabled="! $canEdit" />
    <div x-show="f.ticketMode === 'url'" x-cloak><x-cms.field :spec="\App\Support\Cms\Resources::get('events')['extra'][0]" :values="$values" :options="[]" :can-edit="$canEdit" /></div>
    <div x-show="f.ticketMode === 'product'" x-cloak>
        @if ($products === [])<p class="rounded-lg border border-dashed border-stone-300 px-3 py-2 text-sm text-stone-600">There are no ticket products yet. Add one under Setup, Catalog and prices (kind: Ticket), then come back.</p>
        @else<x-cms.field :spec="\App\Support\Cms\Resources::get('events')['extra'][1]" :values="$values" :options="['products' => $products]" :can-edit="$canEdit" />@endif
    </div>
</div>
