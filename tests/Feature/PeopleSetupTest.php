<?php

namespace Tests\Feature;

use Tests\Support\RealApi;
use Tests\TestCase;

/** Roles editor, global search, the Setup hub and its progress card, and the navigation each kind of user sees. */
class PeopleSetupTest extends TestCase
{
    private function api(array $over = [], ?array $perms = null): void
    {
        $this->fakeApi($over + RealApi::routes(true));
        $this->signIn($perms ?? RealApi::ownerPermissions(), ['OWNER']);
    }

    private function role(): array
    {
        return RealApi::load('role-permissions')['role'];
    }

    public function test_the_role_editor_shows_groups_descriptions_and_what_you_cannot_grant(): void
    {
        $this->api(['GET /roles/*/permissions' => [200, RealApi::load('role-permissions'), ['ETag' => '"1"']]]);
        $this->get('/people/roles?role='.$this->role()['id'])->assertOk()->assertSee('Manager')->assertSee('Request an attendance clock correction')->assertSee('attendance.correction.request')->assertSee('Save permissions')->assertSee('New role');
        $this->get('/people/roles?tab=matrix')->assertOk()->assertSee('Permission matrix');
    }

    public function test_saving_a_role_sends_the_complete_set_with_approval_flags_and_the_version(): void
    {
        $id = $this->role()['id'];
        $this->api(['PUT /roles/*/permissions' => RealApi::load('role-permissions')]);
        $this->put("/people/roles/{$id}/permissions", ['etag' => '"1"', 'permissions' => ['order.view', 'order.void'], 'approval' => ['order.void']])->assertRedirect()->assertSessionHas('success');
        $req = $this->lastSent('PUT', "roles/{$id}/permissions");
        $this->assertSame([['code' => 'order.view', 'requiresApproval' => false], ['code' => 'order.void', 'requiresApproval' => true]], $req['permissions']);
        $this->assertSame('"1"', $req->header('If-Match')[0]);
    }

    public function test_the_owner_role_and_escalation_refusals_are_shown_in_words(): void
    {
        $id = $this->role()['id'];
        $this->api(['PUT /roles/*/permissions' => $this->problem(403, 'role_immutable', 'The Owner role always has every permission and cannot be changed.')]);
        $this->from('/people/roles')->put("/people/roles/{$id}/permissions", ['permissions' => ['x']])->assertSessionHas('error', 'The Owner role always has every permission and cannot be changed.');
    }

    public function test_custom_roles_can_be_created_from_an_existing_one_renamed_and_deleted(): void
    {
        $id = $this->role()['id'];
        $this->api(['POST /roles' => [201, ['role' => ['id' => $id]]], 'PATCH /roles/*' => ['id' => $id], 'DELETE /roles/*' => [204, []]]);
        $this->post('/people/roles', ['name' => 'Head barista', 'description' => 'Bar lead', 'copyFrom' => $id])->assertRedirect('/people/roles?role='.$id);
        $b = $this->lastSent('POST', 'roles')->data();
        $this->assertSame('Head barista', $b['name']);
        $this->assertNotEmpty($b['permissions'], 'copied from the chosen role');
        $this->assertSame('attendance.correction.request', $b['permissions'][0]['code']);
        $this->patch("/people/roles/{$id}", ['name' => 'Bar lead'])->assertRedirect();
        $this->delete("/people/roles/{$id}")->assertRedirect('/people/roles');
    }

    public function test_deleting_a_role_that_is_held_says_who_needs_it_gone_first(): void
    {
        $id = $this->role()['id'];
        $this->api(['DELETE /roles/*' => $this->problem(409, 'role_in_use', 'Two people still hold this role. Remove it from them first.')]);
        $this->from('/x')->delete("/people/roles/{$id}")->assertSessionHas('error', 'Two people still hold this role. Remove it from them first.');
    }

    public function test_role_editing_needs_role_manage_but_looking_needs_only_role_assignment_manage(): void
    {
        $id = $this->role()['id'];
        $this->api([], ['role_assignment.manage']);
        $this->get('/people/roles?role='.$id)->assertOk()->assertDontSee('New role');
        $this->put("/people/roles/{$id}/permissions", ['permissions' => []])->assertForbidden();
        $this->post('/people/roles', ['name' => 'x'])->assertForbidden();
    }

    public function test_search_finds_pages_and_the_apis_grouped_results_with_links(): void
    {
        $this->api();
        $h = $this->get('/search?q=rest')->assertOk()->assertSee('Facilities')->assertSee('Restaurant')->assertSee('Orders')->assertSee('RESTAU-000004')->getContent();
        $this->assertStringContainsString('/setup/facilities/'.RealApi::FAC, $h);
        $this->assertStringContainsString('/operations/orders/', $h);
        $this->assertTrue($this->sentTo('GET', '/admin/search', fn ($r) => str_contains($r->url(), 'q=rest')));
        $this->get('/search?q=a')->assertOk();
        $this->assertFalse($this->sentTo('GET', '/admin/search', fn ($r) => str_contains($r->url(), 'q=a&')), 'one letter is not searched');
    }

    public function test_the_setup_hub_shows_progress_from_the_api_and_a_findable_map_of_every_setting(): void
    {
        $this->api();
        $h = $this->get('/setup')->assertOk()->assertSee('Setup complete')->assertSee('100%')->assertSee('Business profile')->assertSee('Find a setting')->assertSee('Waiter collection and cash')->assertSee('Import and export')->assertSee('Roles and permissions')->assertSee('Card machines')->getContent();
        $this->assertStringContainsString('data-testid="setup-progress"', $h);
        $this->assertStringContainsString('/setup/catalog#import', $h);
    }

    public function test_incomplete_setup_says_what_is_missing_and_links_to_it(): void
    {
        $status = RealApi::load('admin-setup-status');
        $status['percent'] = 62;
        $status['complete'] = false;
        $status['steps'][3]['done'] = false;
        $status['steps'][3]['hint'] = 'Some products have no price';
        $this->api(['GET /admin/setup-status' => $status]);
        $this->get('/setup')->assertOk()->assertSee('1 step left before you are ready to trade')->assertSee('62%')->assertSee('Some products have no price');
    }

    public function test_the_hub_only_offers_what_the_account_may_open(): void
    {
        $this->api([], ['settings.manage']);
        $h = $this->get('/setup')->assertOk()->getContent();
        $this->assertStringContainsString('Receipts', $h);
        $this->assertStringNotContainsString('Waiter collection and cash', $h);
        $this->assertStringNotContainsString('Roles and permissions', $h);
    }

    public function test_sidebar_groups_the_new_screens_where_a_person_would_look(): void
    {
        $this->api();
        $h = $this->get('/')->assertOk()->getContent();
        foreach (['Collected by waiters', 'Cash handovers', 'Card machines', 'Setup home', 'Payment methods', 'Ticket types', 'Roles &amp; permissions'] as $label) {
            $this->assertStringContainsString($label, $h, "{$label} is in the sidebar");
        }
        $this->signIn(['order.view', 'order.create']);
        $h = $this->get('/')->getContent();
        $this->assertStringNotContainsString('Setup home', $h, 'a waiter never sees administrator navigation');
    }
}
