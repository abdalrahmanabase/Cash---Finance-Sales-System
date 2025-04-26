<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class InstallmentCalculator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static string $view = 'filament.pages.installment-calculator';
    protected static ?string $navigationLabel = 'Installment Calculator';
    protected static ?string $title = 'Installment Calculator';
    protected static ?int $navigationSort = 1; 

    public $productPrice = null;
public $downpayment = null;
public $interest = null;
public $months = null;


}
