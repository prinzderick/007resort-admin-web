<?php

namespace Tests\Feature;

use App\Auth\StaffSession;
use App\Livewire\ApprovalsQueue;
use App\Support\Navigation;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NavigationAuthorizationTest extends TestCase
{
    /** @return list<string> */
    private function labels(array $perms): array
    {
        $this->signIn($perms);

        return array_column(Navigation::for(app(StaffSession::class)), 'label');
    }

    public function test_navigation_is_driven_by_permissions(): void
    {
        $this->assertSame(['Dashboard'], $this->labels([]));
        $this->assertSame(['Dashboard', 'Reports'], $this->labels(['report.view']));
        $this->assertSame(['Dashboard', 'Sync & IT', 'Devices', 'Configuration'], array_values(array_intersect(['Dashboard', 'Sync & IT', 'Devices', 'Configuration'], $this->labels(['config.manage', 'device.register']))));
        $this->assertContains('Approvals', $this->labels(['refund.approve']));
        $this->assertNotContains('Approvals', $this->labels(['payment.view']));
    }

    public function test_it_role_does_not_get_financial_sections(): void
    {
        $labels = $this->labels(['device.register', 'device.revoke', 'config.manage', 'audit.view']);

        $this->assertContains('Devices', $labels);
        $this->assertContains('Sync & IT', $labels);
        $this->assertNotContains('Finance', $labels);
        $this->assertNotContains('Reports', $labels);
    }

    public function test_staff_link_falls_back_to_a_permitted_subsection(): void
    {
        $this->signIn(['attendance.view']);
        $staff = collect(Navigation::for(app(StaffSession::class)))->firstWhere('label', 'Staff');

        $this->assertSame('staff.attendance', $staff['route']);
    }

    public function test_sidebar_only_renders_permitted_links(): void
    {
        $this->fakeApi(['GET /organization/facilities' => ['items' => []]]);
        $html = $this->signIn(['report.view'])->get('/reports')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('reports.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('finance.payments').'"', $html);
        $this->assertStringNotContainsString('href="'.route('sync').'"', $html);
    }

    #[DataProvider('forbiddenRoutes')]
    public function test_routes_are_forbidden_without_the_permission(string $method, string $uri): void
    {
        $this->fakeApi([]);
        $this->signIn(['order.create'])->call($method, $uri)->assertForbidden();
    }

    public static function forbiddenRoutes(): array
    {
        return [
            'reports' => ['GET', '/reports'], 'payments' => ['GET', '/finance/payments'], 'refund' => ['POST', '/finance/payments/x/refund'],
            'reversal' => ['POST', '/finance/payments/x/reversal'], 'inventory' => ['GET', '/inventory'], 'receive' => ['GET', '/inventory/receive'],
            'staff' => ['GET', '/staff'], 'attendance' => ['GET', '/staff/attendance'], 'audit' => ['GET', '/staff/audit'], 'config' => ['GET', '/config'],
            'tax' => ['GET', '/config/tax'], 'tax save' => ['PUT', '/config/tax'], 'devices' => ['GET', '/devices'], 'revoke' => ['POST', '/devices/x/revoke'], 'sync' => ['GET', '/sync'],
        ];
    }

    public function test_finance_permission_does_not_open_it_screens(): void
    {
        $this->fakeApi([]);
        $this->signIn(['payment.view', 'report.view'])->get('/devices')->assertForbidden();
        $this->get('/sync')->assertForbidden();
        $this->get('/config/tax')->assertForbidden();
    }

    public function test_inventory_actions_check_their_own_permission(): void
    {
        $this->fakeApi([]);
        $this->signIn(['inventory.view'])->get('/inventory/receive')->assertForbidden();
        $this->post('/inventory/receive', [])->assertForbidden();
    }

    public function test_livewire_pages_are_gated_too(): void
    {
        $this->fakeApi([]);
        $this->signIn(['payment.view'])->get('/sync')->assertForbidden();
        $this->get('/login')->assertRedirect('/');
    }

    public function test_approvals_component_renders_for_an_approver(): void
    {
        $this->fakeApi(['GET /approvals' => ['items' => []]]);
        $this->signIn(['refund.approve']);
        Livewire::test(ApprovalsQueue::class)->assertSee('Nothing is waiting for your decision');
    }
}
