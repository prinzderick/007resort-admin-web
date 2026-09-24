<?php

namespace App\Http\Controllers;

use App\Services\Portal\DashboardData;
use App\Support\Contract;
use App\Support\Fetch;
use App\Support\Navigation;
use Illuminate\Http\Request;

/**
 * Global search. Uses the API's GET /admin/search when the contract has it; until then it searches what the
 * signed-in account can already read (pages, facilities, staff, roles, devices, suppliers) so the box is never dead.
 */
class SearchController extends Controller
{
    public function index(Request $request, DashboardData $dash)
    {
        $q = trim((string) $request->query('q', ''));
        $groups = [];

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $match = fn (?string $s) => $s !== null && str_contains(mb_strtolower($s), $needle);

            // 1. Pages
            $pages = array_values(array_filter(Navigation::enabled($this->staff), fn ($i) => $match($i['label']) || $match($i['group'])));
            $pages && $groups['Pages'] = array_map(fn ($i) => ['title' => $i['label'], 'sub' => $i['group'], 'url' => route($i['route'])], $pages);

            // 2. The API's own search, when it exists
            if (Contract::has('GET', '/admin/search')) {
                $res = Fetch::of(fn () => $this->api->get('admin/search', ['q' => $q, 'limit' => 20]), ['GET', '/admin/search']);
                foreach ($res->items() as $hit) {
                    $groups[ucfirst((string) ($hit['type'] ?? 'Results'))][] = ['title' => $hit['title'] ?? ($hit['name'] ?? ''), 'sub' => $hit['subtitle'] ?? '', 'url' => $hit['url'] ?? ($hit['href'] ?? '#')];
                }
            } else {
                // 3. Fallback: what this account may already read
                $tree = Fetch::of(fn () => $this->api->get('organization/facilities'), ['GET', '/organization/facilities']);
                $fac = array_values(array_filter($dash->flatten($tree->items()), fn ($f) => $match($f['name'] ?? null) || $match($f['code'] ?? null)));
                $fac && $groups['Facilities'] = array_map(fn ($f) => ['title' => $f['name'] ?? '', 'sub' => ($f['kind'] ?? '').' - '.($f['code'] ?? ''), 'url' => route('setup.facilities.show', $f['id'] ?? '')], array_slice($fac, 0, 8));

                if ($this->staff->can('staff.manage')) {
                    $s = Fetch::of(fn () => $this->api->get('staff', ['q' => $q, 'limit' => 8]), ['GET', '/staff']);
                    $s->items() && $groups['Staff'] = array_map(fn ($m) => ['title' => $m['displayName'] ?? '', 'sub' => ($m['staffNumber'] ?? '').' - '.($m['status'] ?? ''), 'url' => route('staff.show', $m['id'] ?? '')], $s->items());
                }
                if ($this->staff->can('role_assignment.manage')) {
                    $r = $this->all('roles', [], ['GET', '/roles']);
                    $hits = array_values(array_filter($r->items(), fn ($x) => $match($x['name'] ?? null) || $match($x['code'] ?? null)));
                    $hits && $groups['Roles'] = array_map(fn ($x) => ['title' => $x['name'] ?? '', 'sub' => count($x['permissions'] ?? []).' permissions', 'url' => route('people.roles')], $hits);
                }
                if ($this->staff->canAny('device.register', 'device.view', 'device.revoke')) {
                    $d = $this->all('devices', [], ['GET', '/devices'], 2);
                    $hits = array_values(array_filter($d->items(), fn ($x) => $match($x['name'] ?? null)));
                    $hits && $groups['Devices'] = array_map(fn ($x) => ['title' => $x['name'] ?? '', 'sub' => ($x['kind'] ?? '').' - '.($x['status'] ?? ''), 'url' => route('devices.index')], array_slice($hits, 0, 8));
                }
                if ($this->staff->can('order.view') && preg_match('/^[A-Za-z]+-\d+$/', $q)) {
                    $groups['Orders'] = [['title' => 'Find order '.strtoupper($q), 'sub' => 'Orders list', 'url' => route('orders.index')]];
                }
            }
        }

        return view('pages.search', ['q' => $q, 'groups' => $groups]);
    }
}
