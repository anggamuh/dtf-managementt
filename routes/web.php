<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ClosingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SpreadsheetImportController;

Route::get('/', function () {
    return view('auth.login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('orders', OrderController::class)->except('show');
    Route::patch('orders/{order}/complete', [OrderController::class, 'complete'])->name('orders.complete');
    Route::delete('orders/bulk-destroy', [OrderController::class, 'bulkDestroy'])->name('orders.bulk-destroy');
    Route::get('imports/income', [SpreadsheetImportController::class, 'incomeForm'])->name('imports.income.form');
    Route::post('imports/income', [SpreadsheetImportController::class, 'income'])->name('imports.income');
    Route::get('imports/expenses', [SpreadsheetImportController::class, 'expenseForm'])->name('imports.expenses.form');
    Route::post('imports/expenses', [SpreadsheetImportController::class, 'expenses'])->name('imports.expenses');
    Route::resource('invoices', InvoiceController::class)->except('show');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
    Route::delete('invoices/bulk-destroy', [InvoiceController::class, 'bulkDestroy'])->name('invoices.bulk-destroy');
    Route::resource('expenses', ExpenseController::class)->except('show');
    Route::delete('expenses/bulk-destroy', [ExpenseController::class, 'bulkDestroy'])->name('expenses.bulk-destroy');
    Route::resource('materials', MaterialController::class)->except('show');
    Route::post('materials/{material}/opname', [MaterialController::class, 'opname'])->name('materials.opname');

    Route::get('/closing', [ClosingController::class, 'index'])->name('closing.index');
    Route::get('/closing/{closing}', [ClosingController::class, 'show'])->name('closing.show');
    Route::delete('/closing/{closing}', [ClosingController::class, 'destroy'])->name('closing.destroy');
    Route::post('/closing/generate', [ClosingController::class, 'generate'])->name('closing.generate');
    Route::post('/closing/settings', [ClosingController::class, 'updateSettings'])->name('closing.settings');
    Route::post('/closing/update-stock-akhir', [ClosingController::class, 'updateStockAkhir'])->name('closing.update-stock-akhir');
    Route::post('/closing/lock', [ClosingController::class, 'lock'])->name('closing.lock');
    Route::get('/closing/export/pdf', [ClosingController::class, 'exportPdf'])->name('closing.export-pdf');
    Route::get('/closing/export/excel', [ClosingController::class, 'exportExcel'])->name('closing.export-excel');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{format}', [ReportController::class, 'export'])->whereIn('format', ['pdf', 'xlsx'])->name('reports.export');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

});
