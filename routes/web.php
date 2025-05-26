<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Pages\ClientInstallmentPayments;

Route::view('/', 'welcome');

Route::get('/client-installment-payments', ClientInstallmentPayments::class)
    ->name('filament.pages.client-installment-payments');




