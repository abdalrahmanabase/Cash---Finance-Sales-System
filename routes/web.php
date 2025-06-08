<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Pages\ClientInstallmentPayments;



Route::get(
    '/client-installment-payments/{client_id?}',
    ClientInstallmentPayments::class
)->name('filament.pages.client-installment-payments');






Route::get('lang/{locale}', function ($locale) {
    if (in_array($locale, ['en','ar'])) {
        session(['locale' => $locale]);
    }

    // redirect()->back();  <-- remove this
    return redirect('/');
})->name('lang.switch');
