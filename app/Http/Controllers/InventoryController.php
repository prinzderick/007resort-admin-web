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
        $items = Fetch::of(fn () => $this->api->get('inventory/items', ['limit' => 200]), ['GET', '/inventory/items']);
        $locations = Fetch::of(fn () => $this->api->get('inventory/locations', ['limit' => 200]), ['GET', '/inventory/locations']);
        $balances = Fetch::of(fn () => $this->api->get('inventory/balances', ['limit' => 200, 'locationId' => $locationId]), ['GET', '/inventory/balances']);

        $reorder = [];
        $names = [];
        foreach ($items->items() as $i) {
            $reorder[$i['id']] = $i['reorderLevel'] ?? null;
        }
        foreach ($locations->items() as $l) {
            $names[$l['id']] = $l['name'];
        }
        $rows = array_map(function ($b) use ($reorder, $names) {
            $level = $reorder[$b['itemId']] ?? null;
            $b['location'] = $names[$b['locationId']] ?? $b['locationId'];
            $b['low'] = $level !== null && Money::cmp($b['quantity'], $level) <= 0;
            $b['reorderLevel'] = $level;

            return $b;
        }, $balances->items());

        if ($request->query('format') === 'csv') {
            return Csv::stream('stock-balances.csv', ['Item', 'Location', 'Quantity', 'Unit', 'Reorder level', 'Updated'],
                array_map(fn ($r) => [$r['itemName'] ?? $r['itemId'], $r['location'], $r['quantity'], $r['unit'] ?? '', $r['reorderLevel'] ?? '', $r['updatedAt'] ?? ''], $rows));
        }

        return view('pages.inventory.index', ['rows' => $rows, 'balances' => $balances, 'locations' => $locations, 'locationId' => $locationId, 'lowCount' => count(array_filter($rows, fn ($r) => $r['low']))]);
    }

    public function form(string $action)
    {
        [$perm, $title] = $this->action($action);
        abort_unless($this->staff->can($perm), 403);

        return view('pages.inventory.form', [
            'action' => $action, 'title' => $title,
            'items' => Fetch::of(fn () => $this->api->get('inventory/items', ['limit' => 200]), ['GET', '/inventory/items']),
            'locations' => Fetch::of(fn () => $this->api->get('inventory/locations', ['limit' => 200]), ['GET', '/inventory/locations']),
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
                $d = $request->validate(['locationId' => ['required', 'uuid'], 'supplierName' => ['nullable', 'string', 'max:190'], 'supplierInvoice' => ['nullable', 'string', 'max:190'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.itemId' => ['required', 'uuid'], 'lines.*.quantity' => $qty, 'lines.*.unitCost' => ['required', 'regex:/^\d+(\.\d{1,4})?$/']]);
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

                return $this->rememberCount($res->body, null);
            default:
                abort(404);
        }

        return $this->done($res, 'inventory.index', $ok, $pending);
    }

    public function postCount(string $count)
    {
        abort_unless($this->staff->can('inventory.count.post'), 403);
        $res = $this->api->request('POST', "inventory/counts/{$count}/post");

        return $this->rememberCount($res->body, 'Count posted: variance movements were written by the API.');
    }

    /** The contract has no GET for a count, so the sheet the API returned is kept in the server-side session. */
    public function showCount(Request $request, string $count)
    {
        $data = $request->session()->get("r007.count.{$count}");
        abort_if(! is_array($data), 404, 'This count sheet is no longer in your session. Start a new count.');

        return $this->countView($data, $request->session()->pull('count_flash'));
    }

    /** @param  array<string, mixed>  $count */
    private function rememberCount(array $count, ?string $flash): RedirectResponse
    {
        session()->put("r007.count.{$count['id']}", $count);

        return redirect()->route('inventory.count.show', $count['id'])->with('count_flash', $flash);
    }

    /** @param  array<string, mixed>  $count */
    private function countView(array $count, ?string $flash = null)
    {
        $items = Fetch::of(fn () => $this->api->get('inventory/items', ['limit' => 200]), ['GET', '/inventory/items']);
        $names = [];
        foreach ($items->items() as $i) {
            $names[$i['id']] = $i['name'].' ('.($i['unit'] ?? '').')';
        }
        $locations = Fetch::of(fn () => $this->api->get('inventory/locations', ['limit' => 200]), ['GET', '/inventory/locations']);
        $loc = collect($locations->items())->firstWhere('id', $count['locationId'] ?? '')['name'] ?? ($count['locationId'] ?? '');

        return response()->view('pages.inventory.count', ['count' => $count, 'names' => $names, 'location' => $loc, 'flash' => $flash]);
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
            $cost && $row['unitCost'] = $l['unitCost'];
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
