<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\StaffController;
use App\Livewire\ApprovalsQueue;
use App\Livewire\Dashboard;
use App\Livewire\SyncCenter;
use Illuminate\Support\Facades\Route;

/*
| Liveness probe for load balancers / the on-site service monitor.
| (Laravel's built-in /up endpoint is also available.)
*/
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => '007resort-admin-web',
]))->name('health');

// Sign-in is delegated to the API; this app only holds the resulting tokens in the server-side session.
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
Route::get('/mfa', [AuthController::class, 'showMfa'])->name('mfa');
Route::post('/mfa', [AuthController::class, 'verifyMfa'])->middleware('throttle:10,1')->name('mfa.verify');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('staff')->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/approvals', ApprovalsQueue::class)->name('approvals');

    // Reports
    Route::prefix('reports')->middleware('permit:report.view,report.view.all,finance.report.view')->name('reports.')->group(function (): void {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('/facility/{facility}', [ReportsController::class, 'facility'])->name('facility');
        Route::get('/shift/{session}', [ReportsController::class, 'shift'])->name('shift');
    });

    // Finance
    Route::prefix('finance')->name('finance.')->group(function (): void {
        Route::middleware('permit:payment.view,settlement.reconcile,finance.report.view')->group(function (): void {
            Route::get('/payments', [FinanceController::class, 'payments'])->name('payments');
            Route::get('/payments/{payment}', [FinanceController::class, 'payment'])->name('payment');
            Route::get('/reconciliation', [FinanceController::class, 'reconciliation'])->name('reconciliation');
            Route::post('/paystack-verify', [FinanceController::class, 'verifyPaystack'])->name('paystack-verify');
        });
        Route::post('/payments/{payment}/refund', [FinanceController::class, 'refund'])->middleware('permit:refund.execute')->name('refund');
        Route::post('/payments/{payment}/reversal', [FinanceController::class, 'reversal'])->middleware('permit:payment.reversal.execute')->name('reversal');
    });

    // Inventory
    Route::prefix('inventory')->name('inventory.')->middleware('permit:inventory.view,inventory.purchase_receipt.create,inventory.transfer.create,inventory.count.create,inventory.adjustment.request')->group(function (): void {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/movements', [InventoryController::class, 'movements'])->name('movements');
        Route::get('/counts', [InventoryController::class, 'counts'])->name('counts');
        Route::get('/adjustments', [InventoryController::class, 'adjustments'])->name('adjustments');
        Route::get('/count/{count}', [InventoryController::class, 'showCount'])->name('count.show');
        Route::post('/count/{count}/post', [InventoryController::class, 'postCount'])->name('count.post');
        Route::get('/{action}', [InventoryController::class, 'form'])->whereIn('action', array_keys(InventoryController::ACTIONS))->name('form');
        Route::post('/{action}', [InventoryController::class, 'submit'])->whereIn('action', array_keys(InventoryController::ACTIONS))->name('submit');
    });

    // Staff
    Route::prefix('staff')->name('staff.')->group(function (): void {
        Route::get('/attendance', [StaffController::class, 'attendance'])->middleware('permit:attendance.view')->name('attendance');
        Route::post('/attendance/corrections/{correction}/{decision}', [StaffController::class, 'decideCorrection'])->middleware('permit:staff.clock_correction.approve')->name('correction');
        Route::post('/attendance/corrections', [StaffController::class, 'requestCorrection'])->middleware('permit:attendance.correction.request')->name('correction.request');
        Route::get('/audit', [StaffController::class, 'audit'])->middleware('permit:audit.view')->name('audit');

        Route::middleware('permit:staff.manage')->group(function (): void {
            Route::get('/', [StaffController::class, 'index'])->name('index');
            Route::post('/', [StaffController::class, 'store'])->name('store');
            Route::get('/{staff}', [StaffController::class, 'show'])->whereUuid('staff')->name('show');
            Route::patch('/{staff}', [StaffController::class, 'update'])->whereUuid('staff')->name('update');
            Route::put('/{staff}/credentials/{kind}', [StaffController::class, 'credential'])->whereUuid('staff')->name('credential');
            Route::delete('/{staff}/credentials/nfc-card', [StaffController::class, 'removeCard'])->whereUuid('staff')->name('card.remove');
            Route::post('/{staff}/roles', [StaffController::class, 'grantRole'])->whereUuid('staff')->name('role.grant');
            Route::delete('/{staff}/roles/{assignment}', [StaffController::class, 'revokeRole'])->whereUuid('staff')->name('role.revoke');
        });
    });

    // Configuration
    Route::prefix('config')->name('config.')->middleware('permit:config.manage,facility.configure,pricing.manage,membership.plan.manage,catalog.availability.manage,catalog.manage,booking.configure')->group(function (): void {
        Route::get('/', [ConfigurationController::class, 'index'])->name('index');
        Route::middleware('permit:facility.configure,config.manage,booking.configure')->group(function (): void {
            Route::get('/facilities', [ConfigurationController::class, 'facilities'])->name('facilities');
            Route::put('/facilities/{facility}/rules', [ConfigurationController::class, 'updateRules'])->name('facilities.rules');
            Route::get('/bookings', [ConfigurationController::class, 'bookings'])->name('bookings');
            Route::patch('/bookings/{resource}', [ConfigurationController::class, 'updateResource'])->name('bookings.update');
            Route::get('/tickets', [ConfigurationController::class, 'tickets'])->name('tickets');
            Route::get('/kds', [ConfigurationController::class, 'kds'])->name('kds');
            Route::get('/payments', [ConfigurationController::class, 'payments'])->name('payments');
        });
        Route::middleware('permit:pricing.manage,config.manage,catalog.availability.manage,catalog.manage')->group(function (): void {
            Route::get('/catalog', [ConfigurationController::class, 'catalog'])->name('catalog');
            Route::put('/catalog/{product}/availability', [ConfigurationController::class, 'setAvailability'])->name('availability');
            Route::post('/catalog/products', [ConfigurationController::class, 'createProduct'])->name('catalog.product.create');
            Route::patch('/catalog/products/{product}', [ConfigurationController::class, 'updateProduct'])->name('catalog.product.update');
            Route::put('/catalog/products/{product}/price', [ConfigurationController::class, 'setPrice'])->name('catalog.price');
            Route::post('/catalog/categories', [ConfigurationController::class, 'createCategory'])->name('catalog.category.create');
        });
        Route::middleware('permit:config.manage')->group(function (): void {
            Route::get('/tax', [ConfigurationController::class, 'tax'])->name('tax');
            Route::put('/tax', [ConfigurationController::class, 'updateTax'])->name('tax.update');
        });
        Route::middleware('permit:membership.plan.manage,config.manage')->group(function (): void {
            Route::get('/memberships', [ConfigurationController::class, 'memberships'])->name('memberships');
            Route::post('/memberships', [ConfigurationController::class, 'savePlan'])->name('memberships.store');
            Route::patch('/memberships/{plan}', [ConfigurationController::class, 'savePlan'])->name('memberships.update');
        });
    });

    // Devices
    Route::prefix('devices')->name('devices.')->middleware('permit:device.register,device.revoke,attendance.device.manage')->group(function (): void {
        Route::get('/', [DevicesController::class, 'index'])->name('index');
        Route::post('/registration-code', [DevicesController::class, 'issueCode'])->name('code');
        Route::post('/{device}/revoke', [DevicesController::class, 'revoke'])->name('revoke');
        Route::post('/terminals', [DevicesController::class, 'createTerminal'])->name('terminal.create');
        Route::post('/terminals/{device}/rotate', [DevicesController::class, 'rotateTerminal'])->name('terminal.rotate');
        Route::post('/terminals/{device}/status', [DevicesController::class, 'terminalStatus'])->name('terminal.status');
    });

    // Sync & IT
    Route::get('/sync', SyncCenter::class)->middleware('permit:config.manage')->name('sync');
});
