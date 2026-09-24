<?php

namespace App\Http\Controllers;

use App\Support\Fetch;
use App\Support\Navigation;
use Illuminate\Http\Request;

/**
 * Global search: the portal's own pages (by name) plus GET /admin/search, which finds staff, products, facilities, orders, receipts and
 * customers and only returns the kinds this account may read. Every hit links to the screen where it can be opened or edited.
 */
class SearchController extends Controller
{
    private const LABELS = ['facility' => 'Facilities', 'staff' => 'Staff', 'product' => 'Products', 'order' => 'Orders', 'receipt' => 'Receipts', 'customer' => 'Customers'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $groups = [];

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $pages = array_values(array_filter(Navigation::enabled($this->staff), fn ($i) => str_contains(mb_strtolower($i['label']), $needle) || str_contains(mb_strtolower($i['group']), $needle)));
            $pages && $groups['Pages'] = array_map(fn ($i) => ['title' => $i['label'], 'sub' => $i['group'], 'url' => route($i['route'])], $pages);

            if (mb_strlen($q) >= 2) {
                $res = Fetch::of(fn () => $this->api->get('admin/search', ['q' => $q, 'limit' => 8]), ['GET', '/admin/search']);
                foreach ((array) ($res->ok() ? ($res->data['results'] ?? []) : []) as $type => $hits) {
                    foreach ((array) $hits as $h) {
                        $groups[self::LABELS[$type] ?? ucfirst((string) $type)][] = ['title' => $h['title'] ?? '', 'sub' => $h['subtitle'] ?? '', 'url' => $this->url((string) $type, $h)];
                    }
                }
            }
        }

        return view('pages.search', ['q' => $q, 'groups' => $groups]);
    }

    /** @param  array<string, mixed>  $h */
    private function url(string $type, array $h): string
    {
        $id = (string) ($h['id'] ?? '');

        return match ($type) {
            'facility' => route('setup.facilities.show', $id),
            'staff' => route('staff.show', $id),
            'product' => route('setup.catalog.product', $id),
            'order' => route('orders.show', $id),
            'receipt' => route('finance.payments', ['filter' => ['q' => $h['title'] ?? '']]),
            'customer' => route('memberships.index', ['q' => $h['title'] ?? '']),
            default => '#',
        };
    }
}
