<?php

use App\Http\Controllers\HeatSettlementController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceSettingController;
use App\Http\Controllers\KsefSettingController;
use App\Http\Controllers\MailSettingController;
use App\Http\Controllers\MaintenanceCostController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\RentChargeController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantPaymentController;
use App\Http\Controllers\TenantSettlementController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitDocumentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UtilityBillController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('overview', OverviewController::class)->name('overview');

    Route::middleware('admin')->group(function () {
        Route::get('mail-settings', [MailSettingController::class, 'edit'])->name('mail-settings.edit');
        Route::put('mail-settings', [MailSettingController::class, 'update'])->name('mail-settings.update');
        Route::post('mail-settings/test', [MailSettingController::class, 'test'])->name('mail-settings.test');

        Route::get('ksef-settings', [KsefSettingController::class, 'edit'])->name('ksef-settings.edit');
        // POST obok PUT: podmiana metody ukrytym polem `_method` bywa gubiona po drodze,
        // a zapis ustawień nie ma powodu się o to wykładać.
        Route::match(['put', 'post'], 'ksef-settings', [KsefSettingController::class, 'update'])
            ->name('ksef-settings.update');
        Route::post('ksef-settings/test', [KsefSettingController::class, 'test'])->name('ksef-settings.test');

        Route::get('invoice-settings', [InvoiceSettingController::class, 'edit'])->name('invoice-settings.edit');
        Route::post('invoice-settings', [InvoiceSettingController::class, 'update'])->name('invoice-settings.update');
    });

    Route::middleware('admin')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('{user}/approve', [UserController::class, 'approve'])->name('approve');
        Route::post('{user}/revoke', [UserController::class, 'revoke'])->name('revoke');
        Route::post('{user}/admin', [UserController::class, 'toggleAdmin'])->name('admin');
        Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::resource('properties', PropertyController::class);
    Route::resource('units', UnitController::class);
    Route::post('units/{unit}/documents', [UnitDocumentController::class, 'store'])->name('units.documents.store');
    Route::get('unit-documents/{document}', [UnitDocumentController::class, 'download'])->name('unit-documents.download');
    Route::delete('unit-documents/{document}', [UnitDocumentController::class, 'destroy'])->name('unit-documents.destroy');
    Route::resource('tenants', TenantController::class);
    Route::get('tenants/{tenant}/payments/pdf', [TenantPaymentController::class, 'pdf'])->name('tenants.payments.pdf');
    Route::post('tenants/{tenant}/payments/reminder', [TenantPaymentController::class, 'reminder'])->name('tenants.payments.reminder');
    Route::resource('meters', MeterController::class);
    Route::resource('readings', ReadingController::class)->except('show');
    Route::resource('maintenance-costs', MaintenanceCostController::class)->except('show');
    Route::resource('utility-bills', UtilityBillController::class);

    Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'show', 'store', 'destroy']);
    Route::post('invoices/import', [InvoiceController::class, 'import'])->name('invoices.import');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

    Route::resource('rent-charges', RentChargeController::class)->except('show');
    Route::post('rent-charges/{rentCharge}/paid', [RentChargeController::class, 'togglePaid'])->name('rent-charges.paid');

    Route::resource('heat-settlements', HeatSettlementController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy']);

    Route::resource('tenant-settlements', TenantSettlementController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('tenant-settlements/{tenantSettlement}/recalculate', [TenantSettlementController::class, 'recalculate'])
        ->name('tenant-settlements.recalculate');
    Route::post('tenant-settlements/{tenantSettlement}/finalize', [TenantSettlementController::class, 'finalize'])
        ->name('tenant-settlements.finalize');
    Route::get('tenant-settlements/{tenantSettlement}/pdf', [TenantSettlementController::class, 'pdf'])
        ->name('tenant-settlements.pdf');
    Route::post('tenant-settlements/{tenantSettlement}/email', [TenantSettlementController::class, 'email'])
        ->name('tenant-settlements.email');
});
