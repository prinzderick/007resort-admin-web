@props(['value', 'short' => 8])
{{-- Monospace id/reference; click copies the full value. --}}
<button type="button" x-data="{ done: false }" @click="navigator.clipboard?.writeText(@js((string) $value)); done = true; setTimeout(() => done = false, 1200)" class="mono rounded px-1 py-0.5 text-stone-600 hover:bg-stone-100" title="{{ $value }} (click to copy)" aria-label="Copy {{ $value }}"><span x-show="!done">{{ \Illuminate\Support\Str::limit((string) $value, $short, '') }}</span><span x-show="done" x-cloak class="text-brand-700">copied</span></button>
