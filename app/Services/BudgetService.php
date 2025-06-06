<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    /**
     * Create a new budget.
     */
    public function createBudget(array $data): Budget
    {
        return DB::transaction(function () use ($data) {
            $budget = Budget::create($data);
            $budget->updateSpentAmount();
            return $budget->fresh(['category']);
        });
    }

    /**
     * Update an existing budget.
     */
    public function updateBudget(Budget $budget, array $data): Budget
    {
        return DB::transaction(function () use ($budget, $data) {
            $budget->update($data);
            $budget->updateSpentAmount();
            return $budget->fresh(['category']);
        });
    }

    /**
     * Get budget statistics for a user.
     */
    public function getBudgetStatistics(int $userId): array
    {
        $budgets = Budget::where('user_id', $userId)
            ->where('status', 'active')
            ->get();

        return [
            'total_budgets' => $budgets->count(),
            'total_budgeted' => $budgets->sum('amount'),
            'total_spent' => $budgets->sum('spent_amount'),
            'exceeded_budgets' => $budgets->where('is_exceeded', true)->count(),
            'near_limit_budgets' => $budgets->filter(function ($budget) {
                return $budget->spent_percentage >= $budget->notification_threshold && !$budget->is_exceeded;
            })->count(),
        ];
    }

    /**
     * Get budget performance data.
     */
    public function getBudgetPerformance(Budget $budget): array
    {
        $transactions = $budget->transactions()->orderBy('transaction_date')->get();
        
        $dailySpending = [];
        $runningTotal = 0;
        
        foreach ($transactions as $transaction) {
            $date = $transaction->transaction_date->format('Y-m-d');
            if (!isset($dailySpending[$date])) {
                $dailySpending[$date] = 0;
            }
            $dailySpending[$date] += $transaction->amount;
            $runningTotal += $transaction->amount;
        }

        return [
            'daily_spending' => $dailySpending,
            'spending_trend' => $this->calculateSpendingTrend($budget),
            'projected_spending' => $this->calculateProjectedSpending($budget),
        ];
    }

    /**
     * Calculate spending trend.
     */
    private function calculateSpendingTrend(Budget $budget): string
    {
        $totalDays = $budget->start_date->diffInDays($budget->end_date) + 1;
        $elapsedDays = $budget->start_date->diffInDays(now()) + 1;
        
        if ($elapsedDays <= 0) {
            return 'not_started';
        }
        
        $expectedSpentPercentage = ($elapsedDays / $totalDays) * 100;
        $actualSpentPercentage = $budget->spent_percentage;
        
        if ($actualSpentPercentage > $expectedSpentPercentage * 1.2) {
            return 'overspending';
        } elseif ($actualSpentPercentage < $expectedSpentPercentage * 0.8) {
            return 'underspending';
        } else {
            return 'on_track';
        }
    }

    /**
     * Calculate projected spending.
     */
    private function calculateProjectedSpending(Budget $budget): float
    {
        $totalDays = $budget->start_date->diffInDays($budget->end_date) + 1;
        $elapsedDays = $budget->start_date->diffInDays(now()) + 1;
        
        if ($elapsedDays <= 0 || $elapsedDays >= $totalDays) {
            return $budget->spent_amount;
        }
        
        $dailyAverage = $budget->spent_amount / $elapsedDays;
        return $dailyAverage * $totalDays;
    }

    /**
     * Update all active budgets' spent amounts.
     */
    public function updateAllBudgetSpentAmounts(): void
    {
        Budget::where('status', 'active')
            ->chunk(100, function ($budgets) {
                foreach ($budgets as $budget) {
                    $budget->updateSpentAmount();
                }
            });
    }

    /**
     * Get budgets that need notifications.
     */
    public function getBudgetsNeedingNotification(): Collection
    {
        return Budget::where('status', 'active')
            ->where('send_notifications', true)
            ->get()
            ->filter(function ($budget) {
                return $budget->shouldSendNotification();
            });
    }

    /**
     * Create recurring budgets.
     */
    public function createRecurringBudgets(): void
    {
        $completedBudgets = Budget::where('status', 'completed')
            ->where('end_date', '<', now())
            ->get();

        foreach ($completedBudgets as $budget) {
            $this->createNextPeriodBudget($budget);
        }
    }

    /**
     * Create next period budget based on the completed one.
     */
    private function createNextPeriodBudget(Budget $budget): void
    {
        $nextStartDate = $this->getNextPeriodStartDate($budget);
        $nextEndDate = $this->getNextPeriodEndDate($budget, $nextStartDate);

        Budget::create([
            'user_id' => $budget->user_id,
            'category_id' => $budget->category_id,
            'name' => $budget->name,
            'description' => $budget->description,
            'amount' => $budget->amount,
            'period' => $budget->period,
            'start_date' => $nextStartDate,
            'end_date' => $nextEndDate,
            'send_notifications' => $budget->send_notifications,
            'notification_threshold' => $budget->notification_threshold,
            'color' => $budget->color,
        ]);
    }

    /**
     * Get next period start date.
     */
    private function getNextPeriodStartDate(Budget $budget): Carbon
    {
        return match ($budget->period) {
            'daily' => $budget->end_date->addDay(),
            'weekly' => $budget->end_date->addDay(),
            'monthly' => $budget->end_date->addDay(),
            'quarterly' => $budget->end_date->addDay(),
            'yearly' => $budget->end_date->addDay(),
            default => $budget->end_date->addDay(),
        };
    }

    /**
     * Get next period end date.
     */
    private function getNextPeriodEndDate(Budget $budget, Carbon $startDate): Carbon
    {
        return match ($budget->period) {
            'daily' => $startDate->copy()->endOfDay(),
            'weekly' => $startDate->copy()->addWeek()->subDay(),
            'monthly' => $startDate->copy()->addMonth()->subDay(),
            'quarterly' => $startDate->copy()->addQuarter()->subDay(),
            'yearly' => $startDate->copy()->addYear()->subDay(),
            default => $startDate->copy()->addMonth()->subDay(),
        };
    }
}