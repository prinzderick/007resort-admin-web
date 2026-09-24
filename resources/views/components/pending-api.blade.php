@props(['items', 'title' => 'Waiting on the API'])
<div class="rounded-xl border border-dashed border-stone-300 bg-stone-50 p-4 text-sm text-stone-700" data-testid="pending-api">
    <div class="mb-1 font-semibold">{{ $title }}</div>
    <p class="mb-2 text-stone-600">These capabilities are part of the plan but the API contract does not define them yet, so they are not offered here (nothing is faked).</p>
    <ul class="list-disc space-y-0.5 pl-5">
        @foreach ($items as $item)<li>{{ $item }}</li>@endforeach
    </ul>
</div>
