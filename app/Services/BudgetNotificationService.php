<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class BudgetNotificationService
{
    /**
     * Check all active budgets for notification triggers
     */
    public function checkBudgetNotifications()
    {
        $budgets = Budget::where('status', 'active')
            ->where('send_notifications', true)
            ->with(['user'])
            ->get();

        foreach ($budgets as $budget) {
            $this->checkBudgetThreshold($budget);
        }
    }

    /**
     * Check individual budget against threshold
     */
    public function checkBudgetThreshold(Budget $budget)
    {
        $spentPercentage = $budget->amount > 0 ? ($budget->spent_amount / $budget->amount) * 100 : 0;
        $threshold = $budget->notification_threshold ?? 80;

        // Check if threshold exceeded and notification not already sent
        if ($spentPercentage >= $threshold) {
            $this->sendBudgetNotification($budget, $spentPercentage);
        }
    }

    /**
     * Send budget notification
     */
    protected function sendBudgetNotification(Budget $budget, $spentPercentage)
    {
        $type = $spentPercentage >= 100 ? 'budget_exceeded' : 'budget_warning';
        
        // Check if similar notification was already sent recently (within 24 hours)
        $recentNotification = Notification::where('user_id', $budget->user_id)
            ->where('type', $type)
            ->whereJsonContains('data->budget_id', $budget->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $title = $spentPercentage >= 100 
            ? "Budget Exceeded!" 
            : "Budget Warning!";

        $message = $spentPercentage >= 100
            ? "Your '{$budget->name}' budget has been exceeded. You've spent ₱{$budget->spent_amount} out of ₱{$budget->amount} (" . round($spentPercentage, 1) . "%)"
            : "Your '{$budget->name}' budget is at " . round($spentPercentage, 1) . "% (₱{$budget->spent_amount} of ₱{$budget->amount})";

        $notification = Notification::create([
            'user_id' => $budget->user_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => [
                'budget_id' => $budget->id,
                'budget_uuid' => $budget->uuid,
                'budget_name' => $budget->name,
                'spent_amount' => $budget->spent_amount,
                'budget_amount' => $budget->amount,
                'percentage' => round($spentPercentage, 2)
            ],
            'is_sent' => true
        ]);

        Log::info("Budget notification sent", [
            'user_id' => $budget->user_id,
            'budget_id' => $budget->id,
            'type' => $type,
            'percentage' => $spentPercentage
        ]);

        return $notification;
    }

    /**
     * Create notification when budget is created
     */
    public function sendBudgetCreatedNotification(Budget $budget)
    {
        return Notification::create([
            'user_id' => $budget->user_id,
            'type' => 'budget_created',
            'title' => 'Budget Created',
            'message' => "Your budget '{$budget->name}' has been created successfully with an amount of ₱{$budget->amount}.",
            'data' => [
                'budget_id' => $budget->id,
                'budget_uuid' => $budget->uuid,
                'budget_name' => $budget->name,
                'budget_amount' => $budget->amount
            ],
            'is_sent' => true
        ]);
    }

    /**
     * Create notification when budget is updated
     */
    public function sendBudgetUpdatedNotification(Budget $budget)
    {
        return Notification::create([
            'user_id' => $budget->user_id,
            'type' => 'budget_updated',
            'title' => 'Budget Updated',
            'message' => "Your budget '{$budget->name}' has been updated successfully.",
            'data' => [
                'budget_id' => $budget->id,
                'budget_uuid' => $budget->uuid,
                'budget_name' => $budget->name,
                'budget_amount' => $budget->amount
            ],
            'is_sent' => true
        ]);
    }

    /**
     * Create notification when budget is deleted
     */
    public function sendBudgetDeletedNotification($budgetName, $userId)
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => 'budget_deleted',
            'title' => 'Budget Deleted',
            'message' => "Your budget '{$budgetName}' has been deleted successfully.",
            'data' => [
                'budget_name' => $budgetName
            ],
            'is_sent' => true
        ]);
    }
}
