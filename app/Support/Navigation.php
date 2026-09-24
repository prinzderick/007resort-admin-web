<?php

namespace App\Support;

use App\Auth\StaffSession;

/**
 * The sidebar. Every entry is one obvious click, grouped the way an owner thinks about the business.
 *
 * Visibility rules (permissions come from /auth/me and only drive what the UI OFFERS; the API still authorises every call):
 *  - a permitted entry is a normal link;
 *  - a NOT permitted entry in an operational group is shown greyed out with a tooltip naming the missing permission
 *    (so people learn who to ask), except that
 *  - `admin` entries (Setup, System) are hidden entirely from people who hold none of the group's permissions, so a
 *    cashier never sees administrator navigation.
 */
final class Navigation
{
    /**
     * @return list<array{title: string, admin?: bool, items: list<array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            ['title' => 'Overview', 'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'grid', 'permissions' => []],
            ]],
            ['title' => 'Operations', 'items' => [
                ['label' => 'Orders', 'route' => 'orders.index', 'match' => 'orders.*', 'icon' => 'cart', 'permissions' => ['order.view']],
                ['label' => 'Tables', 'route' => 'tables.index', 'match' => 'tables.*', 'icon' => 'table', 'permissions' => ['order.view', 'table.manage']],
                ['label' => 'Bookings', 'route' => 'bookings.index', 'match' => 'bookings.*', 'icon' => 'calendar', 'permissions' => ['booking.view']],
                ['label' => 'Tickets & entry', 'route' => 'tickets.index', 'match' => 'tickets.*', 'icon' => 'ticket', 'permissions' => ['ticket.view']],
                ['label' => 'Memberships', 'route' => 'memberships.index', 'match' => 'memberships.*', 'icon' => 'users', 'permissions' => ['membership.view']],
            ]],
            ['title' => 'Finance', 'items' => [
                ['label' => 'Payments', 'route' => 'finance.payments', 'match' => 'finance.payment*', 'icon' => 'card', 'permissions' => ['payment.view', 'settlement.reconcile', 'finance.report.view']],
                ['label' => 'Refunds & reversals', 'route' => 'finance.refunds', 'match' => 'finance.refunds', 'icon' => 'undo', 'permissions' => ['payment.view', 'refund.execute', 'refund.approve', 'payment.reversal.execute']],
                ['label' => 'Cash sessions', 'route' => 'finance.cash-sessions', 'match' => 'finance.cash-sessions', 'icon' => 'cash', 'permissions' => ['cash_session.view']],
                ['label' => 'Settlements', 'route' => 'finance.reconciliation', 'match' => 'finance.reconciliation', 'icon' => 'briefcase', 'permissions' => ['settlement.reconcile', 'finance.report.view', 'payment.view']],
                ['label' => 'Reports', 'route' => 'reports.index', 'match' => 'reports.*', 'icon' => 'chart', 'permissions' => ['report.view', 'report.view.all', 'finance.report.view']],
            ]],
            ['title' => 'Inventory', 'items' => [
                ['label' => 'Stock', 'route' => 'inventory.index', 'match' => 'inventory.index', 'icon' => 'box', 'permissions' => ['inventory.view', 'inventory.receive', 'inventory.purchase_receipt.create', 'inventory.adjustment.request']],
                ['label' => 'Transfers', 'route' => 'inventory.transfers', 'match' => 'inventory.transfers', 'icon' => 'repeat', 'permissions' => ['inventory.view', 'inventory.transfer.create']],
                ['label' => 'Counts', 'route' => 'inventory.counts', 'match' => 'inventory.count*', 'icon' => 'clipboard', 'permissions' => ['inventory.view', 'inventory.count.create']],
                ['label' => 'Suppliers', 'route' => 'inventory.suppliers', 'match' => 'inventory.suppliers', 'icon' => 'truck', 'permissions' => ['supplier.manage', 'inventory.item.manage']],
            ]],
            ['title' => 'People', 'items' => [
                ['label' => 'Staff', 'route' => 'staff.index', 'match' => 'staff.index|staff.show', 'icon' => 'user', 'permissions' => ['staff.manage']],
                ['label' => 'Roles & permissions', 'route' => 'people.roles', 'match' => 'people.roles', 'icon' => 'shield', 'permissions' => ['role_assignment.manage']],
                ['label' => 'Attendance', 'route' => 'staff.attendance', 'match' => 'staff.attendance', 'icon' => 'clock', 'permissions' => ['attendance.view']],
                ['label' => 'Devices', 'route' => 'devices.index', 'match' => 'devices.*', 'icon' => 'device', 'permissions' => ['device.register', 'device.revoke', 'device.view', 'attendance.device.manage']],
            ]],
            ['title' => 'Setup', 'admin' => true, 'items' => [
                ['label' => 'Facilities', 'route' => 'setup.facilities', 'match' => 'setup.facilities*', 'icon' => 'building', 'permissions' => ['facility.configure', 'config.manage', 'booking.configure']],
                ['label' => 'Catalog & prices', 'route' => 'setup.catalog', 'match' => 'setup.catalog*|setup.tax', 'icon' => 'tag', 'permissions' => ['catalog.manage', 'pricing.manage', 'catalog.availability.manage', 'config.manage']],
                ['label' => 'Booking resources', 'route' => 'setup.bookings', 'match' => 'setup.bookings*', 'icon' => 'layers', 'permissions' => ['booking.configure', 'config.manage']],
                ['label' => 'Ticket types', 'route' => 'setup.tickets', 'match' => 'setup.tickets*', 'icon' => 'ticket', 'permissions' => ['facility.configure', 'config.manage']],
                ['label' => 'Membership plans', 'route' => 'setup.memberships', 'match' => 'setup.memberships*', 'icon' => 'users', 'permissions' => ['membership.plan.manage', 'config.manage']],
                ['label' => 'Kitchen & bar routing', 'route' => 'setup.kds', 'match' => 'setup.kds*', 'icon' => 'flame', 'permissions' => ['facility.configure', 'config.manage', 'catalog.manage']],
                ['label' => 'Payment rules', 'route' => 'setup.payments', 'match' => 'setup.payments*', 'icon' => 'sliders', 'permissions' => ['facility.configure', 'config.manage']],
                ['label' => 'Business & receipts', 'route' => 'setup.business', 'match' => 'setup.business*', 'icon' => 'file', 'permissions' => ['config.manage']],
            ]],
            ['title' => 'System', 'admin' => true, 'items' => [
                ['label' => 'Sync & IT', 'route' => 'sync', 'match' => 'sync', 'icon' => 'sync', 'permissions' => ['config.manage']],
                ['label' => 'Audit log', 'route' => 'audit.index', 'match' => 'audit.*', 'icon' => 'list', 'permissions' => ['audit.view']],
                ['label' => 'Approvals', 'route' => 'approvals', 'match' => 'approvals', 'icon' => 'check', 'permissions' => [], 'approvals' => true, 'badge' => 'approvals'],
            ]],
        ];
    }

    /**
     * The sidebar for one staff member. Each item gets `state` = enabled | disabled, and `missing` (a tooltip) when disabled.
     * Groups with nothing to show are dropped; admin groups are dropped when nothing in them is permitted.
     *
     * @return list<array{title: string, items: list<array<string, mixed>>}>
     */
    public static function for(StaffSession $staff): array
    {
        $out = [];
        foreach (self::groups() as $group) {
            $items = [];
            $anyAllowed = false;
            foreach ($group['items'] as $item) {
                $allowed = ($item['approvals'] ?? false) ? $staff->canApproveAnything() : ($item['permissions'] === [] || $staff->canAny(...$item['permissions']));
                $anyAllowed = $anyAllowed || $allowed;
                $item['state'] = $allowed ? 'enabled' : 'disabled';
                $item['missing'] = $allowed ? null : (($item['approvals'] ?? false) ? 'Only people who can approve requests (any *.approve permission) see the queue' : 'Needs permission: '.implode(' or ', $item['permissions']));
                $items[] = $item;
            }
            if (($group['admin'] ?? false) === true) {
                // Administrator areas: only the entries you may use, and none at all when you may use none.
                $items = array_values(array_filter($items, fn ($i) => $i['state'] === 'enabled'));
            }
            if ($items === [] || ! $anyAllowed) {
                continue;
            }
            $out[] = ['title' => $group['title'], 'items' => $items];
        }

        return $out;
    }

    /**
     * Flat list of enabled entries (used by global search to jump to a page, and by tests).
     *
     * @return list<array<string, mixed>>
     */
    public static function enabled(StaffSession $staff): array
    {
        $flat = [];
        foreach (self::for($staff) as $g) {
            foreach ($g['items'] as $i) {
                if ($i['state'] === 'enabled') {
                    $flat[] = $i + ['group' => $g['title']];
                }
            }
        }

        return $flat;
    }

    /** Does the current route match a `match` pattern list ("a.*|b")? */
    public static function isActive(string $match): bool
    {
        foreach (explode('|', $match) as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }
}
