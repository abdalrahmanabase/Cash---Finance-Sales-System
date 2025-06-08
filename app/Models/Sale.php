<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
        'notes',
        'preferred_payment_day',
        'next_payment_date',
    ];

    protected $casts = [
        'payment_dates'       => 'array',
        'payment_amounts'     => 'array',
        'down_payment'        => 'float',
        'final_price'         => 'float',
        'total_price'         => 'float',
        'discount'            => 'float',
        'interest_rate'       => 'float',
        'interest_amount'     => 'float',
        'monthly_installment' => 'float',
        'remaining_amount'    => 'float',
        'months_count'        => 'integer',
        'next_payment_date'   => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
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

    public function getAllPaymentsAttribute()
    {
        $dates   = $this->payment_dates   ?? [];
        $amounts = $this->payment_amounts ?? [];
        $payments = [];

        foreach ($dates as $index => $date) {
            $payments[] = [
                'date'   => $date,
                'amount' => $amounts[$index] ?? 0,
            ];
        }

        usort($payments, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
        return $payments;
    }

    public function getPaidAmountAttribute(): float
    {
        return array_sum($this->payment_amounts ?? []);
    }

    public function getRemainingMonthsAttribute(): int
    {
        $progress = $this->getPaymentScheduleProgress();
        return max(0, ($this->months_count ?? 0) - $progress['fully_paid_months']);
    }

    public function getNextPaymentDateAttribute($value)
    {
        if ($value) {
            return Carbon::parse($value);
        }

        if ($this->status === 'completed') {
            return null;
        }

        $progress = $this->getPaymentScheduleProgress();
        $baseDate = $this->created_at
            ? Carbon::parse($this->created_at)
            : Carbon::now();
        $preferredDay = $this->preferred_payment_day;
        if (!is_numeric($preferredDay) || $preferredDay < 1 || $preferredDay > 31) {
            $preferredDay = $baseDate->day;
        }
        $preferredDay = (int)$preferredDay;

        $nextPaymentDate = $baseDate->copy()
            ->addMonthsNoOverflow($progress['fully_paid_months'] + 1)
            ->setDay(
                min(
                    $preferredDay,
                    $baseDate->copy()
                             ->addMonthsNoOverflow($progress['fully_paid_months'] + 1)
                             ->daysInMonth
                )
            );

        return $nextPaymentDate;
    }

    public function recordPayment(float $amount, string $date): bool
    {
        try {
            if ($amount <= 0) {
                throw new \Exception("Payment amount must be positive");
            }
            if ($this->status === 'completed') {
                throw new \Exception("Cannot record payment – sale already completed");
            }

            // Add payment to arrays
            $dates   = $this->payment_dates   ?? [];
            $amounts = $this->payment_amounts ?? [];
            $paymentDate = Carbon::parse($date)->format('Y-m-d');
            $dates[]   = $paymentDate;
            $amounts[] = $amount;

            // Calculate totals
            $totalAmount = $this->final_price + ($this->interest_amount ?? 0);
            $downPayment = $this->down_payment ?? 0;
            $monthlyPayments = array_sum($amounts);
            $newRemainingAmount = max(0, $totalAmount - $downPayment - $monthlyPayments);

            if ($monthlyPayments > ($totalAmount - $downPayment)) {
                throw new \Exception("Payment amount exceeds remaining balance");
            }

            $isCompleted = round($newRemainingAmount, 2) <= 0;

            // Save values
            $this->payment_dates    = $dates;
            $this->payment_amounts  = $amounts;
            $this->remaining_amount = $newRemainingAmount;
            $this->status           = $isCompleted ? 'completed' : 'ongoing';

            $this->save();
            // 🚩 Always refresh after save to reload next_payment_date from DB
            $this->refresh();

            return true;
        } catch (\Exception $e) {
            Log::error('Payment recording failed', [
                'sale_id' => $this->id,
                'error'   => $e->getMessage(),
                'amount'  => $amount,
                'date'    => $date,
            ]);
            return false;
        }
    }

    public function getPaymentScheduleProgress(): array
    {
        $monthlyInstallment = $this->monthly_installment ?? 0;
        $downPayment        = $this->down_payment ?? 0;
        $monthsCount        = $this->months_count ?? 0;
        $allPayments        = $this->all_payments;

        $totalPaid = array_sum(array_column($allPayments, 'amount'));
        $paidTowardInstallments = max(0, $totalPaid - $downPayment);

        usort($allPayments, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
        $fullyPaidMonths  = 0;
        $remainingPayment = 0;

        foreach ($allPayments as $payment) {
            $remainingPayment += $payment['amount'];
            while ($remainingPayment >= $monthlyInstallment && $fullyPaidMonths < $monthsCount) {
                $remainingPayment -= $monthlyInstallment;
                $fullyPaidMonths++;
            }
        }

        $lastPaymentDate = !empty($allPayments)
            ? end($allPayments)['date']
            : ($this->created_at ? $this->created_at->format('d-m-Y') : null);

        return [
            'fully_paid_months'          => $fullyPaidMonths,
            'remaining_balance'          => $remainingPayment,
            'last_payment_date'          => $lastPaymentDate,
            'next_payment_due'           => ($fullyPaidMonths < $monthsCount)
                                            ? max(0, $monthlyInstallment - $remainingPayment)
                                            : 0,
            'total_paid'                 => $totalPaid,
            'paid_toward_installments'   => $paidTowardInstallments,
            'total_months'               => $monthsCount,
        ];
    }

    public function getCurrentMonthDueAttribute(): float
    {
        $progress = $this->getPaymentScheduleProgress();
        return $progress['next_payment_due'];
    }

    public function getPaymentStatus(): array
    {
        $totalAmount     = $this->final_price + ($this->interest_amount ?? 0);
        $paidAmount      = $this->getPaidAmountAttribute();
        $remainingAmount = $this->remaining_amount ?? max(0, $totalAmount - $paidAmount);
        $progressPct     = $totalAmount > 0 ? ($paidAmount / $totalAmount) * 100 : 0;
        $scheduleProg    = $this->getPaymentScheduleProgress();

        return [
            'total_amount'        => $totalAmount,
            'paid_amount'         => $paidAmount,
            'remaining_amount'    => $remainingAmount,
            'progress_percentage' => round($progressPct, 2),
            'is_completed'        => $this->status === 'completed',
            'fully_paid_months'   => $scheduleProg['fully_paid_months'],
            'remaining_months'    => $this->remaining_months,
            'next_payment_date'   => $this->next_payment_date,
            'next_payment_due'    => $scheduleProg['next_payment_due'],
            'last_payment_date'   => $scheduleProg['last_payment_date'],
        ];
    }

    public function scopeActiveInstallments($query)
    {
        return $query
            ->where('sale_type', 'installment')
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

        return $nextPaymentDate->isPast();
    }

    public function getLatestPayment(): ?array
    {
        $allPayments = $this->all_payments;
        return !empty($allPayments) ? end($allPayments) : null;
    }

    public function updatePayment($paymentIndex, $amount, $date)
    {
        $payments = $this->payment_amounts ?? [];
        $dates    = $this->payment_dates   ?? [];
        if (!isset($payments[$paymentIndex])) {
            return false;
        }

        $payments[$paymentIndex] = $amount;
        $dates[$paymentIndex]    = $date;

        $this->payment_amounts = $payments;
        $this->payment_dates   = $dates;
        $this->save();

        // saving() hook will recompute next_payment_date
        return $this->recalculatePaymentStatus();
    }

    public function deletePayment($paymentIndex)
    {
        $payments = $this->payment_amounts ?? [];
        $dates    = $this->payment_dates   ?? [];
        if (!isset($payments[$paymentIndex])) {
            return false;
        }

        array_splice($payments, $paymentIndex, 1);
        array_splice($dates,    $paymentIndex, 1);

        $this->payment_amounts = $payments;
        $this->payment_dates   = $dates;
        $this->save();

        return $this->recalculatePaymentStatus();
    }

    public function recalculatePaymentStatus(): bool
    {
        $totalAmount     = $this->final_price + ($this->interest_amount ?? 0);
        $downPayment     = $this->down_payment ?? 0;
        $monthlyPayments = array_sum($this->payment_amounts ?? []);
        $this->remaining_amount = max(0, $totalAmount - $downPayment - $monthlyPayments);
        $this->status = (round($this->remaining_amount, 2) <= 0) ? 'completed' : 'ongoing';
        return $this->save();
    }

    public function getTotalCostAttribute(): float
    {
        return $this->items->sum(fn($item) =>
            $item->quantity * ($item->product->purchase_price ?? 0)
        );
    }

    public function getProfitAttribute(): float
    {
        return ($this->final_price + ($this->interest_amount ?? 0))
               - $this->total_cost
               - ($this->discount ?? 0);
    }

    public function getDynamicStatusAttribute(): string
    {
        if ($this->status === 'completed') {
            return 'completed';
        }

        if ($this->isPaymentOverdue()) {
            $daysLate = Carbon::now()->diffInDays(
                Carbon::createFromFormat('d-m-Y', $this->next_payment_date),
                false
            );
            return $daysLate <= -3 ? 'danger' : 'orange';
        }

        if ($this->remaining_amount > 0
            && $this->remaining_amount < $this->monthly_installment
        ) {
            return 'Partial (' . __('Currency') . ' ' . number_format($this->current_month_due, 2) . ' due)';
        }

        return 'success';
    }

    public function getPaymentsGroupedByMonth(): array
    {
        $payments = $this->all_payments;
        $grouped  = [];

        foreach ($payments as $p) {
            $monthKey = Carbon::parse($p['date'])->format('Y-m');
            if (!isset($grouped[$monthKey])) {
                $grouped[$monthKey] = [
                    'amount'  => 0,
                    'profit'  => 0,
                    'capital' => 0,
                ];
            }
            $grouped[$monthKey]['amount'] += $p['amount'];

            if ($this->final_price > 0) {
                $profitTotal  = $this->profit;
                $capitalTotal = $this->total_cost;

                $grouped[$monthKey]['profit']  += ($p['amount'] / $this->final_price) * $profitTotal;
                $grouped[$monthKey]['capital'] += ($p['amount'] / $this->final_price) * $capitalTotal;
            }
        }

        return $grouped;
    }

    public function getScheduledPaymentDateForMonth(string $targetMonth): ?string
    {
        $baseDate = $this->created_at ?? now();
        $preferredDay = $this->preferred_payment_day;
        if (!is_numeric($preferredDay) || $preferredDay < 1 || $preferredDay > 31) {
            $preferredDay = $baseDate->day;
        }
        $preferredDay = (int)$preferredDay;

        for ($i = 0; $i < $this->months_count; $i++) {
            $d = Carbon::parse($baseDate)
                ->addMonthsNoOverflow($i)
                ->setDay(min(
                    $preferredDay,
                    Carbon::parse($baseDate)
                          ->addMonthsNoOverflow($i)
                          ->daysInMonth
                ));

            if ($d->format('Y-m') === $targetMonth) {
                return $d->format('d-m-Y');
            }
        }

        return null;
    }

    public function getCapitalPaidForMonth(string $month): float
    {
        $payments = $this->getPaymentsForMonth($month);
        $total    = 0;

        foreach ($payments as $p) {
            if ($this->final_price > 0) {
                $total += ($p['amount'] / $this->final_price) * $this->total_cost;
            }
        }

        return round($total, 2);
    }

    public function getPaymentsForMonth(string $month): array
    {
        return array_filter($this->all_payments, fn($p) =>
            Carbon::parse($p['date'])->format('Y-m') === $month
        );
    }

    protected static function booted()
    {
        static::saving(function (Sale $sale) {
            $paidAmounts = array_sum($sale->payment_amounts ?? []);
            $monthly     = $sale->monthly_installment ?? 0;
            $monthsCount = $sale->months_count ?? 0;

            $netPaid = max(0, $paidAmounts );
            $fullyPaidMonths = 0;
            $carry = $netPaid;
            while ($carry >= $monthly && $fullyPaidMonths < $monthsCount) {
                $carry -= $monthly;
                $fullyPaidMonths++;
            }

            if ($fullyPaidMonths >= $monthsCount) {
                $sale->status = 'completed';
                $sale->remaining_amount = 0;
                $sale->next_payment_date = null;
                return;
            }

            $baseDate = $sale->created_at ? Carbon::parse($sale->created_at) : Carbon::now();
            $preferredDay = $sale->preferred_payment_day;
            if (!is_numeric($preferredDay) || $preferredDay < 1 || $preferredDay > 31) {
                $preferredDay = $baseDate->day;
            }
            $preferredDay = (int)$preferredDay;

            // Always add number of full paid months to base date!
            $nextMonth = $baseDate->copy()->addMonthsNoOverflow($fullyPaidMonths + 1);
            $dayInMonth = min($preferredDay, $nextMonth->daysInMonth);
            $calcNext = $nextMonth->copy()->setDay($dayInMonth);

            $sale->next_payment_date = $calcNext->format('Y-m-d');
        });
    }

    public function getProfitOnDueAmount(): float
{
    $due = $this->getPaymentScheduleProgress()['next_payment_due'];

    $monthsCount = max($this->months_count, 1);

    $remainingCapital = max(0, $this->total_cost - $this->down_payment);
    $monthlyCapital   = $remainingCapital / $monthsCount;

    $totalProfit = ($this->final_price + ($this->interest_amount ?? 0)) - $this->total_cost;
    $monthlyProfit = $totalProfit / $monthsCount;

    $monthlyTotal = $monthlyCapital + $monthlyProfit;

    if ($monthlyTotal <= 0) {
        return 0;
    }

    $portion = $due / $monthlyTotal;

    $profitDue = $monthlyProfit * $portion;

    return round($profitDue, 2);
}

public function getCapitalOnDueAmount(): float
{
    $due = $this->getPaymentScheduleProgress()['next_payment_due'];

    $monthsCount = max($this->months_count, 1);

    $remainingCapital = max(0, $this->total_cost - $this->down_payment);
    $monthlyCapital   = $remainingCapital / $monthsCount;

    $totalProfit = ($this->final_price + ($this->interest_amount ?? 0)) - $this->total_cost;
    $monthlyProfit = $totalProfit / $monthsCount;

    $monthlyTotal = $monthlyCapital + $monthlyProfit;

    if ($monthlyTotal <= 0) {
        return 0;
    }

    $portion = $due / $monthlyTotal;

    $capitalDue = $monthlyCapital * $portion;

    return round($capitalDue, 2);
}


    public function getLatestPaymentAttribute()
    {
        return $this->getLatestPayment();
    }
}
