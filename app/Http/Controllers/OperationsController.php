<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Services\Portal\Directory;
use App\Support\Csv;
use App\Support\DateRange;
use App\Support\Fetch;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Operations: what is happening on the floor right now. These are read-only views over data the tablets, POS and
 * KDS write through the API; anything that changes an order/booking/ticket is done at the point of sale.
 */
class OperationsController extends Controller
{
    public function orders(Request $request, DashboardData $dash, Directory $dir)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $facilityId = $request->query('facility');
        $status = $request->query('status');
        $orders = Fetch::of(fn () => $this->api->get('orders', ['limit' => 100, 'cursor' => $request->query('cursor'), 'filter[facilityId]' => $facilityId, 'filter[status]' => $status]), ['GET', '/orders']);
        $names = $this->facilityNames($dash, $tree);

        if ($request->query('format') === 'csv') {
            return Csv::stream('orders-'.now()->format('Ymd').'.csv', ['Number', 'Facility', 'Table', 'Status', 'Lines', 'Total', 'Balance due', 'Created'],
                array_map(fn ($o) => [$o['number'] ?? '', $names[$o['facilityId'] ?? ''] ?? '', $o['tableLabel'] ?? '', $o['status'] ?? '', $o['lineCount'] ?? 0, $o['total'] ?? '0', $o['balanceDue'] ?? '0', $o['createdAt'] ?? ''], $orders->items()));
        }

        return view('pages.operations.orders', ['orders' => $orders, 'facilities' => $dash->flatten($tree->items()), 'facilityNames' => $names, 'facilityId' => $facilityId, 'status' => $status]);
    }

    public function order(string $order, DashboardData $dash, Directory $dir)
    {
        $o = Fetch::of(fn () => $this->api->get("orders/{$order}"), ['GET', '/orders/{orderId}']);
        $facilityId = $o->ok() ? ($o->data['facilityId'] ?? null) : null;
        $payments = $facilityId && $this->staff->can('payment.view')
            ? Fetch::of(fn () => $this->api->get('payments', ['filter[orderId]' => $order, 'filter[facilityId]' => $facilityId, 'limit' => 50]), ['GET', '/payments'])
            : new Fetch(null, 'forbidden');
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);

        return view('pages.operations.order', ['order' => $o, 'id' => $order, 'payments' => $payments, 'facilityNames' => $this->facilityNames($dash, $tree), 'staffNames' => $dir->staffNames()]);
    }

    /** Tables of one facility with their live status (GET /tables needs a facility). */
    public function tables(Request $request, DashboardData $dash, Directory $dir)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $flat = $dash->flatten($tree->items());
        $withTables = array_values(array_filter($flat, fn ($f) => in_array('TABLE_SERVICE', (array) ($f['capabilities'] ?? []), true)));
        $facilityId = $request->query('facility') ?: (($withTables[0] ?? $flat[0] ?? [])['id'] ?? null);
        $tables = $facilityId ? $this->all('tables', ['facilityId' => $facilityId], ['GET', '/tables'], 2) : new Fetch(null, 'pending');

        return view('pages.operations.tables', ['tables' => $tables, 'facilities' => $withTables ?: $flat, 'facilityId' => $facilityId, 'staffNames' => $dir->staffNames()]);
    }

    public function bookings(Request $request, DashboardData $dash)
    {
        $range = DateRange::fromRequest($request, '30d');
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $facilityId = $request->query('facility');
        $status = $request->query('status');
        // Bookings are filtered by start time; the picker covers the past AND the coming month so upcoming ones show.
        $from = CarbonImmutable::parse($range->from, 'Africa/Lagos')->startOfDay()->utc();
        $to = CarbonImmutable::parse($range->to, 'Africa/Lagos')->addDays(31)->startOfDay()->utc();
        $q = ['limit' => 100, 'cursor' => $request->query('cursor'), 'q' => $request->query('q'), 'filter[facilityId]' => $facilityId, 'filter[status]' => $status,
            'filter[from]' => $from->toIso8601ZuluString(), 'filter[to]' => $to->toIso8601ZuluString()];
        $bookings = Fetch::of(fn () => $this->api->get('bookings', $q), ['GET', '/bookings']);
        $names = $this->facilityNames($dash, $tree);

        if ($request->query('format') === 'csv') {
            return Csv::stream('bookings.csv', ['Number', 'Resource', 'Facility', 'Start', 'End', 'Status', 'Customer', 'Total', 'Paid'],
                array_map(fn ($b) => [$b['number'] ?? '', $b['resourceName'] ?? '', $names[$b['facilityId'] ?? ''] ?? '', $b['start'] ?? '', $b['end'] ?? '', $b['status'] ?? '', $b['customer']['name'] ?? '', $b['total'] ?? '0', $b['amountPaid'] ?? '0'], $bookings->items()));
        }

        return view('pages.operations.bookings', ['bookings' => $bookings, 'facilities' => $dash->flatten($tree->items()), 'facilityNames' => $names, 'facilityId' => $facilityId, 'status' => $status, 'q' => $request->query('q'), 'range' => $range]);
    }

    /** Issued tickets and rentals. The QR token is a credential and is never shown here. */
    public function tickets(Request $request, DashboardData $dash)
    {
        $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
        $entitlements = Fetch::of(fn () => $this->api->get('entitlements', ['limit' => 100, 'cursor' => $request->query('cursor')]), ['GET', '/entitlements']);
        $items = array_map(function ($e) {
            unset($e['qrToken']);

            return $e;
        }, $entitlements->items());
        $status = $request->query('status');
        $items = $status ? array_values(array_filter($items, fn ($e) => ($e['status'] ?? '') === $status)) : $items;

        return view('pages.operations.tickets', ['entitlements' => $entitlements, 'items' => $items, 'facilityNames' => $this->facilityNames($dash, $tree), 'status' => $status]);
    }

    public function memberships(Request $request)
    {
        $status = $request->query('status');
        $q = $request->query('q');
        $members = Fetch::of(fn () => $this->api->get('memberships', ['limit' => 100, 'cursor' => $request->query('cursor'), 'q' => $q, 'filter[status]' => $status]), ['GET', '/memberships']);
        $range = DateRange::fromRequest($request, '30d');
        $summary = $this->staff->canAny('report.view', 'report.view.all')
            ? Fetch::of(fn () => $this->api->get('reports/membership-summary', ['from' => $range->from, 'to' => $range->to]), ['GET', '/reports/membership-summary'])
            : new Fetch(null, 'forbidden');
        $plans = Fetch::of(fn () => $this->api->get('memberships/plans', ['limit' => 100]), ['GET', '/memberships/plans']);

        return view('pages.operations.memberships', ['members' => $members, 'summary' => $summary, 'plans' => $plans, 'status' => $status, 'q' => $q, 'range' => $range]);
    }

    /**
     * @return array<string, string> facility id => name
     */
    private function facilityNames(DashboardData $dash, Fetch $tree): array
    {
        $names = [];
        foreach ($dash->flatten($tree->items()) as $f) {
            $names[$f['id'] ?? ''] = $f['name'] ?? '';
        }

        return $names;
    }
}
