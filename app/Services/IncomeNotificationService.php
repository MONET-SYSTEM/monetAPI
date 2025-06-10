<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class IncomeNotificationService
{
    /**
     * Check all users for income milestone notifications
     */
    public function checkIncomeNotifications()
    {
        $users = User::all();

        foreach ($users as $user) {
            $this->checkIncomeThresholds($user);
        }
    }

    /**
     * Check individual user's income against various thresholds
     */
    public function checkIncomeThresholds(User $user)
    {
        // Check monthly income milestones
        $this->checkMonthlyIncome($user);
        
        // Check weekly income consistency
        $this->checkWeeklyIncomeConsistency($user);
        
        // Check income goals achievement
        $this->checkIncomeGoals($user);
    }

    /**
     * Check monthly income milestones
     */
    protected function checkMonthlyIncome(User $user)
    {
        $currentMonth = now()->format('Y-m');
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // Get user's accounts
        $accountIds = $user->accounts()->pluck('id')->toArray();
        if (empty($accountIds)) {
            return;
        }

        // Calculate total income for current month
        $monthlyIncome = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');

        // Define income milestones (you can make these configurable)
        $milestones = [
            10000 => 'Congratulations! You\'ve reached ₱10,000 in monthly income!',
            25000 => 'Great job! You\'ve earned ₱25,000 this month!',
            50000 => 'Excellent! You\'ve achieved ₱50,000 in monthly income!',
            100000 => 'Outstanding! You\'ve reached ₱100,000 in monthly income!',
        ];

        foreach ($milestones as $milestone => $message) {
            if ($monthlyIncome >= $milestone) {
                $this->sendIncomeMilestoneNotification($user, $milestone, $monthlyIncome, 'monthly', $message);
            }
        }
    }

    /**
     * Check weekly income consistency
     */
    protected function checkWeeklyIncomeConsistency(User $user)
    {
        $accountIds = $user->accounts()->pluck('id')->toArray();
        if (empty($accountIds)) {
            return;
        }

        // Check last 4 weeks for consistency
        $weeksWithIncome = 0;
        $totalWeeklyIncome = 0;

        for ($i = 0; $i < 4; $i++) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();

            $weeklyIncome = Transaction::whereIn('account_id', $accountIds)
                ->where('type', 'income')
                ->whereBetween('transaction_date', [$weekStart, $weekEnd])
                ->sum('amount');

            if ($weeklyIncome > 0) {
                $weeksWithIncome++;
                $totalWeeklyIncome += $weeklyIncome;
            }
        }

        // If user has income for 4 consecutive weeks
        if ($weeksWithIncome >= 4) {
            $avgWeeklyIncome = $totalWeeklyIncome / 4;
            $this->sendIncomeConsistencyNotification($user, $weeksWithIncome, $avgWeeklyIncome);
        }
    }

    /**
     * Check income goals achievement (based on previous month comparison)
     */
    protected function checkIncomeGoals(User $user)
    {
        $accountIds = $user->accounts()->pluck('id')->toArray();
        if (empty($accountIds)) {
            return;
        }

        // Current month income
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();
        $currentIncome = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');

        // Previous month income
        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();
        $previousIncome = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount');

        // Check for significant improvement (20% or more)
        if ($previousIncome > 0 && $currentIncome > $previousIncome * 1.2) {
            $improvementPercentage = (($currentIncome - $previousIncome) / $previousIncome) * 100;
            $this->sendIncomeImprovementNotification($user, $currentIncome, $previousIncome, $improvementPercentage);
        }
    }

    /**
     * Send income milestone notification
     */
    protected function sendIncomeMilestoneNotification(User $user, $milestone, $currentIncome, $period, $message)
    {
        $type = 'income_milestone_' . $period;
        
        // Check if similar notification was already sent recently (within current period)
        $timeFrame = $period === 'monthly' ? now()->startOfMonth() : now()->startOfWeek();
        
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->whereJsonContains('data->milestone', $milestone)
            ->where('created_at', '>=', $timeFrame)
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Income Milestone Achieved!',
            'message' => $message,
            'data' => [
                'milestone' => $milestone,
                'current_income' => $currentIncome,
                'period' => $period,
                'achievement_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Income milestone notification sent", [
            'user_id' => $user->id,
            'milestone' => $milestone,
            'current_income' => $currentIncome,
            'period' => $period
        ]);

        return $notification;
    }

    /**
     * Send income consistency notification
     */
    protected function sendIncomeConsistencyNotification(User $user, $consecutiveWeeks, $avgWeeklyIncome)
    {
        $type = 'income_consistency';
        
        // Check if similar notification was already sent recently (within 30 days)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subDays(30))
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "Great job! You've maintained consistent income for {$consecutiveWeeks} consecutive weeks with an average of ₱" . number_format($avgWeeklyIncome, 2) . " per week.";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Consistent Income Achievement!',
            'message' => $message,
            'data' => [
                'consecutive_weeks' => $consecutiveWeeks,
                'average_weekly_income' => $avgWeeklyIncome,
                'achievement_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Income consistency notification sent", [
            'user_id' => $user->id,
            'consecutive_weeks' => $consecutiveWeeks,
            'avg_weekly_income' => $avgWeeklyIncome
        ]);

        return $notification;
    }

    /**
     * Send income improvement notification
     */
    protected function sendIncomeImprovementNotification(User $user, $currentIncome, $previousIncome, $improvementPercentage)
    {
        $type = 'income_improvement';
        
        // Check if similar notification was already sent recently (within current month)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->startOfMonth())
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "Fantastic! Your income has improved by " . round($improvementPercentage, 1) . "% this month. You earned ₱" . number_format($currentIncome, 2) . " compared to ₱" . number_format($previousIncome, 2) . " last month.";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Income Growth Achievement!',
            'message' => $message,
            'data' => [
                'current_income' => $currentIncome,
                'previous_income' => $previousIncome,
                'improvement_percentage' => round($improvementPercentage, 2),
                'achievement_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Income improvement notification sent", [
            'user_id' => $user->id,
            'current_income' => $currentIncome,
            'previous_income' => $previousIncome,
            'improvement_percentage' => $improvementPercentage
        ]);

        return $notification;
    }

    /**
     * Create notification when significant income is recorded
     */
    public function sendLargeIncomeNotification(Transaction $transaction)
    {
        // Only for income transactions above a certain threshold (e.g., ₱50,000)
        if ($transaction->type !== 'income' || $transaction->amount < 50000) {
            return;
        }

        $user = $transaction->account->user;
        
        // Check if similar notification was already sent recently (within 24 hours)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', 'large_income_received')
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "Great news! You've received a significant income of ₱" . number_format($transaction->amount, 2) . " in your {$transaction->account->name} account.";

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'large_income_received',
            'title' => 'Large Income Received!',
            'message' => $message,
            'data' => [
                'transaction_id' => $transaction->id,
                'transaction_uuid' => $transaction->uuid,
                'amount' => $transaction->amount,
                'account_name' => $transaction->account->name,
                'description' => $transaction->description,
                'date' => $transaction->transaction_date->toDateString()
            ],
            'is_sent' => true
        ]);
    }

    /**
     * Create notification when income transaction is created
     */
    public function sendIncomeRecordedNotification(Transaction $transaction)
    {
        if ($transaction->type !== 'income') {
            return;
        }

        $user = $transaction->account->user;

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'income_recorded',
            'title' => 'Income Recorded',
            'message' => "Income of ₱" . number_format($transaction->amount, 2) . " has been recorded in your {$transaction->account->name} account.",
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
     * Create notification when income transaction is updated
     */
    public function sendIncomeUpdatedNotification(Transaction $transaction)
    {
        if ($transaction->type !== 'income') {
            return;
        }

        $user = $transaction->account->user;

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'income_updated',
            'title' => 'Income Updated',
            'message' => "Your income transaction of ₱" . number_format($transaction->amount, 2) . " has been updated successfully.",
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
     * Create notification when income transaction is deleted
     */
    public function sendIncomeDeletedNotification($transactionAmount, $accountName, $userId)
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => 'income_deleted',
            'title' => 'Income Deleted',
            'message' => "Your income transaction of ₱" . number_format($transactionAmount, 2) . " from {$accountName} has been deleted successfully.",
            'data' => [
                'amount' => $transactionAmount,
                'account_name' => $accountName
            ],
            'is_sent' => true
        ]);
    }
}
