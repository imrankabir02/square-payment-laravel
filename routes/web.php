<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::get('/payment', [PaymentController::class, 'showPaymentForm'])->name('payment.form');
Route::post('/process-payment', [PaymentController::class, 'processPayment'])->name('payment.process');

Route::get('/', function () {
    return view('welcome');
});
