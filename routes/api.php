<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\ProductCategoryController;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('role.warehouse');
    });

    Route::apiResource('users', UserController::class);
    Route::get('get-all-users', [UserController::class, 'getAllUsers']);
    Route::put('users/{user}/update-password', [UserController::class, 'updatePassword']);

    Route::apiResource('accounts', ChartOfAccountController::class);
    Route::get('get-cash-and-bank', [ChartOfAccountController::class, 'getCashAndBank']);
    Route::apiResource('category-accounts', AccountController::class);
    Route::delete('delete-selected-account', [ChartOfAccountController::class, 'deleteAll']);
    Route::put('warehouse/{warehouse}/add-cash-bank/{id}', [ChartOfAccountController::class, 'addCashAndBankToWarehouse']);
    Route::get('get-cash-bank-by-warehouse/{warehouse}', [ChartOfAccountController::class, 'getCashAndBankByWarehouse']);
    Route::get('get-expenses', [ChartOfAccountController::class, 'getExpenses']);

    Route::apiResource('products', ProductController::class);
    Route::apiResource('product-categories', ProductCategoryController::class);
    Route::get('get-all-products', [ProductController::class, 'getAllProducts']);

    Route::apiResource('contacts', ContactController::class);

    Route::apiResource('warehouse', WarehouseController::class);
    Route::get('get-all-warehouses', [WarehouseController::class, 'getAllWarehouses']);

    //journals
    Route::apiResource('journals', JournalController::class);
    Route::post('create-transfer', [JournalController::class, 'createTransfer']);
    Route::post('create-voucher', [JournalController::class, 'createVoucher']);
    Route::post('create-deposit', [JournalController::class, 'createDeposit']);
    Route::post('create-mutation', [JournalController::class, 'createMutation']);
});
