<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [BillingController::class, 'dashboard'])->name('dashboard');
    Route::get('/customers', [BillingController::class, 'customers'])->name('customers.index');
    Route::post('/customers', [BillingController::class, 'storeCustomer'])->name('customers.store');
    Route::get('/invoices', [BillingController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [BillingController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [BillingController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [BillingController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [BillingController::class, 'edit'])->whereNumber('invoice')->name('invoices.edit');
    Route::put('/invoices/{invoice}', [BillingController::class, 'update'])->whereNumber('invoice')->name('invoices.update');
    Route::post('/invoices/{invoice}/issue', [BillingController::class, 'issue'])->whereNumber('invoice')->name('invoices.issue');
    Route::post('/invoices/{invoice}/void', [BillingController::class, 'void'])->whereNumber('invoice')->name('invoices.void');
    Route::post('/invoices/{invoice}/payments', [BillingController::class, 'pay'])->whereNumber('invoice')->name('payments.store');
});
