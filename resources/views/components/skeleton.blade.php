@props(['rows' => 5, 'cols' => 4, 'lines' => null])
{{-- Placeholder while a block loads. rows/cols = a table; lines = text lines. --}}
@if ($lines)
    <div class="space-y-2" aria-hidden="true" data-component="skeleton">@for ($i = 0; $i < $lines; $i++)<div class="skeleton h-3" style="width: {{ 100 - ($i % 3) * 18 }}%"></div>@endfor</div>
@else
    <div class="divide-y divide-stone-100" aria-hidden="true" data-component="skeleton">@for ($r = 0; $r < $rows; $r++)<div class="flex gap-4 px-4 py-4">@for ($c = 0; $c < $cols; $c++)<div class="skeleton h-3 flex-1"></div>@endfor</div>@endfor</div>
@endif
