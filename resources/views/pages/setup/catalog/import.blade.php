@php
    $kinds2 = ['products' => ['Products', 'sku, name, category, kind, description, barcode, taxRateCode, prepRoute, trackStock, imageUrl, active, price', 'catalog.manage'], 'prices' => ['Prices', 'sku, facilityCode, priceList, amount, validFrom, validTo', 'pricing.manage']];
@endphp
<div class="grid gap-5 xl:grid-cols-2" data-testid="import">
    @foreach ($kinds2 as $kind => [$label, $cols, $perm])
        @continue(! auth_staff()->can($perm))
        @php $rep = request('done') === $kind ? session("import.{$kind}") : null; $r = $rep['report'] ?? null; @endphp
        <x-card :title="$label" :subtitle="'Columns: '.$cols" data-import="{{ $kind }}">
            <div class="mb-4 flex flex-wrap gap-2"><x-btn variant="secondary" icon="download" :href="route('setup.catalog.export', $kind)">Download current {{ strtolower($label) }} (CSV)</x-btn></div>
            @if ($r)
                <div class="mb-4 rounded-xl border p-4 {{ ! empty($r['errors']) ? 'border-red-300 bg-red-50' : (($rep['applied'] ?? false) ? 'border-brand-300 bg-brand-50' : 'border-sky-300 bg-sky-50') }}" data-testid="import-report" data-dry="{{ ($r['dryRun'] ?? true) ? '1' : '0' }}">
                    <div class="mb-2 text-sm font-semibold">{{ ($rep['applied'] ?? false) ? 'Import applied' : (! empty($r['errors']) ? 'Fix these rows, then check again' : 'Check passed: nothing has been changed yet') }}</div>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-5"><div><dt class="text-xs text-stone-600">Rows</dt><dd class="font-semibold tabular-nums">{{ $r['totalRows'] ?? 0 }}</dd></div><div><dt class="text-xs text-stone-600">Valid</dt><dd class="font-semibold tabular-nums">{{ $r['valid'] ?? 0 }}</dd></div><div><dt class="text-xs text-stone-600">Will create</dt><dd class="font-semibold tabular-nums">{{ $r['willCreate'] ?? 0 }}</dd></div><div><dt class="text-xs text-stone-600">Will update</dt><dd class="font-semibold tabular-nums">{{ $r['willUpdate'] ?? 0 }}</dd></div><div><dt class="text-xs text-stone-600">Unchanged</dt><dd class="font-semibold tabular-nums">{{ $r['unchanged'] ?? 0 }}</dd></div></dl>
                    @if (! empty($r['createdCategories']))<p class="mt-2 text-sm">New categories: {{ implode(', ', $r['createdCategories']) }}</p>@endif
                    @if (! empty($r['errors']))
                        <div class="mt-3 max-h-56 overflow-auto rounded-lg bg-white"><table class="data-table"><thead><tr><th>Row</th><th>Column</th><th>Problem</th></tr></thead><tbody>@foreach ($r['errors'] as $e)<tr><td class="num">{{ $e['row'] ?? '' }}</td><td>{{ $e['field'] ?? '' }}</td><td>{{ $e['message'] ?? '' }}</td></tr>@endforeach</tbody></table></div>
                    @endif
                    @if (empty($r['errors']) && ! ($rep['applied'] ?? false) && ! empty($rep['csv']))
                        <form method="POST" action="{{ route('setup.catalog.import', $kind) }}" class="mt-3">@csrf<input type="hidden" name="mode" value="apply"><x-btn>Apply this import ({{ ($r['willCreate'] ?? 0) + ($r['willUpdate'] ?? 0) }} change{{ (($r['willCreate'] ?? 0) + ($r['willUpdate'] ?? 0)) === 1 ? '' : 's' }})</x-btn></form>
                    @endif
                </div>
            @endif
            <form method="POST" action="{{ route('setup.catalog.import', $kind) }}" enctype="multipart/form-data" class="grid gap-4" novalidate>@csrf
                <x-form.file name="file" label="CSV file" accept=".csv,text/csv" :max-size="2" hint="Up to 2 MB and 5,000 rows. The first step only checks the file: nothing is saved until you press Apply." />
                <div><x-btn variant="secondary">Check the file</x-btn></div>
            </form>
        </x-card>
    @endforeach
</div>
