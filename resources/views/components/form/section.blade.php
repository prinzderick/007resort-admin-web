@props(['title' => null, 'description' => null, 'stacked' => false, 'cols' => 1, 'id' => null])
{{-- A grouped block of fields: title + plain-language description on the left, fields on the right (stacks on narrow screens). Put x-slot:aside in the header for a badge or action. --}}
<section {{ $attributes->class(['f-section']) }} data-stacked="{{ $stacked ? 'true' : 'false' }}" @if ($id) id="{{ $id }}" @endif @if ($title) aria-labelledby="{{ ($id ?? 'sec') }}-t" @endif>
    @if ($title || $description)
        <div>
            @if ($title)<h3 class="f-section-title" id="{{ ($id ?? 'sec') }}-t">{{ $title }}</h3>@endif
            @if ($description)<p class="f-section-desc">{{ $description }}</p>@endif
            @isset ($aside)<div style="margin-top:0.75rem">{{ $aside }}</div>@endisset
        </div>
    @endif
    <div class="f-section-body" data-cols="{{ $cols }}">{{ $slot }}</div>
</section>
