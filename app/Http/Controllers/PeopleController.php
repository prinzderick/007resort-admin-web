<?php

namespace App\Http\Controllers;

use App\Support\Fetch;

/** People: the roles and permissions catalogue (who may do what). Read-only: roles are defined by the API. */
class PeopleController extends Controller
{
    public function roles()
    {
        $roles = $this->all('roles', [], ['GET', '/roles']);
        $catalog = $this->all('permissions', [], ['GET', '/permissions']);
        $desc = [];
        foreach ($catalog->items() as $p) {
            $desc[$p['code'] ?? ''] = $p['description'] ?? '';
        }
        // Group the permission catalogue by its prefix (order.*, inventory.*, ...) for the matrix.
        $groups = [];
        foreach ($catalog->items() as $p) {
            $code = (string) ($p['code'] ?? '');
            $groups[explode('.', $code)[0]][] = $code;
        }
        ksort($groups);

        return view('pages.people.roles', ['roles' => $roles, 'catalog' => $catalog, 'desc' => $desc, 'groups' => $groups]);
    }
}
