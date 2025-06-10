<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Notification;
use App\Models\User;
use App\Models\Budget;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExpenseNotificationService
{
    /**
     * Check all users for expense-related notifications
     */
    public function checkExpenseNotifications()
    {
        $users = User::all();

        foreach ($users as $user) {
            $this->checkExpenseThresholds($user);
        }
    }

    /**
     * Check individual user's expenses against various thresholds
     */
    public function checkExpenseThresholds(User $user)
    {
        // Check monthly expense patterns
        $this->checkMonthlyExpensePatterns($user);
        
        // Check for unusual spending spikes
        $this->checkSpendingSpikes($user);
        
        // Check expense budget warnings
        $this->checkBudgetWarnings($user);
        
        // Check for high-frequency spending
        $this->checkHighFrequencySpending($user);
    }

    /**
     * Check monthly expense patterns and milestones
     */
    protected function checkMonthlyExpensePatterns(User $user)
    {
        $currentMonth = now()->format('Y-m');
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // Get user's accounts
        $accountIds = $user->accounts()->pluck('id')->toArray();
        if (empty($accountIds)) {
            return;
        }

        // Calculate total expenses for current month
        $monthlyExpenses = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        // Compare with previous month
        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();
        $previousMonthExpenses = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount');

        // Check for significant increase (30% or more)
        if ($previousMonthExpenses > 0 && $monthlyExpenses > $previousMonthExpenses * 1.3) {
            $increasePercentage = (($monthlyExpenses - $previousMonthExpenses) / $previousMonthExpenses) * 100;
            $this->sendExpenseIncreaseNotification($user, $monthlyExpenses, $previousMonthExpenses, $increasePercentage);
        }

        // Check for expense milestones (warning thresholds)
        $expenseThresholds = [
            50000 => 'You\'ve spent ₱50,000 this month. Consider reviewing your expenses.',
            100000 => 'Alert: You\'ve reached ₱100,000 in monthly expenses!',
            200000 => 'High spending alert: ₱200,000 spent this month!',
        ];

        foreach ($expenseThresholds as $threshold => $message) {
            if ($monthlyExpenses >= $threshold) {
                $this->sendExpenseThresholdNotification($user, $threshold, $monthlyExpenses, 'monthly', $message);
            }
        }
    }

    /**
     * Check for unusual spending spikes (daily)
     */
    protected function checkSpendingSpikes(User $user)
    {
        $accountIds = $user->accounts()->pluck('id')->toArray();
        if (empty($accountIds)) {
            return;
        }

        // Get today's expenses
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $todayExpenses = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->sum('amount');

        // Calculate average daily expenses for the last 30 days (excluding today)
        $thirtyDaysAgo = now()->subDays(30)->startOfDay();
        $yesterday = now()->subDay()->endOfDay();
        
        $pastExpenses = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$thirtyDaysAgo, $yesterday])
            ->sum('amount');

        $avgDailyExpense = $pastExpenses / 30;

        // Check if today's spending is significantly higher (3x average)
        if ($avgDailyExpense > 0 && $todayExpenses > $avgDailyExpense * 3 && $todayExpenses > 5000) {
            $this->sendSpendingSpikeNotification($user, $todayExpenses, $avgDailyExpense);
        }
    }

    /**
     * Check budget warnings (when approaching budget limits)
     */
    protected function checkBudgetWarnings(User $user)
    {
        $currentMonth = now()->format('Y-m');
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // Get active budgets for current month
        $budgets = Budget::where('user_id', $user->id)
            ->where('period', 'monthly')
            ->where('start_date', '<=', $startDate)
            ->where('end_date', '>=', $endDate)
            ->get();

        foreach ($budgets as $budget) {
            // Calculate spent amount for this budget's category
            $spentAmount = Transaction::whereHas('account', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('type', 'expense')
            ->where('category_id', $budget->category_id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

            $percentageUsed = ($spentAmount / $budget->amount) * 100;

            // Send warning at 80% and 95% thresholds
            if ($percentageUsed >= 95 && $percentageUsed < 100) {
                $this->sendBudgetWarningNotification($user, $budget, $spentAmount, 95);
            } elseif ($percentageUsed >= 80 && $percentageUsed < 95) {
                $this->sendBudgetWarningNotification($user, $budget, $spentAmount, 80);
            } elseif ($percentageUsed >= 100) {
                $this->sendBudgetExceededNotification($user, $budget, $spentAmount);
            }
        }
    }

    /**
     * Check for high-frequency spending (multiple transactions in a short period)
     */
    protected function checkHighFrequencySpending(User $user)
    {
        $accountIds = $user->accounts()->pluck('id')->toArray();
        if (empty($accountIds)) {
            return;
        }

        // Check transactions in the last 2 hours
        $twoHoursAgo = now()->subHours(2);
        $recentTransactions = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'expense')
            ->where('created_at', '>=', $twoHoursAgo)
            ->count();

        // If 5 or more expense transactions in 2 hours
        if ($recentTransactions >= 5) {
            $totalAmount = Transaction::whereIn('account_id', $accountIds)
                ->where('type', 'expense')
                ->where('created_at', '>=', $twoHoursAgo)
                ->sum('amount');

            $this->sendHighFrequencySpendingNotification($user, $recentTransactions, $totalAmount);
        }
    }

    /**
     * Send expense increase notification
     */
    protected function sendExpenseIncreaseNotification(User $user, $currentExpenses, $previousExpenses, $increasePercentage)
    {
        $type = 'expense_increase_monthly';
        
        // Check if similar notification was already sent recently (within current month)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->startOfMonth())
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "Heads up! Your expenses have increased by " . round($increasePercentage, 1) . "% this month. You've spent ₱" . number_format($currentExpenses, 2) . " compared to ₱" . number_format($previousExpenses, 2) . " last month.";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Expense Increase Alert',
            'message' => $message,
            'data' => [
                'current_expenses' => $currentExpenses,
                'previous_expenses' => $previousExpenses,
                'increase_percentage' => round($increasePercentage, 2),
                'alert_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Expense increase notification sent", [
            'user_id' => $user->id,
            'current_expenses' => $currentExpenses,
            'previous_expenses' => $previousExpenses,
            'increase_percentage' => $increasePercentage
        ]);

        return $notification;
    }

    /**
     * Send expense threshold notification
     */
    protected function sendExpenseThresholdNotification(User $user, $threshold, $currentExpenses, $period, $message)
    {
        $type = 'expense_threshold_' . $period;
        
        // Check if similar notification was already sent recently (within current period)
        $timeFrame = $period === 'monthly' ? now()->startOfMonth() : now()->startOfWeek();
        
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->whereJsonContains('data->threshold', $threshold)
            ->where('created_at', '>=', $timeFrame)
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Expense Threshold Alert',
            'message' => $message,
            'data' => [
                'threshold' => $threshold,
                'current_expenses' => $currentExpenses,
                'period' => $period,
                'alert_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Expense threshold notification sent", [
            'user_id' => $user->id,
            'threshold' => $threshold,
            'current_expenses' => $currentExpenses,
            'period' => $period
        ]);

        return $notification;
    }

    /**
     * Send spending spike notification
     */
    protected function sendSpendingSpikeNotification(User $user, $todayExpenses, $avgDailyExpense)
    {
        $type = 'spending_spike_daily';
        
        // Check if similar notification was already sent today
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->startOfDay())
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "Unusual spending detected! You've spent ₱" . number_format($todayExpenses, 2) . " today, which is " . round($todayExpenses / $avgDailyExpense, 1) . "x your average daily spending of ₱" . number_format($avgDailyExpense, 2) . ".";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Spending Spike Alert',
            'message' => $message,
            'data' => [
                'today_expenses' => $todayExpenses,
                'average_daily_expense' => $avgDailyExpense,
                'spike_multiplier' => round($todayExpenses / $avgDailyExpense, 2),
                'alert_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Spending spike notification sent", [
            'user_id' => $user->id,
            'today_expenses' => $todayExpenses,
            'avg_daily_expense' => $avgDailyExpense
        ]);

        return $notification;
    }

    /**
     * Send budget warning notification
     */
    protected function sendBudgetWarningNotification(User $user, Budget $budget, $spentAmount, $warningThreshold)
    {
        $type = 'budget_warning_' . $warningThreshold;
        
        // Check if similar notification was already sent recently (within current month)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->whereJsonContains('data->budget_id', $budget->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $percentageUsed = ($spentAmount / $budget->amount) * 100;
        $categoryName = $budget->category ? $budget->category->name : 'Uncategorized';
        
        $message = "Budget warning! You've used " . round($percentageUsed, 1) . "% of your {$categoryName} budget. Spent: ₱" . number_format($spentAmount, 2) . " of ₱" . number_format($budget->amount, 2) . ".";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Budget Warning',
            'message' => $message,
            'data' => [
                'budget_id' => $budget->id,
                'category_name' => $categoryName,
                'budget_amount' => $budget->amount,
                'spent_amount' => $spentAmount,
                'percentage_used' => round($percentageUsed, 2),
                'warning_threshold' => $warningThreshold,
                'alert_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Budget warning notification sent", [
            'user_id' => $user->id,
            'budget_id' => $budget->id,
            'spent_amount' => $spentAmount,
            'percentage_used' => $percentageUsed,
            'warning_threshold' => $warningThreshold
        ]);

        return $notification;
    }

    /**
     * Send budget exceeded notification
     */
    protected function sendBudgetExceededNotification(User $user, Budget $budget, $spentAmount)
    {
        $type = 'budget_exceeded';
        
        // Check if similar notification was already sent recently (within current month)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->whereJsonContains('data->budget_id', $budget->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $percentageUsed = ($spentAmount / $budget->amount) * 100;
        $excessAmount = $spentAmount - $budget->amount;
        $categoryName = $budget->category ? $budget->category->name : 'Uncategorized';
        
        $message = "Budget exceeded! You've spent ₱" . number_format($spentAmount, 2) . " on {$categoryName}, which is ₱" . number_format($excessAmount, 2) . " over your budget of ₱" . number_format($budget->amount, 2) . ".";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Budget Exceeded!',
            'message' => $message,
            'data' => [
                'budget_id' => $budget->id,
                'category_name' => $categoryName,
                'budget_amount' => $budget->amount,
                'spent_amount' => $spentAmount,
                'excess_amount' => $excessAmount,
                'percentage_used' => round($percentageUsed, 2),
                'alert_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Budget exceeded notification sent", [
            'user_id' => $user->id,
            'budget_id' => $budget->id,
            'spent_amount' => $spentAmount,
            'excess_amount' => $excessAmount
        ]);

        return $notification;
    }

    /**
     * Send high-frequency spending notification
     */
    protected function sendHighFrequencySpendingNotification(User $user, $transactionCount, $totalAmount)
    {
        $type = 'high_frequency_spending';
        
        // Check if similar notification was already sent recently (within 6 hours)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHours(6))
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "High spending activity detected! You've made {$transactionCount} expense transactions totaling ₱" . number_format($totalAmount, 2) . " in the last 2 hours.";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'High Spending Activity',
            'message' => $message,
            'data' => [
                'transaction_count' => $transactionCount,
                'total_amount' => $totalAmount,
                'time_period' => '2 hours',
                'alert_date' => now()->toDateString(),
                'alert_time' => now()->toTimeString()
            ],
            'is_sent' => true
        ]);

        Log::info("High frequency spending notification sent", [
            'user_id' => $user->id,
            'transaction_count' => $transactionCount,
            'total_amount' => $totalAmount
        ]);

        return $notification;
    }

    /**
     * Create notification when large expense is recorded
     */
    public function sendLargeExpenseNotification(Transaction $transaction)
    {
        // Only for expense transactions above a certain threshold (e.g., ₱20,000)
        if ($transaction->type !== 'expense' || $transaction->amount < 20000) {
            return;
        }

        $user = $transaction->account->user;
        
        // Check if similar notification was already sent recently (within 2 hours)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', 'large_expense_recorded')
            ->where('created_at', '>=', now()->subHours(2))
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "Large expense alert! You've recorded an expense of ₱" . number_format($transaction->amount, 2) . " in your {$transaction->account->name} account.";

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'large_expense_recorded',
            'title' => 'Large Expense Alert',
            'message' => $message,
            'data' => [
                'transaction_id' => $transaction->id,
                'transaction_uuid' => $transaction->uuid,
                'amount' => $transaction->amount,
                'account_name' => $transaction->account->name,
                'description' => $transaction->description,
                'category' => $transaction->category ? $transaction->category->name : null,
                'date' => $transaction->transaction_date->toDateString()
            ],
            'is_sent' => true
        ]);
    }

    /**
     * Create notification when expense transaction is created
     */
    public function sendExpenseRecordedNotification(Transaction $transaction)
    {
        if ($transaction->type !== 'expense') {
            return;
        }

        $user = $transaction->account->user;

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'expense_recorded',
            'title' => 'Expense Recorded',
            'message' => "Expense of ₱" . number_format($transaction->amount, 2) . " has been recorded in your {$transaction->account->name} account.",
            'data' => [
                'transaction_id' => $transaction->id,
                'transaction_uuid' => $transaction->uuid,
                'amount' => $transaction->amount,
                'account_name' => $transaction->account->name,
                'description' => $transaction->description,
                'category' => $transaction->category ? $transaction->category->name : null,
                'date' => $transaction->transaction_date->toDateString()
            ],
            'is_sent' => true
        ]);
    }

    /**
     * Create notification when expense transaction is updated
     */
    public function sendExpenseUpdatedNotification(Transaction $transaction)
    {
        if ($transaction->type !== 'expense') {
            return;
        }

        $user = $transaction->account->user;

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'expense_updated',
            'title' => 'Expense Updated',
            'message' => "Your expense transaction of ₱" . number_format($transaction->amount, 2) . " has been updated successfully.",
            'data' => [
                'transaction_id' => $transaction->id,
                'transaction_uuid' => $transaction->uuid,
                'amount' => $transaction->amount,
                'account_name' => $transaction->account->name,
                'description' => $transaction->description,
                'category' => $transaction->category ? $transaction->category->name : null,
                'date' => $transaction->transaction_date->toDateString()
            ],
            'is_sent' => true
        ]);
    }

    /**
     * Create notification when expense transaction is deleted
     */
    public function sendExpenseDeletedNotification($transactionAmount, $accountName, $userId)
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => 'expense_deleted',
            'title' => 'Expense Deleted',
            'message' => "Your expense transaction of ₱" . number_format($transactionAmount, 2) . " from {$accountName} has been deleted successfully.",
            'data' => [
                'amount' => $transactionAmount,
                'account_name' => $accountName
            ],
            'is_sent' => true
        ]);
    }
}
