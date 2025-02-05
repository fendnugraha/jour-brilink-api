<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\ProductCategoryController;
use App\Models\Finance;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('role.warehouse');
    });

    Route::apiResource('users', UserController::class);
    Route::get('get-all-users', [UserController::class, 'getAllUsers']);
    Route::put('users/{id}/update-password', [UserController::class, 'updatePassword']);

    Route::apiResource('accounts', ChartOfAccountController::class);
    Route::get('get-all-accounts', [ChartOfAccountController::class, 'getAllAccounts']);
    Route::get('get-account-by-account-id', [ChartOfAccountController::class, 'getAccountByAccountId']);
    Route::get('get-cash-and-bank', [ChartOfAccountController::class, 'getCashAndBank']);
    Route::apiResource('category-accounts', AccountController::class);
    Route::delete('delete-selected-account', [ChartOfAccountController::class, 'deleteAll']);
    Route::put('warehouse/{warehouse}/add-cash-bank/{id}', [ChartOfAccountController::class, 'addCashAndBankToWarehouse']);
    Route::get('get-cash-bank-by-warehouse/{warehouse}', [ChartOfAccountController::class, 'getCashAndBankByWarehouse']);
    Route::get('get-expense-accounts', [ChartOfAccountController::class, 'getExpenses']);
    Route::get('get-cash-bank-balance/{warehouse}', [ChartOfAccountController::class, 'getCashBankBalance']);
    Route::get('daily-dashboard/{warehouse}/{startDate}/{endDate}', [ChartOfAccountController::class, 'dailyDashboard']);

    Route::apiResource('products', ProductController::class);
    Route::apiResource('product-categories', ProductCategoryController::class);
    Route::get('get-all-products', [ProductController::class, 'getAllProducts']);

    //contacts
    Route::apiResource('contacts', ContactController::class);
    Route::get('get-all-contacts', [ContactController::class, 'getAllContacts']);

    Route::apiResource('warehouse', WarehouseController::class);
    Route::get('get-all-warehouses', [WarehouseController::class, 'getAllWarehouses']);

    //journals
    Route::apiResource('journals', JournalController::class);
    Route::post('create-transfer', [JournalController::class, 'createTransfer']);
    Route::post('create-voucher', [JournalController::class, 'createVoucher']);
    Route::post('create-deposit', [JournalController::class, 'createDeposit']);
    Route::post('create-mutation', [JournalController::class, 'createMutation']);
    Route::get('get-journal-by-warehouse/{warehouse}/{startDate}/{endDate}', [JournalController::class, 'getJournalByWarehouse']);
    Route::get('get-expenses/{warehouse}/{startDate}/{endDate}', [JournalController::class, 'getExpenses']);
    Route::get('get-warehouse-balance/{endDate}', [JournalController::class, 'getWarehouseBalance']);
    Route::get('get-revenue-report/{startDate}/{endDate}', [JournalController::class, 'getRevenueReport']);
    Route::get('mutation-history/{account}/{startDate}/{endDate}', [JournalController::class, 'mutationHistory']);

    //transactions
    Route::apiResource('transactions', TransactionController::class);
    Route::get('get-trx-vcr/{warehouse}/{startDate}/{endDate}', [TransactionController::class, 'getTrxVcr']);
    Route::get('get-trx-by-warehouse/{warehouse}/{startDate}/{endDate}', [TransactionController::class, 'getTrxByWarehouse']);

    //Finance
    Route::apiResource('finance', FinanceController::class);
    Route::get('finance-by-type/{contact}/{financeType}', [FinanceController::class, 'getFinanceByType']);
    Route::get('get-finance-by-contact-id/{contactId}', [FinanceController::class, 'getFinanceByContactId']);
});
