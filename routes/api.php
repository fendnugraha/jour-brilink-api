<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\ProductCategoryController;

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('accounts', ChartOfAccountController::class);
    Route::get('get-cash-and-bank', [ChartOfAccountController::class, 'getCashAndBank']);
    Route::apiResource('category-accounts', AccountController::class);
    Route::delete('delete-selected-account', [ChartOfAccountController::class, 'deleteAll']);
    Route::put('warehouse/{warehouse}/add-cash-bank/{id}', [ChartOfAccountController::class, 'addCashAndBankToWarehouse']);

    Route::apiResource('products', ProductController::class);
    Route::apiResource('product-categories', ProductCategoryController::class);

    Route::apiResource('warehouse', WarehouseController::class);
});
