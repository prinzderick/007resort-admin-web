<?php

namespace App\Http\Controllers;

use App\Support\Fetch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * People > Roles & permissions (docs/CONFIG_ADMIN_API.md section 11). Pick a role, tick what it may do, save. A role can never be
 * given more than the person editing it holds, and OWNER cannot be changed: the API enforces both and says why.
 */
class PeopleController extends Controller
{
    public function roles(Request $request)
    {
        $roles = $this->all('roles', [], ['GET', '/roles']);
        $list = collect($roles->items());
        $tab = $request->query('tab') === 'matrix' ? 'matrix' : 'edit';
        $selected = (string) ($request->query('role') ?: ($list->firstWhere('code', 'MANAGER')['id'] ?? $list->first()['id'] ?? ''));
        $data = ['roles' => $roles, 'tab' => $tab, 'selected' => $selected, 'canEdit' => $this->staff->can('role.manage'), 'canView' => $this->staff->canAny('role.manage', 'role_assignment.manage')];

        if ($tab === 'matrix') {
            $catalog = $this->all('permissions', [], ['GET', '/permissions']);
            $groups = [];
            foreach ($catalog->items() as $p) {
                $code = (string) ($p['code'] ?? '');
                $groups[explode('.', $code)[0]][] = $code;
            }
            ksort($groups);

            return view('pages.people.roles', $data + ['groups' => $groups]);
        }
        $detail = $selected !== '' ? Fetch::of(fn () => $this->api->request('GET', "roles/{$selected}/permissions"), ['GET', '/roles/{roleId}/permissions']) : new Fetch(null, 'pending');

        return view('pages.people.roles', $data + ['detail' => $detail->ok() ? new Fetch($detail->data->body) : $detail, 'etag' => $detail->ok() ? $detail->data->etag() : null]);
    }

    public function create(Request $request): RedirectResponse
    {
        abort_unless($this->staff->can('role.manage'), 403);
        $d = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:255'], 'copyFrom' => ['nullable', 'uuid']]);
        $permissions = [];
        if (! empty($d['copyFrom'])) {
            $src = $this->api->get("roles/{$d['copyFrom']}/permissions");
            foreach ((array) ($src['groups'] ?? []) as $g) {
                foreach ((array) ($g['items'] ?? []) as $i) {
                    ! empty($i['granted']) && $permissions[] = ['code' => $i['code'], 'requiresApproval' => (bool) ($i['requiresApproval'] ?? false)];
                }
            }
        }
        $res = $this->api->request('POST', 'roles', [], array_filter(['name' => $d['name'], 'description' => $d['description'] ?? null, 'permissions' => $permissions ?: null], fn ($v) => $v !== null));

        return redirect()->route('people.roles', ['role' => $res->body['id'] ?? ($res->body['role']['id'] ?? null)])->with('success', 'Role created. Tick what it may do and save.');
    }

    public function permissions(Request $request, string $role): RedirectResponse
    {
        abort_unless($this->staff->can('role.manage'), 403);
        $codes = array_values(array_filter((array) $request->input('permissions', []), 'is_string'));
        $approval = array_flip((array) $request->input('approval', []));
        $body = array_map(fn ($c) => ['code' => $c, 'requiresApproval' => isset($approval[$c])], $codes);
        $this->api->request('PUT', "roles/{$role}/permissions", [], ['permissions' => $body], $request->filled('etag') ? ['If-Match' => (string) $request->input('etag')] : []);

        return redirect()->route('people.roles', ['role' => $role])->with('success', 'Permissions saved. They apply on the very next action; nobody has to sign in again.');
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        abort_unless($this->staff->can('role.manage'), 403);
        $d = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:255']]);
        $this->api->request('PATCH', "roles/{$role}", [], ['name' => $d['name'], 'description' => $d['description'] ?? null]);

        return redirect()->route('people.roles', ['role' => $role])->with('success', 'Role renamed.');
    }

    public function destroy(string $role): RedirectResponse
    {
        abort_unless($this->staff->can('role.manage'), 403);
        $this->api->request('DELETE', "roles/{$role}");

        return redirect()->route('people.roles')->with('success', 'Role deleted.');
    }
}
