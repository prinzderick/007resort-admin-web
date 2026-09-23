@if (session('status'))
    <div class="mb-4 rounded-lg border border-stone-300 bg-white px-4 py-3 text-sm" role="status">{{ session('status') }}</div>
@endif
@if (session('success'))
    <div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('success') }}</div>
@endif
@if (session('pending'))
    <div class="mb-4 rounded-lg border-2 border-amber-400 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status" data-testid="pending-approval">
        <div class="font-semibold">Awaiting approval</div>
        <div>{{ session('pending') }}</div>
        @if (session('pending_link'))
            <a class="mt-1 inline-block font-medium underline" href="{{ session('pending_link') }}">Open approvals queue</a>
        @endif
    </div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">{{ session('error') }}</div>
@elseif ($errors->any())
    <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">Please correct the highlighted fields.</div>
@endif
