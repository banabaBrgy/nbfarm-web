<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProductionDetailsController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\SalesHistoryController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\QrScannerController;
use App\Http\Controllers\QrRecordController;
use App\Http\Controllers\QrTransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ManagerDashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OwnerReportController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\AlertController;
use App\Models\Production;
use App\Services\WeatherService;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function (WeatherService $weather) {
    if (auth()->check()) {
        $role = strtolower(trim((string) auth()->user()->role));

        $destination = match ($role) {
            'farm owner', 'owner' => 'owner.dashboard',
            'farm manager', 'manager' => 'manager.dashboard',
            'poultry staff', 'staff' => 'staff.dashboard',
            default => null,
        };

        return $destination
            ? redirect()->route($destination)
            : redirect('/login');
    }

    return view('landing', [
        'eggsStock' => Production::leftoverStock(),
        'weather' => $weather->current(),
    ]);
})->name('landing');

// Login
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

// Forgot Password

Route::get('/forgot-password', [ForgotPasswordController::class, 'showForm'])
    ->name('forgot.password');

Route::post('/forgot-password/check', [ForgotPasswordController::class, 'checkUsername'])
    ->name('forgot.check');

Route::post('/forgot-password/verify', [ForgotPasswordController::class, 'verifyAnswer'])
    ->name('forgot.verify');

Route::get('/forgot-password/verify', [ForgotPasswordController::class, 'showVerifyForm'])
    ->name('forgot.verify.form');

Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'resetPassword'])
    ->name('forgot.reset');

Route::get('/forgot-password/reset', [ForgotPasswordController::class, 'showResetForm'])
    ->name('forgot.reset.form');

Route::view('/terms', 'terms')->name('terms');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->get('/weather', [WeatherController::class, 'current'])
    ->name('weather.current');

Route::middleware('auth')->prefix('alerts')->name('alerts.')->group(function () {
    Route::get('/', [AlertController::class, 'index'])->name('index');
    Route::post('/push', [AlertController::class, 'togglePush'])->name('push');
    Route::post('/subscribe', [AlertController::class, 'subscribe'])->name('subscribe');
    Route::post('/unsubscribe', [AlertController::class, 'unsubscribe'])->name('unsubscribe');
    Route::post('/read-all', [AlertController::class, 'readAll'])->name('readAll');
    Route::post('/{alert}/read', [AlertController::class, 'read'])->name('read');
    Route::post('/{alert}/unread', [AlertController::class, 'unread'])->name('unread');
});

/*
|--------------------------------------------------------------------------
| MANAGER ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('manager')
    ->middleware(['auth', 'role:manager'])
    ->name('manager.')
    ->group(function () {

        Route::get('/dashboard', [ManagerDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/sales/history', [SalesHistoryController::class, 'index'])
            ->name('sales.history');

        // Production
        Route::get('/production', [ProductionController::class, 'index'])->name('production');

        Route::get('/production/{id}/details', [ProductionDetailsController::class, 'index'])
            ->name('production.details');

        Route::get('/production/batch/{batch}', [BatchController::class, 'show'])
            ->name('batch.details');

        // Inventory
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
        Route::get('/inventory/{id}/history', [InventoryController::class, 'history'])->name('inventory.history');
        Route::post('/inventory/add-stock', [InventoryController::class, 'addStock'])->name('inventory.addStock');
        Route::post('/inventory/increase-stock', [InventoryController::class, 'increaseStock'])->name('inventory.increaseStock');
        Route::delete('/inventory/delete/{id}', [InventoryController::class, 'deleteItem'])->name('inventory.delete');

        // QR Scanner
        Route::get('/production/scan-qr', [QrScannerController::class, 'index'])
            ->name('production.scan');

        Route::post('/scan/store', [QrScannerController::class, 'store'])
            ->name('scan.store');

        // QR Records
        Route::post('/qr/generate', [QrRecordController::class, 'generate'])
            ->name('qr.generate');

        Route::get('/qr/list', [QrRecordController::class, 'index'])
            ->name('qr.list');

        Route::get('/qr/print', [QrRecordController::class, 'print'])
            ->name('qr.print');

        // QR Transactions
        Route::post('/qr-transactions/store', [QrTransactionController::class, 'store'])
            ->name('qr.transaction.store');

        Route::post('/qr/update/{id}', [QrTransactionController::class, 'update'])
            ->name('qr.update');

        Route::get('/reports', [ReportController::class, 'index'])
            ->name('reports');

        Route::post('/reports/store', [ReportController::class, 'store'])
            ->name('reports.store');

        Route::post('/reports/send/{report}', [ReportController::class, 'send'])
            ->name('reports.send');

        Route::delete('/reports/delete/{report}', [ReportController::class, 'destroy'])
            ->name('reports.destroy');

        Route::get('/reports/preview/{type}', [ReportController::class, 'preview'])
            ->name('reports.preview');

        Route::get('/dispatch', [DispatchController::class, 'index'])->name('dispatch');
        Route::post('/dispatch/store', [DispatchController::class, 'store'])->name('dispatch.store');
        Route::patch('/dispatch/{dispatch}/status', [DispatchController::class, 'updateStatus'])->name('dispatch.updateStatus');
        Route::put('/dispatch/{dispatch}', [DispatchController::class, 'update'])->name('dispatch.update');
        Route::delete('/dispatch/bulk', [DispatchController::class, 'destroyBulk'])->name('dispatch.destroyBulk');
        Route::delete('/dispatch/{dispatch}', [DispatchController::class, 'destroy'])
            ->name('dispatch.destroy');
    });
/*
|--------------------------------------------------------------------------
| OWNER ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/sales/history', [SalesHistoryController::class, 'index'])->name('sales.history');
    Route::get('/production', [ProductionController::class, 'index'])->name('production');
    Route::get('/production/{id}/details', [ProductionDetailsController::class, 'index'])->name('production.details');
    Route::get('/production/batch/{batch}', [BatchController::class, 'show'])->name('batch.details');
    Route::get('/production/scan-qr', [QrScannerController::class, 'index'])->name('production.scan');

    Route::post('/scan/store', [QrScannerController::class, 'store'])->name('scan.store');

    Route::post('/qr/generate', [QrRecordController::class, 'generate'])->name('qr.generate');
    Route::get('/qr/list', [QrRecordController::class, 'index'])->name('qr.list');
    Route::get('/qr/print', [QrRecordController::class, 'print'])->name('qr.print');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::get('/inventory/{id}/history', [InventoryController::class, 'history'])->name('inventory.history');
    Route::post('/inventory/add-stock', [InventoryController::class, 'addStock'])->name('inventory.addStock');
    Route::delete('/inventory/{id}', [InventoryController::class, 'deleteItem'])->name('inventory.delete');
    Route::post('/inventory/increase-stock', [InventoryController::class, 'increaseStock'])->name('inventory.increaseStock');

    Route::get('/dispatch', [DispatchController::class, 'index'])->name('dispatch');
    Route::post('/dispatch/store', [DispatchController::class, 'store'])->name('dispatch.store');
    Route::patch('/dispatch/{dispatch}/status', [DispatchController::class, 'updateStatus'])->name('dispatch.updateStatus');
    Route::put('/dispatch/{dispatch}', [DispatchController::class, 'update'])->name('dispatch.update');
    Route::delete('/dispatch/{dispatch}', [DispatchController::class, 'destroy'])
        ->name('dispatch.destroy');

    Route::view('/forecast', 'forecast')->name('forecast');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggleStatus');

    Route::get('/revenue', [RevenueController::class, 'index'])->name('revenue');
    Route::post('/revenue', [RevenueController::class, 'store'])->name('revenue.store');
    Route::get('/revenue/search', [RevenueController::class, 'search'])->name('revenue.search');

    /*
|--------------------------------------------------------------------------
| REPORTS
|--------------------------------------------------------------------------
*/

Route::get('/reports', [OwnerReportController::class, 'index'])
    ->name('reports');

Route::get('/reports/{report}', [OwnerReportController::class, 'show'])
    ->name('reports.show');

Route::post('/reports/{report}/remark', [OwnerReportController::class, 'saveRemark'])
    ->name('reports.remark');

    
    

});
/*
|--------------------------------------------------------------------------
| STAFF ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Production
    Route::get('/production', [ProductionController::class, 'index'])->name('production');

    Route::get('/production/{id}/details', [ProductionDetailsController::class, 'index'])
        ->name('production.details');

    Route::get('/production/batch/{batch}', [BatchController::class, 'show'])
        ->name('batch.details');

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::get('/inventory/{id}/history', [InventoryController::class, 'history'])->name('inventory.history');
    Route::post('/inventory/add-stock', [InventoryController::class, 'addStock'])->name('inventory.addStock');
    Route::post('/inventory/increase-stock', [InventoryController::class, 'increaseStock'])->name('inventory.increaseStock');
    Route::delete('/inventory/delete/{id}', [InventoryController::class, 'deleteItem'])->name('inventory.delete');

    // QR Scanner
    Route::get('/production/scan-qr', [QrScannerController::class, 'index'])
        ->name('production.scan');

    Route::post('/scan/store', [QrScannerController::class, 'store'])
        ->name('scan.store');

    // QR Records
    Route::post('/qr/generate', [QrRecordController::class, 'generate'])
        ->name('qr.generate');

    Route::get('/qr/list', [QrRecordController::class, 'index'])
        ->name('qr.list');

    Route::get('/qr/print', [QrRecordController::class, 'print'])
        ->name('qr.print');

    // QR Transactions
    Route::post('/qr-transactions/store', [QrTransactionController::class, 'store'])
        ->name('qr.transaction.store');

    Route::post('/qr/update/{id}', [QrTransactionController::class, 'update'])
        ->name('qr.update');
});
