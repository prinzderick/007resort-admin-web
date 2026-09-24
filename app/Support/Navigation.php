<?php

namespace App\Support;

use App\Auth\StaffSession;

/**
 * Permission-driven navigation. Entries are visible when the staff member (as
 * reported by /auth/me) holds ANY of the listed permissions; an empty list
 * means "any signed-in staff". Permission codes mirror the contract's
 * x-permission values for the endpoints each section reads.
 */
final class Navigation
{
    /**
     * @return list<array{label: string, route: string, match: string, permissions: list<string>, approvals?: bool, alt?: array<string, string>}>
     */
    public static function all(): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'match' => 'dashboard', 'permissions' => []],
            ['label' => 'Approvals', 'route' => 'approvals', 'match' => 'approvals', 'permissions' => [], 'approvals' => true],
            ['label' => 'Reports', 'route' => 'reports.index', 'match' => 'reports.*', 'permissions' => ['report.view', 'report.view.all', 'finance.report.view']],
            ['label' => 'Finance', 'route' => 'finance.payments', 'match' => 'finance.*', 'permissions' => ['payment.view', 'settlement.reconcile', 'finance.report.view']],
            ['label' => 'Inventory', 'route' => 'inventory.index', 'match' => 'inventory.*', 'permissions' => ['inventory.view', 'inventory.receive', 'inventory.purchase_receipt.create', 'inventory.transfer.create', 'inventory.count.create', 'inventory.adjustment.request']],
            ['label' => 'Staff', 'route' => 'staff.index', 'alt' => ['staff.attendance' => 'attendance.view', 'staff.audit' => 'audit.view'], 'match' => 'staff.*', 'permissions' => ['staff.manage', 'role_assignment.manage', 'attendance.view', 'audit.view']],
            ['label' => 'Configuration', 'route' => 'config.index', 'match' => 'config.*', 'permissions' => ['config.manage', 'facility.configure', 'pricing.manage', 'membership.plan.manage', 'catalog.availability.manage', 'catalog.manage', 'booking.configure']],
            ['label' => 'Devices', 'route' => 'devices.index', 'match' => 'devices.*', 'permissions' => ['device.register', 'device.revoke', 'attendance.device.manage']],
            ['label' => 'Sync & IT', 'route' => 'sync', 'match' => 'sync', 'permissions' => ['config.manage']],
        ];
    }

    /** @return list<array{label: string, route: string, match: string, permissions: list<string>, alt?: array<string, string>}> */
    public static function for(StaffSession $staff): array
    {
        $visible = array_values(array_filter(self::all(), function (array $item) use ($staff): bool {
            if (($item['approvals'] ?? false) === true) {
                return $staff->canApproveAnything();
            }

            return $item['permissions'] === [] || $staff->canAny(...$item['permissions']);
        }));

        // If the landing page of a section is not permitted, link to the first one that is.
        return array_map(function (array $item) use ($staff): array {
            if (isset($item['alt']) && ! $staff->can('staff.manage')) {
                foreach ($item['alt'] as $route => $perm) {
                    if ($staff->can($perm)) {
                        $item['route'] = $route;
                        break;
                    }
                }
            }

            return $item;
        }, $visible);
    }
}
