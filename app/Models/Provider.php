<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'notes',
    ];

    protected $appends = ['total_debt'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function bills()
    {
        return $this->hasMany(ProviderBill::class);
    }

    public function payments()
    {
        return $this->hasMany(ProviderPayment::class);
    }

    public function getTotalDebtAttribute()
    {
        $billDebt = $this->bills->sum(fn($bill) => ($bill->total_amount - $bill->amount_paid));
        $extraPayments = $this->payments->sum('amount');
        return $billDebt - $extraPayments;
    }
}