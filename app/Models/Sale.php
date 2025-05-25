<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'sale_type',
        'total_price',
        'discount',
        'interest_rate',
        'interest_amount',
        'final_price',
        'down_payment',
        'monthly_installment',
        'remaining_amount',
        'months_count',
        'payment_dates',
        'payment_amounts',
        'status',
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
        
        // Sort payments by date
        usort($payments, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        return $payments;
    }

    public function getPaidAmountAttribute()
    {
        return array_sum($this->payment_amounts ?? []);
    }

    public function getRemainingMonthsAttribute()
    {
        $progress = $this->getPaymentScheduleProgress();
        return max(0, ($this->months_count ?? 0) - $progress['fully_paid_months']);
    }

    public function getNextPaymentDateAttribute()
    {
        if ($this->status === 'completed') {
            return 'Ended';
        }

        

        $progress = $this->getPaymentScheduleProgress();
        $baseDate = $this->created_at ? Carbon::parse($this->created_at) : now();
        
        return $baseDate->copy()
            ->addMonths($progress['fully_paid_months'] + 1)
            ->format('d-m-Y');
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

    public function recordPayment(float $amount, string $date): bool
    {
        try {
            if ($amount <= 0) {
                throw new \Exception("Payment amount must be positive");
            }

            if ($this->status === 'completed') {
                throw new \Exception("Cannot record payment - sale is already completed");
            }

            $totalAmount = $this->final_price + ($this->interest_amount ?? 0);
            $currentPaid = $this->getPaidAmountAttribute();
            
            if (($currentPaid + $amount) > $totalAmount) {
                throw new \Exception("Payment amount exceeds remaining balance");
            }

            $dates = $this->payment_dates ?? [];
            $amounts = $this->payment_amounts ?? [];

            $paymentDate = Carbon::parse($date)->format('Y-m-d');
            $dates[] = $paymentDate;
            $amounts[] = $amount;

            $newPaidAmount = $currentPaid + $amount;
            $newRemainingAmount = max(0, $totalAmount - $newPaidAmount);
            $isCompleted = $newRemainingAmount <= 0.01;

            $this->update([
                'payment_dates' => $dates,
                'payment_amounts' => $amounts,
                'remaining_amount' => $newRemainingAmount,
                'status' => $isCompleted ? 'completed' : 'ongoing',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Payment recording failed', [
                'sale_id' => $this->id,
                'error' => $e->getMessage(),
                'amount' => $amount,
                'date' => $date
            ]);
            return false;
        }
    }

   public function getPaymentScheduleProgress(): array
{
    $monthlyInstallment = $this->monthly_installment ?? 0;
    $downPayment = $this->down_payment ?? 0;
    $monthsCount = $this->months_count ?? 0;
    $allPayments = $this->getAllPaymentsAttribute();
    
    // Calculate total paid including all payments
    $totalPaid = array_sum(array_column($allPayments, 'amount'));
    
    // Calculate amount paid toward installments (excluding down payment)
    $paidTowardInstallments = max(0, $totalPaid - $downPayment);
    
    // Sort payments chronologically
    usort($allPayments, function ($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    $fullyPaidMonths = 0;
    $remainingPayment = 0;
    
    // Process payments after the first two
    $paymentsToCount = array_slice($allPayments, 0);
    
    foreach ($paymentsToCount as $payment) {
        $remainingPayment += $payment['amount'];
        
        // Count how many full months this payment covers
        while ($remainingPayment >= $monthlyInstallment && $fullyPaidMonths < $monthsCount) {
            $remainingPayment -= $monthlyInstallment;
            $fullyPaidMonths++;
        }
    }
    
    $lastPaymentDate = !empty($allPayments) 
        ? end($allPayments)['date'] 
        : ($this->created_at ? $this->created_at->format('d-m-Y') : null);
    
    return [
        'fully_paid_months' => $fullyPaidMonths,
        'remaining_balance' => $remainingPayment,
        'last_payment_date' => $lastPaymentDate,
        'next_payment_due' => ($fullyPaidMonths < $monthsCount) 
            ? max(0, $monthlyInstallment - $remainingPayment)
            : 0,
        'total_paid' => $totalPaid,
        'paid_toward_installments' => $paidTowardInstallments,
        'total_months' => $monthsCount,
    ];
}
    public function getCurrentMonthDueAttribute()
    {
        $progress = $this->getPaymentScheduleProgress();
        return $progress['next_payment_due'];
    }

    public function getPaymentStatus(): array
    {
        $totalAmount = $this->final_price + ($this->interest_amount ?? 0);
        $paidAmount = $this->getPaidAmountAttribute();
        $remainingAmount = $this->remaining_amount ?? max(0, $totalAmount - $paidAmount);
        $progress = $totalAmount > 0 ? ($paidAmount / $totalAmount) * 100 : 0;
        $paymentProgress = $this->getPaymentScheduleProgress();

        return [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'progress_percentage' => round($progress, 2),
            'is_completed' => $this->status === 'completed',
            'fully_paid_months' => $paymentProgress['fully_paid_months'],
            'remaining_months' => $this->remaining_months,
            'next_payment_date' => $this->next_payment_date,
            'next_payment_due' => $paymentProgress['next_payment_due'],
            'last_payment_date' => $paymentProgress['last_payment_date'],
        ];
    }

    public function scopeActiveInstallments($query)
    {
        return $query->where('sale_type', 'installment')
                    ->where('status', 'ongoing');
    }

    public function isPaymentOverdue(): bool
    {
        if ($this->status === 'completed') {
            return false;
        }

        $nextPaymentDate = $this->next_payment_date;
        if (!$nextPaymentDate) {
            return false;
        }

        return Carbon::parse($nextPaymentDate)->isPast();
    }

    public function getLatestPayment(): ?array
    {
        $allPayments = $this->getAllPaymentsAttribute();
        return !empty($allPayments) ? end($allPayments) : null;
    }

    // In your Sale model
public function updatePayment($paymentIndex, $amount, $date)
{
    $payments = $this->payment_amounts ?? [];
    $dates = $this->payment_dates ?? [];
    
    if (!isset($payments[$paymentIndex])) {
        return false;
    }
    
    $payments[$paymentIndex] = $amount;
    $dates[$paymentIndex] = $date;
    
    $this->payment_amounts = $payments;
    $this->payment_dates = $dates;
    
    return $this->recalculatePaymentStatus();
}

public function deletePayment($paymentIndex)
{
    $payments = $this->payment_amounts ?? [];
    $dates = $this->payment_dates ?? [];
    
    if (!isset($payments[$paymentIndex])) {
        return false;
    }
    
    array_splice($payments, $paymentIndex, 1);
    array_splice($dates, $paymentIndex, 1);
    
    $this->payment_amounts = $payments;
    $this->payment_dates = $dates;
    
    return $this->recalculatePaymentStatus();
}

protected function recalculatePaymentStatus()
{
    $totalPaid = array_sum($this->payment_amounts ?? []);
    $totalAmount = $this->final_price + ($this->interest_amount ?? 0);

    $this->remaining_amount = max($totalAmount - $totalPaid, 0);

    // Update status based on remaining amount
    $this->status = ($this->remaining_amount <= 0.01) ? 'completed' : 'ongoing';

    // Save changes
    return $this->save();
}

//for the summary page
public function getTotalCostAttribute()
{
    return $this->items->sum(fn($item) =>
        $item->quantity * ($item->product->purchase_price ?? 0)
    );
}

public function getProfitAttribute()
{
    return $this->final_price - $this->total_cost;
}


}