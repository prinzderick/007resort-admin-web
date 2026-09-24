<x-layouts.app title="Error">
    <x-page-header :title="$e->isUnreachable() ? 'The API is unreachable' : 'The API could not complete this request'" />
    <x-card>
        <p class="text-sm">{{ $message }}</p>
        @if ($e->problemCode())<p class="mt-2 text-xs text-stone-500">Code: {{ $e->problemCode() }}</p>@endif
        @if ($e->isUnreachable())
            <p class="mt-3 text-sm text-stone-600">Check that the API node is running and that <code>R007_API_BASE_URL</code> is correct. Nothing was changed.</p>
        @endif
        <div class="mt-4"><x-btn variant="secondary" :href="url()->previous()">Go back</x-btn></div>
    </x-card>
</x-layouts.app>
