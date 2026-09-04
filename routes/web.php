<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ClosingController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\InvoiceController as CustomerInvoiceController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialSplitController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderDesignController;
use App\Http\Controllers\OrderSummaryController;
use App\Http\Controllers\PaymentAccountController;
use App\Http\Controllers\PaymentRefundController;
use App\Http\Controllers\PaymentVerificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SpreadsheetImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1')->name('register.store');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/notification-links/{notificationId}', [NotificationController::class, 'visit'])->middleware(['auth', 'throttle:60,1'])->name('notifications.visit');
Route::post('/webhooks/midtrans', MidtransWebhookController::class)->middleware('throttle:120,1')->name('webhooks.midtrans');

Route::prefix('customer')->name('customer.')->middleware(['auth', 'role:Customer'])->group(function () {
    Route::get('/dashboard', CustomerDashboardController::class)->name('dashboard');
    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [CustomerOrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [CustomerOrderController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/design', [CustomerOrderController::class, 'download'])->name('orders.design');
    Route::patch('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/snap', [CustomerPaymentController::class, 'snap'])->middleware('throttle:10,1')->name('orders.snap');
    Route::post('/orders/{order}/payment-confirmation', [CustomerPaymentController::class, 'confirm'])->middleware('throttle:5,1')->name('orders.payment-confirmation');
    Route::post('/orders/{order}/design-revision', [OrderDesignController::class, 'customerRevision'])->middleware('throttle:5,1')->name('orders.design-revision');
    Route::patch('/orders/{order}/design-approve', [OrderDesignController::class, 'approve'])->name('orders.design-approve');
    Route::patch('/orders/{order}/design-request-revision', [OrderDesignController::class, 'requestRevision'])->name('orders.design-request-revision');
    Route::get('/orders/{order}/design-files/{file}', [OrderDesignController::class, 'download'])->name('orders.design-files.download');
    Route::get('/notifications', [CustomerDashboardController::class, 'notifications'])->name('notifications');
    Route::get('/profile', [CustomerDashboardController::class, 'profile'])->name('profile');
    Route::get('/invoices/{invoice}', [CustomerInvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/pdf', [CustomerInvoiceController::class, 'pdf'])->name('invoices.pdf');
});

Route::middleware(['auth', 'staff'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | AUTH
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');
    Route::get('/notifications', fn () => view('notifications.index', ['notifications' => auth()->user()->notifications()->latest()->paginate(20)]))->name('notifications.index');
    Route::middleware('role:Super Admin|Owner|Admin EPUL|Admin RAPLY')->group(function () {
        Route::resource('payment-accounts', PaymentAccountController::class)->only(['index', 'store', 'update']);
        Route::get('payment-verifications', [PaymentVerificationController::class, 'index'])->name('payment-verifications.index');
        Route::get('payment-confirmations/{confirmation}/proof', [PaymentVerificationController::class, 'proof'])->name('payment-confirmations.proof');
        Route::patch('payment-confirmations/{confirmation}/approve', [PaymentVerificationController::class, 'approve'])->middleware('throttle:30,1')->name('payment-confirmations.approve');
        Route::patch('payment-confirmations/{confirmation}/reject', [PaymentVerificationController::class, 'reject'])->middleware('throttle:30,1')->name('payment-confirmations.reject');
    });
    Route::post('orders/{order}/refunds', [PaymentRefundController::class, 'store'])
        ->middleware(['role:Super Admin|Owner|Finance', 'throttle:10,1'])
        ->name('orders.refunds.store');

    /*
    |--------------------------------------------------------------------------
    | ORDERS
    |--------------------------------------------------------------------------
    */

    Route::delete(
        'orders/bulk-destroy',
        [OrderController::class, 'bulkDestroy']
    )->name('orders.bulk-destroy');

    Route::post(
        'orders/store-bulk',
        [OrderController::class, 'storeBulk']
    )->name('orders.store-bulk');

    Route::resource('orders', OrderController::class)
        ->except('show');

    Route::get(
        'orders/daily-summary',
        [OrderSummaryController::class, 'daily']
    )->name('orders.daily-summary');

    Route::get(
        'orders/weekly-closing',
        [OrderSummaryController::class, 'weekly']
    )->name('orders.weekly-closing');

    Route::patch(
        'orders/{order}/complete',
        [OrderController::class, 'complete']
    )->name('orders.complete');
    Route::get('orders/{order}/customer-detail', [OrderController::class, 'customerDetail'])->name('orders.customer-detail');
    Route::patch('orders/{order}/customer-status', [OrderController::class, 'updateCustomerStatus'])->name('orders.customer-status');
    Route::get('orders/{order}/design', [OrderController::class, 'downloadDesign'])->name('orders.design');
    Route::post('orders/{order}/design-preview', [OrderDesignController::class, 'adminPreview'])->middleware('throttle:10,1')->name('orders.design-preview');
    Route::get('orders/{order}/design-files/{file}', [OrderDesignController::class, 'download'])->name('orders.design-files.download');

    /*
    |--------------------------------------------------------------------------
    | IMPORTS
    |--------------------------------------------------------------------------
    */

    Route::get(
        'imports/income',
        [SpreadsheetImportController::class, 'incomeForm']
    )->name('imports.income.form');

    Route::post(
        'imports/income',
        [SpreadsheetImportController::class, 'income']
    )->name('imports.income');

    Route::get(
        'imports/expenses',
        [SpreadsheetImportController::class, 'expenseForm']
    )->name('imports.expenses.form');

    Route::post(
        'imports/expenses',
        [SpreadsheetImportController::class, 'expenses']
    )->name('imports.expenses');

    /*
    |--------------------------------------------------------------------------
    | INVOICES
    |--------------------------------------------------------------------------
    */

    Route::delete(
        'invoices/bulk-destroy',
        [InvoiceController::class, 'bulkDestroy']
    )->name('invoices.bulk-destroy');

    Route::resource('invoices', InvoiceController::class)
        ->except('show');

    Route::get(
        'invoices/{invoice}/print',
        [InvoiceController::class, 'print']
    )->name('invoices.print');

    Route::get(
        'invoices/{invoice}/pdf',
        [InvoiceController::class, 'downloadPdf']
    )->name('invoices.pdf');

    /*
    |--------------------------------------------------------------------------
    | EXPENSES
    |--------------------------------------------------------------------------
    */

    Route::delete(
        'expenses/bulk-destroy',
        [ExpenseController::class, 'bulkDestroy']
    )->name('expenses.bulk-destroy');

    Route::resource('expenses', ExpenseController::class)
        ->except('show');

    /*
    |--------------------------------------------------------------------------
    | MATERIALS
    |--------------------------------------------------------------------------
    */

    Route::resource('materials', MaterialController::class)
        ->except('show');

    Route::post(
        'materials/{material}/opname',
        [MaterialController::class, 'opname']
    )->name('materials.opname');

    Route::get('materials-split', [MaterialSplitController::class, 'index'])
        ->name('materials.split.index');
    Route::post('materials-split/{material}', [MaterialSplitController::class, 'store'])
        ->name('materials.split.store');

    /*
    |--------------------------------------------------------------------------
    | CLOSING
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/closing',
        [ClosingController::class, 'index']
    )->name('closing.index');

    Route::post(
        '/closing/generate',
        [ClosingController::class, 'generate']
    )->name('closing.generate');

    Route::post(
        '/closing/settings',
        [ClosingController::class, 'updateSettings']
    )->name('closing.settings');

    Route::post(
        '/closing/update-stock-akhir',
        [ClosingController::class, 'updateStockAkhir']
    )->name('closing.update-stock-akhir');

    Route::post(
        '/closing/lock',
        [ClosingController::class, 'lock']
    )->name('closing.lock');

    Route::get(
        '/closing/export/pdf',
        [ClosingController::class, 'exportPdf']
    )->name('closing.export-pdf');

    Route::get(
        '/closing/export/excel',
        [ClosingController::class, 'exportExcel']
    )->name('closing.export-excel');

    /* Route dinamis diletakkan setelah seluruh route statis Closing. */
    Route::get(
        '/closing/{closing}',
        [ClosingController::class, 'show']
    )->name('closing.show');

    Route::delete(
        '/closing/{closing}',
        [ClosingController::class, 'destroy']
    )->name('closing.destroy');

    /*
    |--------------------------------------------------------------------------
    | REPORTS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/reports',
        [ReportController::class, 'index']
    )->name('reports.index');

    Route::get(
        '/reports/export/{format}',
        [ReportController::class, 'export']
    )
        ->whereIn('format', ['pdf', 'xlsx'])
        ->name('reports.export');

    /*
    |--------------------------------------------------------------------------
    | PROFILE
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profile',
        [ProfileController::class, 'edit']
    )->name('profile.edit');

    Route::put(
        '/profile',
        [ProfileController::class, 'update']
    )->name('profile.update');

    /*
    |--------------------------------------------------------------------------
    | LEDGER / BUKU BESAR
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/ledger',
        [LedgerController::class, 'index']
    )->name('ledger.index');

    Route::post(
        '/ledger/realtime',
        [LedgerController::class, 'updateRealtime']
    )->name('ledger.realtime.update');

    Route::post(
        '/ledger/realtime/lock',
        [LedgerController::class, 'lockRealtime']
    )->name('ledger.realtime.lock');
});
