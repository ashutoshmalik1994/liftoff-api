<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TransactionController;

Route::prefix('v1')->group(function () {

    // 🔐 Authentication
    Route::post('/auth/token', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // 🔒 Protected Routes
    Route::middleware('auth:api')->group(function () {

        // Transactions
        Route::get('/transactions', [TransactionController::class, 'getAllTransactions']);

        // Date range filter for transactions & receivables
        Route::get('/transactions/date-range', [TransactionController::class, 'getByDateRange']);
        // If you want to keep the second route, rename it or make sure it has a distinct purpose
        // Route::get('/transactions/range', [TransactionController::class, 'getByDateRanges']);
        Route::get('/transactions/{id}', [TransactionController::class, 'getById']);
        Route::post('/transactions/{id}/cancel', [TransactionController::class, 'cancelTransaction']);
        Route::get('/transactions-receive', [TransactionController::class, 'receiveData'])->name('transactions-receive');

        // Payees 
        Route::get('/payees', [TransactionController::class, 'getPayees']);
        Route::get('/payees/{id}', [TransactionController::class, 'getPayeeId']);
        Route::post('/payees/{id}/bank-accounts', [TransactionController::class, 'payeeBankAccount']);
        Route::post('/payees', [TransactionController::class, 'createPayee']);
        Route::put('/payees/{id}', [TransactionController::class, 'updatePayee']);

        // Banks
        Route::get('/banks', [TransactionController::class, 'getAllBanks']);
        Route::get('/banks/{id}', [TransactionController::class, 'getBankById']);
        Route::post('/banks', [TransactionController::class, 'createBank']);

        // Recurring Payments
        Route::get('/recurring-payments', [TransactionController::class, 'RecurringPayments']);
        Route::get('/recurring-payments/{id}', [TransactionController::class, 'GetRecurringData']);
        Route::post('/recurring-payments', [TransactionController::class, 'createRecurringPayment']);
        Route::patch('/recurring-payments/{id}', [TransactionController::class, 'updateRecurringPayment']);
        Route::post('/recurring-payments/{id}/{status}', [TransactionController::class, 'updateRecurringPaymentStatus']);

    });  // <- Close middleware group here https://app.liftoffcard.com/liftoffcard-solutions-api/api/v1/auth/token


});
