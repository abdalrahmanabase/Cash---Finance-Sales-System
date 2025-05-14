<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_type',
        'status',  
        'client_id',
        'total_price',
        'discount',
        'down_payment',
        'final_price',
        'interest_rate',
        'interest_amount', 
        'months_count',
        'monthly_installment',
        'paid_amount',
        'remaining_amount',
        'payment_dates',
        'payment_amounts',
        'notes'
    ];

    protected $casts = [
        'payment_dates' => 'array',
        'payment_amounts' => 'array',
        'down_payment' => 'float',
        'final_price' => 'float',
        'total_price' => 'float',
        'discount' => 'float',
        'interest_rate' => 'float',
        'interest_amount' => 'float',
        'monthly_installment' => 'float',
        'paid_amount' => 'float',
        'remaining_amount' => 'float',
        'months_count' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function getAllPaymentsAttribute()
    {
        $dates = $this->payment_dates ?? [];
        $amounts = $this->payment_amounts ?? [];
        $payments = [];
        foreach ($dates as $i => $date) {
            $payments[] = [
                'date' => $date,
                'amount' => $amounts[$i] ?? 0,
            ];
        }
        return $payments;
    }

    public function getPaidAmountAttribute()
    {
        // Always sum payment_amounts for paid_amount
        return array_sum($this->payment_amounts ?? []);
    }

    public function getRemainingMonthsAttribute()
    {
        $paid = is_array($this->payment_amounts) ? count($this->payment_amounts) : 0;
        return max(0, ($this->months_count ?? 0) - $paid);
    }

    public function getNextPaymentDateAttribute()
    {
        $dates = $this->payment_dates ?? [];
        if (is_array($dates) && count($dates) > 0) {
            $last = end($dates);
            return \Carbon\Carbon::parse($last)->addMonth()->format('d-m-Y');
        }
        // If no payments, use created_at + 1 month
        return $this->created_at ? $this->created_at->copy()->addMonth()->format('d-m-Y') : null;
    }

    public function deductStock(): void
    {
        foreach ($this->items as $item) {
            $item->product->decrement('stock', $item->quantity);
        }
    }

    public function restockProducts(): void
    {
        foreach ($this->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }
    }

     protected static function booted()
    {
        static::creating(function ($sale) {
            $downPayment = floatval($sale->down_payment ?? 0);

            // Initialize payments array
            $sale->payment_dates = [];
            $sale->payment_amounts = [];

            // Add down payment as separate entry if present
            if ($downPayment > 0) {
                $sale->payment_dates[] = now()->format('Y-m-d');
                $sale->payment_amounts[] = $downPayment;
            }

            // Calculate remaining amounts
            $finalPrice = floatval($sale->final_price ?? 0);
            $interestAmount = floatval($sale->interest_amount ?? 0);
            $sale->paid_amount = array_sum($sale->payment_amounts);
            $sale->remaining_amount = ($finalPrice + $interestAmount) - $sale->paid_amount;
        });
    }

    /**
     * Record a new payment for this sale
     */
    public function recordPayment(float $amount, string $date): bool
    {
        try {
            // Get current payment arrays
            $dates = $this->payment_dates ?? [];
            $amounts = $this->payment_amounts ?? [];

            // Add new payment
            $dates[] = $date;
            $amounts[] = $amount;

            // Calculate new totals
            $newPaidAmount = array_sum($amounts);
            $newRemainingAmount = ($this->final_price + ($this->interest_amount ?? 0)) - $newPaidAmount;

            // Update the sale
            $this->update([
                'payment_dates' => $dates,
                'payment_amounts' => $amounts,
                'paid_amount' => $newPaidAmount,
                'remaining_amount' => $newRemainingAmount,
                'status' => $newRemainingAmount <= 0 ? 'completed' : 'ongoing',
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to record payment:', [
                'sale_id' => $this->id,
                'amount' => $amount,
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get payment status information
     */
    public function getPaymentStatus(): array
    {
        $totalAmount = $this->final_price + ($this->interest_amount ?? 0);
        $paidAmount = $this->paid_amount ?? 0;
        $remainingAmount = $this->remaining_amount ?? ($totalAmount - $paidAmount);
        $progress = $totalAmount > 0 ? ($paidAmount / $totalAmount) * 100 : 0;

        return [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'progress_percentage' => round($progress, 2),
            'is_completed' => $this->status === 'completed',
            'remaining_months' => $this->remaining_months,
            'next_payment_date' => $this->next_payment_date,
        ];
    }

    /**
     * Scope for active installment sales
     */
    public function scopeActiveInstallments($query)
    {
        return $query->where('sale_type', 'installment')
                    ->where('status', 'ongoing');
    }

    /**
     * Check if payment is overdue
     */
    public function isPaymentOverdue(): bool
    {
        if ($this->status === 'completed') {
            return false;
        }

        $lastPaymentDate = end($this->payment_dates) 
            ? \Carbon\Carbon::parse(end($this->payment_dates))
            : $this->created_at;

        return $lastPaymentDate->addMonth()->isPast();
    }

    /**
     * Get the latest payment
     */
    public function getLatestPayment(): ?array
    {
        if (empty($this->payment_dates) || empty($this->payment_amounts)) {
            return null;
        }

        $dates = $this->payment_dates;
        $amounts = $this->payment_amounts;
        $lastIndex = count($dates) - 1;

        return [
            'date' => $dates[$lastIndex],
            'amount' => $amounts[$lastIndex],
        ];
    }
}
