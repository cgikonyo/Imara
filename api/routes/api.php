<?php

use App\Http\Controllers\Api\RepaymentController;
use App\Http\Controllers\Api\AdminDisbursementController;
use App\Http\Controllers\Api\AdminLoanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LoanProductController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MpesaCallbackController;
use Illuminate\Support\Facades\Route;

Route::apiResource('loan-products', LoanProductController::class);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/mpesa/callback', [MpesaCallbackController::class, 'handle']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function(){
    Route::post('/logout',[AuthController::class,'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/loans',[LoanController::class, 'index']);
    Route::get('/loans/{loan}', [LoanController::class, 'show']);
    Route::post('/loans', [LoanController::class, 'store']);

    Route::post('/repayments', [RepaymentController::class, 'store']);
    Route::post('/repayments/mpesa', [RepaymentController::class, 'initiateMpesa']);
    Route::post('/repayments/mpesa/{transaction}/reconcile', [RepaymentController::class, 'reconcileMpesa']);

    Route::middleware('admin')->group(function(){
        Route::post('/admin/loans/{loan}/approve', [AdminLoanController::class, 'approve']);
        Route::post('/admin/loans/{loan}/reject', [AdminLoanController::class, 'reject']);
        Route::post('/admin/loans/{loan}/disburse',[AdminDisbursementController::class, 'disburse']);
    });
});
