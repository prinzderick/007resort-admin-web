{{-- Success and status messages are toasts (see x-toasts); errors and awaiting-approval stay inline so they cannot be missed. --}}
@if (session('pending'))
    <div class="mb-4 rounded-xl border-2 border-amber-400 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status" data-testid="pending-approval">
        <div class="font-semibold">Awaiting approval</div>
        <div>{{ session('pending') }}</div>
        @if (session('pending_link'))<a class="mt-1 inline-block font-medium underline" href="{{ session('pending_link') }}">Open approvals queue</a>@endif
    </div>
@endif
@if (session('error'))
    <x-alert tone="danger" data-code="{{ session('error_code') }}">{{ session('error') }}
        @if (session('blockers'))<ul class="mt-1.5 list-disc space-y-0.5 pl-5" data-testid="blockers">@foreach (session('blockers') as $b)<li>{{ $b }}</li>@endforeach</ul>@endif
        @if (session('error_code') === 'concurrency_conflict')<a class="mt-1.5 inline-block font-medium underline" href="{{ url()->previous() }}">Reload the latest version</a>@endif
    </x-alert>
@elseif ($errors->any())
    <x-alert tone="danger">Please correct the highlighted fields.</x-alert>
@endif
{{-- The same messages, for screens (and tests) that read the page text: hidden visually, announced by toasts. --}}
@if (session('success'))<div class="sr-only" role="status" data-testid="flash-success">{{ session('success') }}</div>@endif
@if (session('status'))<div class="sr-only" role="status">{{ session('status') }}</div>@endif
