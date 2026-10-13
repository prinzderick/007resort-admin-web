<x-layouts.app title="Receipt print queue">
    <x-page-header title="Receipt print queue" subtitle="Receipts a tablet asked to be printed. Open one to print it from this computer, or print it from the POS terminal at the counter." />
    <x-filter-form :reset="route('print-queue')">
        <x-filter-select name="facilityId" label="Facility" :options="collect($facilities)->pluck('name', 'id')->all()" :value="$facilityId" all="" />
        <x-filter-select name="status" label="Status" :options="['QUEUED' => 'Waiting to print', 'PRINTED' => 'Printed', 'FAILED' => 'Failed', 'CANCELLED' => 'Cancelled']" :value="$status" all="" />
    </x-filter-form>
    <x-card flush>
        @if ($jobs === null)
            <x-empty title="No facilities" text="Nothing to show." icon="receipt" />
        @else
            <x-fetch :of="$jobs" what="The print queue" />
            @if ($jobs->ok())
                <div class="table-scroll"><table class="data-table" data-testid="print-queue-table">
                    <thead><tr><th>Requested</th><th>Receipt</th><th>By</th><th>Copies</th><th>Status</th><th class="w-40"></th></tr></thead>
                    <tbody>
                    @forelse ($jobs->items() as $j)
                        <tr>
                            <td class="whitespace-nowrap"><x-time :at="$j['createdAt'] ?? null" ago /></td>
                            <td class="font-medium">{{ $j['receiptNumber'] ?? '' }}</td>
                            <td>{{ $j['requestedBy'] ?? '' }}</td>
                            <td class="num">{{ $j['copies'] ?? 1 }}</td>
                            <td><x-badge :status="$j['status'] ?? ''" /></td>
                            <td class="text-right">@if (($j['status'] ?? '') === 'QUEUED')<x-btn :href="route('print-job', $j['id'])" icon="receipt">Print</x-btn>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="Nothing waiting" text="When a waiter taps Print receipt on the tablet it shows up here." icon="receipt" /></td></tr>
                    @endforelse
                    </tbody></table></div>
            @endif
        @endif
    </x-card>
</x-layouts.app>
