<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingSetupController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CollectionsController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\FacilitiesController;
use App\Http\Controllers\FacilityConfigController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TerminalsController;
use App\Http\Controllers\Website\ContentController;
use App\Http\Controllers\Website\GalleryController;
use App\Http\Controllers\Website\HomepageController;
use App\Http\Controllers\Website\MediaController;
use App\Http\Controllers\Website\MessagesController;
use App\Http\Controllers\Website\OverviewController;
use App\Http\Controllers\Website\SettingsController;
use App\Http\Controllers\Website\SubscribersController;
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

// Form-control gallery (every control in every state): development only, no sign-in needed so it can be reviewed on any build.
if (app()->environment(['local', 'testing'])) {
    Route::view('/styleguide/forms', 'pages.styleguide-forms')->name('styleguide.forms');
}

Route::middleware('staff')->group(function (): void {
    // Component gallery: development only.
    if (app()->environment(['local', 'testing'])) {
        Route::view('/styleguide', 'pages.styleguide')->name('styleguide');
    }

    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/approvals', ApprovalsQueue::class)->name('approvals');
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // Operations (read-only views over what the tablets/POS/KDS write through the API)
    Route::prefix('operations')->group(function (): void {
        Route::get('/orders', [OperationsController::class, 'orders'])->middleware('permit:order.view')->name('orders.index');
        Route::get('/orders/{order}', [OperationsController::class, 'order'])->middleware('permit:order.view')->name('orders.show');
        Route::get('/tables', [OperationsController::class, 'tables'])->middleware('permit:order.view,table.manage')->name('tables.index');
        Route::get('/bookings', [OperationsController::class, 'bookings'])->middleware('permit:booking.view')->name('bookings.index');
        Route::get('/tickets', [OperationsController::class, 'tickets'])->middleware('permit:ticket.view')->name('tickets.index');
        Route::get('/memberships', [OperationsController::class, 'memberships'])->middleware('permit:membership.view')->name('memberships.index');
    });

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
            Route::get('/settlements', [FinanceController::class, 'reconciliation'])->name('reconciliation');
            Route::get('/refunds', [FinanceController::class, 'refunds'])->name('refunds');
            Route::post('/paystack-verify', [FinanceController::class, 'verifyPaystack'])->name('paystack-verify');
        });
        Route::middleware('permit:payment.view,payment.confirm')->group(function (): void {
            Route::get('/collections', [CollectionsController::class, 'index'])->name('collections');
            Route::post('/collections/{payment}/confirm', [CollectionsController::class, 'confirm'])->middleware('permit:payment.confirm')->name('collections.confirm');
            Route::post('/collections/{payment}/reject', [CollectionsController::class, 'reject'])->middleware('permit:payment.confirm')->name('collections.reject');
        });
        Route::middleware('permit:cash_handover.view,cash_handover.receive,cash_handover.signoff')->group(function (): void {
            Route::get('/handovers', [CollectionsController::class, 'handovers'])->name('handovers');
            Route::post('/handovers/{handover}/receive', [CollectionsController::class, 'receive'])->middleware('permit:cash_handover.receive')->name('handovers.receive');
            Route::post('/handovers/{handover}/signoff', [CollectionsController::class, 'signoff'])->middleware('permit:cash_handover.signoff')->name('handovers.signoff');
        });
        Route::get('/cash-sessions', [FinanceController::class, 'cashSessions'])->middleware('permit:cash_session.view')->name('cash-sessions');
        Route::post('/payments/{payment}/refund', [FinanceController::class, 'refund'])->middleware('permit:refund.execute')->name('refund');
        Route::post('/payments/{payment}/reversal', [FinanceController::class, 'reversal'])->middleware('permit:payment.reversal.execute')->name('reversal');
    });

    // Inventory
    Route::prefix('inventory')->name('inventory.')->middleware('permit:inventory.view,inventory.purchase_receipt.create,inventory.transfer.create,inventory.count.create,inventory.adjustment.request')->group(function (): void {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/movements', [InventoryController::class, 'movements'])->name('movements');
        Route::get('/counts', [InventoryController::class, 'counts'])->name('counts');
        Route::get('/adjustments', [InventoryController::class, 'adjustments'])->name('adjustments');
        Route::get('/transfers', [InventoryController::class, 'transfers'])->name('transfers');
        Route::get('/suppliers', [InventoryController::class, 'suppliers'])->name('suppliers');
        Route::post('/suppliers', [InventoryController::class, 'createSupplier'])->name('suppliers.store');
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

        Route::middleware('permit:staff.manage')->group(function (): void {
            Route::get('/', [StaffController::class, 'index'])->name('index');
            Route::post('/', [StaffController::class, 'store'])->name('store');
            Route::get('/{staff}', [StaffController::class, 'show'])->whereUuid('staff')->name('show');
            Route::patch('/{staff}', [StaffController::class, 'update'])->whereUuid('staff')->name('update');
            Route::patch('/{staff}/collection-policy', [StaffController::class, 'collectionPolicy'])->whereUuid('staff')->name('collection-policy');
            Route::put('/{staff}/credentials/{kind}', [StaffController::class, 'credential'])->whereUuid('staff')->name('credential');
            Route::delete('/{staff}/credentials/nfc-card', [StaffController::class, 'removeCard'])->whereUuid('staff')->name('card.remove');
            Route::post('/{staff}/roles', [StaffController::class, 'grantRole'])->whereUuid('staff')->name('role.grant');
            Route::delete('/{staff}/roles/{assignment}', [StaffController::class, 'revokeRole'])->whereUuid('staff')->name('role.revoke');
        });
    });

    // Website (the public site's content: settings, homepage, pages, blog, events, gallery, media, subscribers, messages). All of it lives in the API's CMS module.
    Route::prefix('website')->name('website.')->middleware('permit:cms.view,cms.subscribers.view,cms.messages.manage')->group(function (): void {
        Route::get('/', [OverviewController::class, 'index'])->name('index');

        Route::middleware('permit:cms.view')->group(function (): void {
            Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
            Route::put('/settings', [SettingsController::class, 'update'])->middleware('permit:cms.manage')->name('settings.update');

            Route::get('/homepage', [HomepageController::class, 'index'])->name('homepage');
            Route::middleware('permit:cms.manage')->group(function (): void {
                Route::post('/homepage/sections', [HomepageController::class, 'store'])->name('homepage.store');
                Route::post('/homepage/reorder', [HomepageController::class, 'reorder'])->name('homepage.reorder');
                Route::patch('/homepage/sections/{id}', [HomepageController::class, 'update'])->whereUuid('id')->name('homepage.update');
                Route::delete('/homepage/sections/{id}', [HomepageController::class, 'destroy'])->whereUuid('id')->name('homepage.destroy');
            });
            Route::post('/homepage/sections/{id}/{state}', [HomepageController::class, 'toggle'])->whereUuid('id')->whereIn('state', ['enable', 'disable'])->middleware('permit:cms.publish')->name('homepage.toggle');

            // Pages, blog posts, events and blog categories share one list and one editor (App\Support\Cms\Resources).
            foreach (['categories' => 'blog/categories', 'posts' => 'blog', 'pages' => 'pages', 'events' => 'events'] as $key => $uri) {
                Route::get("/{$uri}", [ContentController::class, 'index'])->defaults('resource', $key)->name("{$key}.index");
                Route::get("/{$uri}/new", [ContentController::class, 'create'])->defaults('resource', $key)->middleware('permit:cms.manage')->name("{$key}.create");
                Route::post("/{$uri}", [ContentController::class, 'store'])->defaults('resource', $key)->middleware('permit:cms.manage')->name("{$key}.store");
                Route::get("/{$uri}/{id}", [ContentController::class, 'edit'])->defaults('resource', $key)->whereUuid('id')->name("{$key}.edit");
                Route::put("/{$uri}/{id}", [ContentController::class, 'update'])->defaults('resource', $key)->whereUuid('id')->middleware('permit:cms.manage')->name("{$key}.update");
                Route::delete("/{$uri}/{id}", [ContentController::class, 'destroy'])->defaults('resource', $key)->whereUuid('id')->middleware('permit:cms.manage')->name("{$key}.destroy");
                Route::post("/{$uri}/{id}/{action}", [ContentController::class, 'transition'])->defaults('resource', $key)->whereUuid('id')->whereIn('action', ['publish', 'unpublish', 'archive'])->middleware('permit:cms.publish')->name("{$key}.transition");
            }

            Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery');
            Route::get('/gallery/{id}', [GalleryController::class, 'show'])->whereUuid('id')->name('gallery.show');
            Route::middleware('permit:cms.manage')->group(function (): void {
                Route::post('/gallery', [GalleryController::class, 'store'])->name('gallery.store');
                Route::put('/gallery/{id}', [GalleryController::class, 'update'])->whereUuid('id')->name('gallery.update');
                Route::delete('/gallery/{id}', [GalleryController::class, 'destroy'])->whereUuid('id')->name('gallery.destroy');
                Route::post('/gallery/{id}/upload', [GalleryController::class, 'upload'])->whereUuid('id')->name('gallery.upload');
                Route::post('/gallery/{id}/add', [GalleryController::class, 'add'])->whereUuid('id')->name('gallery.add');
                Route::post('/gallery/{id}/cover', [GalleryController::class, 'cover'])->whereUuid('id')->name('gallery.cover');
                Route::delete('/gallery/{id}/items/{item}', [GalleryController::class, 'removeItem'])->whereUuid(['id', 'item'])->name('gallery.item.destroy');
            });
            Route::post('/gallery/{id}/{action}', [GalleryController::class, 'transition'])->whereUuid('id')->whereIn('action', ['publish', 'unpublish', 'archive'])->middleware('permit:cms.publish')->name('gallery.transition');

            Route::get('/media', [MediaController::class, 'index'])->name('media');
            Route::get('/media/picker', [MediaController::class, 'picker'])->name('media.picker');
            Route::get('/media/{id}/usage', [MediaController::class, 'usage'])->whereUuid('id')->name('media.usage');
            Route::post('/media/upload', [MediaController::class, 'upload'])->middleware('permit:cms.media.manage')->name('media.upload');
            Route::patch('/media/{id}', [MediaController::class, 'update'])->whereUuid('id')->middleware('permit:cms.media.manage')->name('media.update');
            Route::delete('/media/{id}', [MediaController::class, 'destroy'])->whereUuid('id')->middleware('permit:cms.media.manage')->name('media.destroy');
        });

        Route::middleware('permit:cms.subscribers.view')->group(function (): void {
            Route::get('/subscribers', [SubscribersController::class, 'index'])->name('subscribers');
            Route::get('/subscribers/export', [SubscribersController::class, 'export'])->middleware('permit:cms.subscribers.export')->name('subscribers.export');
            Route::post('/subscribers/{id}/unsubscribe', [SubscribersController::class, 'unsubscribe'])->whereUuid('id')->middleware('permit:cms.manage')->name('subscribers.unsubscribe');
            Route::delete('/subscribers/{id}', [SubscribersController::class, 'destroy'])->whereUuid('id')->middleware('permit:cms.manage')->name('subscribers.destroy');
        });

        Route::middleware('permit:cms.messages.manage')->group(function (): void {
            Route::get('/messages', [MessagesController::class, 'index'])->name('messages');
            Route::post('/messages/bulk', [MessagesController::class, 'bulk'])->name('messages.bulk');
            Route::patch('/messages/{id}', [MessagesController::class, 'update'])->whereUuid('id')->name('messages.update');
            Route::delete('/messages/{id}', [MessagesController::class, 'destroy'])->whereUuid('id')->name('messages.destroy');
        });
    });

    // Configuration
    Route::prefix('setup')->name('setup.')->middleware('permit:config.manage,config.view,facility.manage,facility.configure,settings.manage,ticket_type.manage,pricing.manage,membership.plan.manage,catalog.availability.manage,catalog.manage,booking.configure')->group(function (): void {
        Route::get('/', [ConfigurationController::class, 'index'])->name('index');
        Route::middleware('permit:facility.manage,facility.configure,config.manage,config.view,booking.configure,ticket_type.manage,settings.manage,catalog.manage')->group(function (): void {
            Route::get('/facilities', [FacilitiesController::class, 'index'])->name('facilities');
            Route::get('/facilities/new', [FacilitiesController::class, 'create'])->name('facilities.create');
            Route::post('/facilities', [FacilitiesController::class, 'store'])->name('facilities.store');
            Route::get('/facilities/{facility}', [FacilitiesController::class, 'show'])->whereUuid('facility')->name('facilities.show');
            Route::patch('/facilities/{facility}', [FacilitiesController::class, 'update'])->whereUuid('facility')->name('facilities.update');
            Route::put('/facilities/{facility}/capabilities', [FacilitiesController::class, 'setCapabilities'])->whereUuid('facility')->name('facilities.capabilities');
            Route::put('/facilities/{facility}/rules', [FacilitiesController::class, 'setRules'])->whereUuid('facility')->name('facilities.rules');
            Route::post('/facilities/{facility}/deactivate', [FacilitiesController::class, 'deactivate'])->whereUuid('facility')->name('facilities.deactivate');
            Route::post('/facilities/{facility}/reactivate', [FacilitiesController::class, 'reactivate'])->whereUuid('facility')->name('facilities.reactivate');
            Route::post('/facilities/{facility}/move', [FacilitiesController::class, 'move'])->whereUuid('facility')->name('facilities.move');
            Route::post('/facilities/{facility}/points', [FacilityConfigController::class, 'storePoint'])->whereUuid('facility')->name('points.store');
            Route::patch('/points/{point}', [FacilityConfigController::class, 'updatePoint'])->whereUuid('point')->name('points.update');
            Route::post('/points/{point}/{state}', [FacilityConfigController::class, 'pointState'])->whereUuid('point')->name('points.state');
            Route::post('/facilities/{facility}/tables', [FacilityConfigController::class, 'storeTable'])->whereUuid('facility')->name('tables.store');
            Route::post('/facilities/{facility}/tables/bulk', [FacilityConfigController::class, 'bulkTables'])->whereUuid('facility')->name('tables.bulk');
            Route::patch('/tables/{table}', [FacilityConfigController::class, 'updateTable'])->whereUuid('table')->name('tables.update');
            Route::post('/tables/{table}/merge', [FacilityConfigController::class, 'mergeTable'])->whereUuid('table')->name('tables.merge');
            Route::post('/tables/{table}/{state}', [FacilityConfigController::class, 'tableState'])->whereUuid('table')->name('tables.state');
            Route::get('/bookings', [BookingSetupController::class, 'index'])->name('bookings');
            Route::post('/bookings', [BookingSetupController::class, 'store'])->name('bookings.store');
            Route::get('/bookings/{resource}', [BookingSetupController::class, 'show'])->whereUuid('resource')->name('bookings.show');
            Route::patch('/bookings/{resource}', [BookingSetupController::class, 'update'])->whereUuid('resource')->name('bookings.update');
            Route::put('/bookings/{resource}/schedule', [BookingSetupController::class, 'schedule'])->whereUuid('resource')->name('bookings.schedule');
            Route::put('/bookings/{resource}/rules', [BookingSetupController::class, 'rules'])->whereUuid('resource')->name('bookings.rules');
            Route::post('/bookings/{resource}/blackouts', [BookingSetupController::class, 'blackout'])->whereUuid('resource')->name('bookings.blackouts.store');
            Route::delete('/bookings/{resource}/blackouts/{blackout}', [BookingSetupController::class, 'removeBlackout'])->whereUuid(['resource', 'blackout'])->name('bookings.blackouts.destroy');
            Route::get('/tickets', [ConfigurationController::class, 'tickets'])->name('tickets');
            Route::post('/tickets', [ConfigurationController::class, 'saveTicketType'])->name('tickets.store');
            Route::patch('/tickets/{type}', [ConfigurationController::class, 'saveTicketType'])->whereUuid('type')->name('tickets.update');
            Route::get('/kds', [ConfigurationController::class, 'kds'])->name('kds');
            Route::put('/kds', [ConfigurationController::class, 'saveRouting'])->name('kds.save');
            Route::post('/kds/routes', [ConfigurationController::class, 'saveRoute'])->name('kds.route.store');
            Route::patch('/kds/routes/{route}', [ConfigurationController::class, 'saveRoute'])->whereUuid('route')->name('kds.route.update');
            Route::get('/payments', [ConfigurationController::class, 'payments'])->name('payments');
            Route::put('/payments', [ConfigurationController::class, 'savePaymentMethods'])->name('payments.save');
        });
        Route::middleware('permit:pricing.manage,config.manage,catalog.availability.manage,catalog.manage')->group(function (): void {
            Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog');
            Route::get('/catalog/products/{product}', [CatalogController::class, 'product'])->whereUuid('product')->name('catalog.product');
            Route::put('/catalog/{product}/availability', [CatalogController::class, 'setAvailability'])->whereUuid('product')->name('availability');
            Route::post('/catalog/products', [CatalogController::class, 'store'])->name('catalog.product.create');
            Route::patch('/catalog/products/{product}', [CatalogController::class, 'update'])->whereUuid('product')->name('catalog.product.update');
            Route::put('/catalog/products/{product}/facilities/{facility}', [CatalogController::class, 'facility'])->whereUuid(['product', 'facility'])->name('catalog.product.facility');
            Route::delete('/catalog/products/{product}/facilities/{facility}', [CatalogController::class, 'removeFacility'])->whereUuid(['product', 'facility'])->name('catalog.product.facility.remove');
            Route::post('/catalog/products/{product}/prices', [CatalogController::class, 'price'])->whereUuid('product')->name('catalog.product.price');
            Route::put('/catalog/products/{product}/stock-links', [CatalogController::class, 'stockLinks'])->whereUuid('product')->name('catalog.product.stock');
            Route::post('/catalog/categories', [CatalogController::class, 'category'])->name('catalog.category.create');
            Route::post('/catalog/categories/{category}/route', [CatalogController::class, 'categoryRoute'])->whereUuid('category')->name('catalog.category.route');
            Route::post('/catalog/tax-rates', [CatalogController::class, 'taxRate'])->name('catalog.tax.create');
            Route::patch('/catalog/tax-rates/{rate}', [CatalogController::class, 'taxRate'])->whereUuid('rate')->name('catalog.tax.update');
            Route::post('/catalog/price-lists', [CatalogController::class, 'priceList'])->name('catalog.price-list');
            Route::get('/catalog/export/{kind}', [CatalogController::class, 'export'])->name('catalog.export');
            Route::post('/catalog/import/{kind}', [CatalogController::class, 'import'])->name('catalog.import');
        });
        Route::middleware('permit:config.manage')->group(function (): void {
            Route::get('/business', [ConfigurationController::class, 'business'])->name('business');
            Route::get('/tax', fn () => redirect(route('setup.business').'#tax'))->name('tax');
            Route::put('/tax', [ConfigurationController::class, 'updateTax'])->name('tax.update');
        });
        Route::middleware('permit:settings.manage,config.manage')->group(function (): void {
            Route::put('/business', [ConfigurationController::class, 'updateBusiness'])->name('business.update');
            Route::put('/receipt', [ConfigurationController::class, 'updateReceipt'])->name('receipt.update');
        });
        Route::middleware('permit:membership.plan.manage,config.manage')->group(function (): void {
            Route::get('/memberships', [ConfigurationController::class, 'memberships'])->name('memberships');
            Route::post('/memberships', [ConfigurationController::class, 'savePlan'])->name('memberships.store');
            Route::patch('/memberships/{plan}', [ConfigurationController::class, 'savePlan'])->name('memberships.update');
        });
    });

    // People: roles catalogue, audit log
    Route::middleware('permit:role_assignment.manage,role.manage')->prefix('people/roles')->name('people.roles')->group(function (): void {
        Route::get('/', [PeopleController::class, 'roles']);
        Route::post('/', [PeopleController::class, 'create'])->name('.store');
        Route::put('/{role}/permissions', [PeopleController::class, 'permissions'])->whereUuid('role')->name('.permissions');
        Route::patch('/{role}', [PeopleController::class, 'update'])->whereUuid('role')->name('.update');
        Route::delete('/{role}', [PeopleController::class, 'destroy'])->whereUuid('role')->name('.destroy');
    });
    Route::get('/system/audit', [StaffController::class, 'audit'])->middleware('permit:audit.view,config.view')->name('audit.index');

    // Devices
    Route::prefix('devices')->name('devices.')->middleware('permit:device.register,device.revoke,attendance.device.manage,device.manage')->group(function (): void {
        Route::get('/', [DevicesController::class, 'index'])->name('index');
        Route::get('/payment-terminals', [TerminalsController::class, 'index'])->name('payment-terminals');
        Route::post('/payment-terminals', [TerminalsController::class, 'store'])->name('payment-terminals.store');
        Route::patch('/payment-terminals/{terminal}', [TerminalsController::class, 'update'])->whereUuid('terminal')->name('payment-terminals.update');
        Route::patch('/{device}', [DevicesController::class, 'update'])->whereUuid('device')->name('update');
        Route::post('/registration-code', [DevicesController::class, 'issueCode'])->name('code');
        Route::post('/{device}/revoke', [DevicesController::class, 'revoke'])->name('revoke');
        Route::post('/terminals', [DevicesController::class, 'createTerminal'])->name('terminal.create');
        Route::post('/terminals/{device}/rotate', [DevicesController::class, 'rotateTerminal'])->name('terminal.rotate');
        Route::post('/terminals/{device}/status', [DevicesController::class, 'terminalStatus'])->name('terminal.status');
    });

    // Sync & IT
    Route::get('/sync', SyncCenter::class)->middleware('permit:config.manage')->name('sync');
});
