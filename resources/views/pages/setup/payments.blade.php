@php $timing = ['PAY_AFTER_SERVICE' => 'After service', 'PAY_FIRST' => 'Pay first', 'OPEN_TAB' => 'Open tab', 'PAY_BEFORE_LEAVING' => 'Before leaving', 'PAY_ON_EXIT' => 'On exit', 'PAY_AT_RECEPTION' => 'At reception']; @endphp
<x-layouts.app title="Payment methods">
    <x-page-header title="Payment methods" subtitle="Which ways of paying each facility accepts. A method that is switched off is refused at the till, on tablets and online. At least one must stay on." :crumbs="['Setup' => route('setup.index'), 'Payment methods' => null]" />
    <x-fetch :of="$tree" what="Facilities" />
    <form method="POST" action="{{ route('setup.payments.save') }}" novalidate data-testid="methods-form">@csrf @method('PUT')
        <x-card flush>
            <div class="table-scroll"><table class="data-table" data-testid="rules-table"><thead><tr><th>Facility</th>@foreach ($labels as $code => $label)<th class="text-center">{{ $label }}</th>@endforeach<th>Payment timing</th></tr></thead><tbody>
            @forelse ($rows as $r)
                @php $fid = $r['facility']['id']; @endphp
                <tr>
                    <td class="font-medium"><a class="underline decoration-stone-300 underline-offset-2 hover:decoration-brand-600" href="{{ route('setup.facilities.show', ['facility' => $fid, 'tab' => 'rules']) }}">{{ $r['facility']['name'] ?? '' }}</a><input type="hidden" name="version[{{ $fid }}]" value="{{ $r['version'] }}">@foreach ($labels as $code => $label)<input type="hidden" name="before[{{ $fid }}][{{ $code }}]" value="{{ ($r['methods'][$code] ?? true) ? 1 : 0 }}">@endforeach</td>
                    @foreach ($labels as $code => $label)<td class="text-center"><div class="inline-block" data-method="{{ $code }}"><x-form.toggle :name="'methods['.$fid.']['.$code.']'" :bare="true" :label="$label.' at '.($r['facility']['name'] ?? '')" :value="$r['methods'][$code] ?? true" :show-state="false" :hide-label="true" :disabled="! $canEdit" /></div></td>@endforeach
                    <td class="text-sm text-stone-600">{{ isset($r['rules']['paymentTiming']) ? ($timing[$r['rules']['paymentTiming']] ?? str_replace('_', ' ', $r['rules']['paymentTiming'])) : '-' }}</td>
                </tr>
            @empty<tr><td colspan="{{ count($labels) + 2 }}"><x-empty title="No facility takes payments" text="Switch on Payment acceptance for a facility first." icon="card" /></td></tr>@endforelse
            </tbody></table></div>
        </x-card>
        @if ($canEdit && $rows !== [])<x-form.actions submit="Save payment methods" />@endif
    </form>
    <p class="mt-4 max-w-3xl text-xs text-stone-500">When payments are taken (after service, on a tab, before leaving) and the approval and cash rules are set per facility under Facilities, Operating rules.</p>
</x-layouts.app>
