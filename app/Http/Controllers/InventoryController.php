<?php

namespace App\Http\Controllers;

use App\Support\Csv;
use App\Support\Fetch;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /** action => [permission, title] */
    public const ACTIONS = [
        'receive' => ['inventory.purchase_receipt.create', 'Receive stock'],
        'transfer' => ['inventory.transfer.create', 'Transfer stock'],
        'adjust' => ['inventory.adjustment.request', 'Adjust stock'],
        'wastage' => ['inventory.wastage.create', 'Record wastage'],
        'count' => ['inventory.count.create', 'Stock count'],
    ];

    public function index(Request $request)
    {
        $locationId = $request->query('location');
        $locations = $this->all('inventory/locations', [], ['GET', '/inventory/locations']);
        $balances = $this->all('inventory/balances', ['locationId' => $locationId], ['GET', '/inventory/balances'], 6);

        $names = [];
        foreach ($locations->items() as $l) {
            $names[$l['id'] ?? ''] = $l['name'] ?? '';
        }
        $rows = array_map(function ($b) use ($names) {
            $level = $b['reorderLevel'] ?? null;
            $b['location'] = $names[$b['locationId'] ?? ''] ?? ($b['locationId'] ?? '');
            // The API says whether a line is at/below its reorder level; fall back to comparing when it does not.
            $b['low'] = (bool) ($b['belowReorder'] ?? ($level !== null && Money::cmp($b['quantity'] ?? '0', $level) <= 0));
            $b['reorderLevel'] = $level;

            return $b;
        }, $balances->items());

        if ($request->query('format') === 'csv') {
            return Csv::stream('stock-balances.csv', ['Item', 'Location', 'Quantity', 'Unit', 'Reorder level', 'Updated'],
                array_map(fn ($r) => [$r['itemName'] ?? $r['itemId'] ?? '', $r['location'], $r['quantity'] ?? '', $r['unit'] ?? '', $r['reorderLevel'] ?? '', $r['updatedAt'] ?? ''], $rows));
        }

        return view('pages.inventory.index', ['rows' => $rows, 'balances' => $balances, 'locations' => $locations, 'locationId' => $locationId, 'lowCount' => count(array_filter($rows, fn ($r) => $r['low']))]);
    }

    /** The immutable stock ledger, newest first (GET /inventory/movements). */
    public function movements(Request $request)
    {
        $q = ['limit' => 100, 'cursor' => $request->query('cursor'), 'itemId' => $request->query('item'), 'reason' => $request->query('reason'), 'locationId' => $request->query('location')];
        $moves = Fetch::of(fn () => $this->api->get('inventory/movements', $q), ['GET', '/inventory/movements']);
        $items = $this->all('inventory/items', [], ['GET', '/inventory/items']);
        $locations = $this->all('inventory/locations', [], ['GET', '/inventory/locations']);
        $staff = app(\App\Services\Portal\Directory::class)->staffNames();

        return view('pages.inventory.movements', ['moves' => $moves, 'items' => $items, 'locations' => $locations, 'itemNames' => $this->names($items->items(), 'name'), 'locNames' => $this->names($locations->items(), 'name'), 'staffNames' => $staff, 'q' => $request->query()]);
    }

    /** Count sheets (GET /inventory/counts). */
    public function counts(Request $request)
    {
        $counts = Fetch::of(fn () => $this->api->get('inventory/counts', ['limit' => 100, 'cursor' => $request->query('cursor'), 'status' => $request->query('status')]), ['GET', '/inventory/counts']);
        $locations = $this->all('inventory/locations', [], ['GET', '/inventory/locations']);

        return view('pages.inventory.counts', ['counts' => $counts, 'locNames' => $this->names($locations->items(), 'name'), 'status' => $request->query('status')]);
    }

    /** Manual adjustments and count variances awaiting or past approval (GET /inventory/adjustments). */
    public function adjustments(Request $request)
    {
        $adj = Fetch::of(fn () => $this->api->get('inventory/adjustments', ['limit' => 100, 'cursor' => $request->query('cursor'), 'status' => $request->query('status')]), ['GET', '/inventory/adjustments']);
        $items = $this->all('inventory/items', [], ['GET', '/inventory/items']);
        $locations = $this->all('inventory/locations', [], ['GET', '/inventory/locations']);

        return view('pages.inventory.adjustments', ['adjustments' => $adj, 'itemNames' => $this->names($items->items(), 'name'), 'locNames' => $this->names($locations->items(), 'name'), 'status' => $request->query('status')]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, string>
     */
    private function names(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[$r['id'] ?? ''] = (string) ($r[$key] ?? '');
        }

        return $out;
    }

    public function form(string $action)
    {
        [$perm, $title] = $this->action($action);
        abort_unless($this->staff->can($perm), 403);

        return view('pages.inventory.form', [
            'action' => $action, 'title' => $title,
            'items' => $this->all('inventory/items', [], ['GET', '/inventory/items']),
            'locations' => $this->all('inventory/locations', [], ['GET', '/inventory/locations']),
        ]);
    }

    public function submit(Request $request, string $action): RedirectResponse
    {
        [$perm] = $this->action($action);
        abort_unless($this->staff->can($perm), 403);

        // The form always offers spare rows; drop the ones nobody filled in.
        if (is_array($request->input('lines'))) {
            $request->merge(['lines' => array_filter($request->input('lines'), fn ($l) => is_array($l) && array_filter($l, fn ($v) => trim((string) $v) !== '') !== [])]);
        }

        $qty = ['required', 'regex:/^\d+(\.\d{1,4})?$/'];
        $ok = 'Recorded.';
        $pending = 'The stock change was accepted but must be approved before balances change.';

        switch ($action) {
            case 'receive':
                $d = $request->validate(['locationId' => ['required', 'uuid'], 'supplierName' => ['nullable', 'string', 'max:190'], 'supplierInvoice' => ['nullable', 'string', 'max:190'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.itemId' => ['required', 'uuid'], 'lines.*.quantity' => $qty, 'lines.*.unitCost' => ['nullable', 'regex:/^\d+(\.\d{1,4})?$/']]);
                $res = $this->api->request('POST', 'inventory/purchase-receipts', [], $this->only($d, ['locationId', 'supplierName', 'supplierInvoice']) + ['lines' => $this->lines($d['lines'])]);
                $ok = 'Stock received.';
                break;
            case 'transfer':
                $d = $request->validate(['fromLocationId' => ['required', 'uuid', 'different:toLocationId'], 'toLocationId' => ['required', 'uuid'], 'note' => ['nullable', 'string', 'max:500'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.itemId' => ['required', 'uuid'], 'lines.*.quantity' => $qty]);
                $res = $this->api->request('POST', 'inventory/transfers', [], $this->only($d, ['fromLocationId', 'toLocationId', 'note']) + ['lines' => $this->lines($d['lines'], false)]);
                $ok = 'Stock transferred.';
                break;
            case 'adjust':
                $d = $request->validate(['locationId' => ['required', 'uuid'], 'itemId' => ['required', 'uuid'], 'quantityDelta' => ['required', 'regex:/^-?\d+(\.\d{1,4})?$/'], 'reason' => ['required', 'in:DAMAGE,THEFT,CORRECTION,EXPIRY,OTHER'], 'note' => ['required', 'string', 'max:500']]);
                $res = $this->api->request('POST', 'inventory/adjustments', [], $d);
                $ok = 'Adjustment posted.';
                break;
            case 'wastage':
                $d = $request->validate(['locationId' => ['required', 'uuid'], 'itemId' => ['required', 'uuid'], 'quantity' => $qty, 'reason' => ['required', 'in:SPOILAGE,BREAKAGE,PREP_WASTE,EXPIRY,OTHER'], 'note' => ['nullable', 'string', 'max:500']]);
                $res = $this->api->request('POST', 'inventory/wastage', [], array_filter($d, fn ($v) => $v !== null));
                $ok = 'Wastage recorded.';
                break;
            case 'count':
                $d = $request->validate(['locationId' => ['required', 'uuid'], 'note' => ['nullable', 'string', 'max:500'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.itemId' => ['required', 'uuid'], 'lines.*.countedQuantity' => $qty]);
                $res = $this->api->request('POST', 'inventory/counts', [], $this->only($d, ['locationId', 'note']) + ['lines' => array_values(array_map(fn ($l) => ['itemId' => $l['itemId'], 'countedQuantity' => $l['countedQuantity']], array_filter($d['lines'], fn ($l) => ($l['itemId'] ?? '') !== '')))]);

                return redirect()->route('inventory.count.show', $res->body['id'] ?? '')->with('count_flash', 'Count sheet created. Review the expected quantities, then post it to write the variance.');
            default:
                abort(404);
        }

        return $this->done($res, 'inventory.index', $ok, $pending);
    }

    public function postCount(string $count): RedirectResponse
    {
        abort_unless($this->staff->can('inventory.count.post'), 403);
        $res = $this->api->request('POST', "inventory/counts/{$count}/post");

        return $this->done($res, 'inventory.count.show', 'Count posted: variance movements were written by the API.', 'The count variance is above the threshold and waits for approval before balances change.', ['count' => $count]);
    }

    public function showCount(Request $request, string $count)
    {
        $doc = Fetch::of(fn () => $this->api->get("inventory/counts/{$count}"), ['GET', '/inventory/counts/{count}']);
        $items = $this->all('inventory/items', [], ['GET', '/inventory/items']);
        $locations = $this->all('inventory/locations', [], ['GET', '/inventory/locations']);
        $itemNames = [];
        foreach ($items->items() as $i) {
            $itemNames[$i['id'] ?? ''] = ($i['name'] ?? '').' ('.($i['unit'] ?? '').')';
        }

        return view('pages.inventory.count', ['doc' => $doc, 'id' => $count, 'names' => $itemNames, 'locNames' => $this->names($locations->items(), 'name'), 'flash' => $request->session()->get('count_flash')]);
    }

    /**
     * @param  array<int, array<string, string>>  $lines
     * @return list<array<string, string>>
     */
    private function lines(array $lines, bool $cost = true): array
    {
        $out = [];
        foreach ($lines as $l) {
            if (($l['itemId'] ?? '') === '') {
                continue;
            }
            $row = ['itemId' => $l['itemId'], 'quantity' => $l['quantity']];
            $cost && ($l['unitCost'] ?? '') !== '' && $row['unitCost'] = $l['unitCost'];
            $out[] = $row;
        }

        return $out;
    }

    /** @return array{0: string, 1: string} */
    private function action(string $action): array
    {
        return self::ACTIONS[$action] ?? abort(404);
    }
}
