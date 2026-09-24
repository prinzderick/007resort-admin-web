@props(['title', 'text' => null, 'icon' => 'info', 'action' => null, 'href' => null])
<div class="flex flex-col items-center px-6 py-10 text-center" data-testid="empty-state">
    <span class="mb-3 flex size-12 items-center justify-center rounded-full bg-stone-100 text-stone-400"><x-icon :name="$icon" class="size-6" /></span>
    <div class="text-sm font-semibold text-stone-800">{{ $title }}</div>
    @if ($text)<p class="mt-1 max-w-md text-sm text-stone-500">{{ $text }}</p>@endif
    @if ($action && $href)<x-btn class="mt-4" :href="$href" icon="plus">{{ $action }}</x-btn>@endif
    {{ $slot }}
</div>
