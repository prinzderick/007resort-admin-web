@props(['of', 'what' => 'This section'])
@php /** @var \App\Support\Fetch $of */ @endphp
@unless ($of->ok())
    @php
        $text = match ($of->state) {
            'pending' => "{$what} is waiting on an API endpoint that is not in the contract yet.",
            'missing' => "{$what} is not available: this API build has not implemented the endpoint yet.",
            'forbidden' => "{$what} is hidden: your account lacks permission for it.",
            'unreachable' => "{$what} could not be loaded: the API is unreachable.",
            default => "{$what} could not be loaded.",
        };
        $tone = in_array($of->state, ['pending', 'missing', 'forbidden'], true) ? 'border-stone-300 bg-stone-50 text-stone-700' : 'border-red-300 bg-red-50 text-red-900';
    @endphp
    <div class="rounded-lg border border-dashed px-4 py-3 text-sm {{ $tone }}" data-testid="fetch-{{ $of->state }}" data-state="{{ $of->state }}">
        <div class="font-medium">{{ $text }}</div>
        @if ($of->message && $of->state === 'error')<div class="mt-0.5 text-xs opacity-80">{{ $of->message }}</div>@endif
    </div>
@endunless
