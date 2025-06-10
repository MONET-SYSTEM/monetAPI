<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\Notification;
use App\Models\User;
use App\Models\Account;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TransferNotificationService
{
    /**
     * Check all users for transfer-related notifications
     */
    public function checkTransferNotifications()
    {
        $users = User::all();

        foreach ($users as $user) {
            $this->checkTransferPatterns($user);
        }
    }

    /**
     * Check individual user's transfer patterns and thresholds
     */
    public function checkTransferPatterns(User $user)
    {
        // Check monthly transfer patterns
        $this->checkMonthlyTransferPatterns($user);
        
        // Check for high-frequency transfers
        $this->checkHighFrequencyTransfers($user);
        
        // Check large transfer patterns
        $this->checkLargeTransferPatterns($user);
        
        // Check currency conversion patterns
        $this->checkCurrencyConversionPatterns($user);
    }

    /**
     * Check monthly transfer patterns and volumes
     */
    protected function checkMonthlyTransferPatterns(User $user)
    {
        $accountIds = $user->accounts()->pluck('id')->toArray();
        if (empty($accountIds)) {
            return;
        }

        // Current month transfers
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();
        
        $currentMonthTransfers = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'transfer')
            ->whereBetween('transaction_date', [$currentMonthStart, $currentMonthEnd])
            ->count();

        $currentMonthVolume = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'transfer')
            ->whereBetween('transaction_date', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');

        // Previous month transfers
        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();
        
        $previousMonthTransfers = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'transfer')
            ->whereBetween('transaction_date', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $previousMonthVolume = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'transfer')
            ->whereBetween('transaction_date', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount');

        // Check for significant increase in transfer activity (50% or more)
        if ($previousMonthTransfers > 0 && $currentMonthTransfers > $previousMonthTransfers * 1.5) {
            $increasePercentage = (($currentMonthTransfers - $previousMonthTransfers) / $previousMonthTransfers) * 100;
            $this->sendTransferActivityIncreaseNotification($user, $currentMonthTransfers, $previousMonthTransfers, $increasePercentage);
        }

        // Check for significant increase in transfer volume (40% or more)
        if ($previousMonthVolume > 0 && $currentMonthVolume > $previousMonthVolume * 1.4) {
            $volumeIncreasePercentage = (($currentMonthVolume - $previousMonthVolume) / $previousMonthVolume) * 100;
            $this->sendTransferVolumeIncreaseNotification($user, $currentMonthVolume, $previousMonthVolume, $volumeIncreasePercentage);
        }

        // Check transfer volume thresholds
        $volumeThresholds = [
            100000 => 'You\'ve transferred over ₱100,000 this month across your accounts.',
            500000 => 'High transfer activity: Over ₱500,000 transferred this month.',
            1000000 => 'Alert: You\'ve transferred over ₱1,000,000 this month!',
        ];

        foreach ($volumeThresholds as $threshold => $message) {
            if ($currentMonthVolume >= $threshold) {
                $this->sendTransferVolumeThresholdNotification($user, $threshold, $currentMonthVolume, $message);
            }
        }
    }

    /**
     * Check for high-frequency transfer activity
     */
    protected function checkHighFrequencyTransfers(User $user)
    {
        $accountIds = $user->accounts()->pluck('id')->toArray();
        if (empty($accountIds)) {
            return;
        }

        // Check transfers in the last 4 hours
        $fourHoursAgo = now()->subHours(4);
        $recentTransfers = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'transfer')
            ->where('created_at', '>=', $fourHoursAgo)
            ->count();

        // If 8 or more transfers in 4 hours
        if ($recentTransfers >= 8) {
            $totalAmount = Transaction::whereIn('account_id', $accountIds)
                ->where('type', 'transfer')
                ->where('created_at', '>=', $fourHoursAgo)
                ->sum('amount');

            $this->sendHighFrequencyTransferNotification($user, $recentTransfers, $totalAmount);
        }
    }

    /**
     * Check for large transfer patterns
     */
    protected function checkLargeTransferPatterns(User $user)
    {
        $accountIds = $user->accounts()->pluck('id')->toArray();
        if (empty($accountIds)) {
            return;
        }

        // Check for multiple large transfers in a day (over ₱50,000 each)
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        
        $largeTransfersToday = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'transfer')
            ->where('amount', '>=', 50000)
            ->whereBetween('transaction_date', [$todayStart, $todayEnd])
            ->count();

        if ($largeTransfersToday >= 3) {
            $totalAmount = Transaction::whereIn('account_id', $accountIds)
                ->where('type', 'transfer')
                ->where('amount', '>=', 50000)
                ->whereBetween('transaction_date', [$todayStart, $todayEnd])
                ->sum('amount');

            $this->sendMultipleLargeTransfersNotification($user, $largeTransfersToday, $totalAmount);
        }
    }

    /**
     * Check currency conversion patterns
     */
    protected function checkCurrencyConversionPatterns(User $user)
    {
        $accountIds = $user->accounts()->pluck('id')->toArray();
        if (empty($accountIds)) {
            return;
        }

        // Get currency transfers from the last 7 days
        $weekAgo = now()->subDays(7);
        
        $currencyTransfers = Transaction::whereIn('account_id', $accountIds)
            ->where('type', 'transfer')
            ->where('created_at', '>=', $weekAgo)
            ->whereHas('outgoingTransfer', function($query) {
                $query->whereNotNull('exchange_rate');
            })
            ->count();

        // Check for frequent currency conversions (5 or more in a week)
        if ($currencyTransfers >= 5) {
            $totalVolume = Transaction::whereIn('account_id', $accountIds)
                ->where('type', 'transfer')
                ->where('created_at', '>=', $weekAgo)
                ->whereHas('outgoingTransfer', function($query) {
                    $query->whereNotNull('exchange_rate');
                })
                ->sum('amount');

            $this->sendFrequentCurrencyConversionNotification($user, $currencyTransfers, $totalVolume);
        }
    }

    /**
     * Send transfer activity increase notification
     */
    protected function sendTransferActivityIncreaseNotification(User $user, $currentTransfers, $previousTransfers, $increasePercentage)
    {
        $type = 'transfer_activity_increase';
        
        // Check if similar notification was already sent recently (within current month)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->startOfMonth())
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "Transfer activity has increased by " . round($increasePercentage, 1) . "% this month. You've made {$currentTransfers} transfers compared to {$previousTransfers} last month.";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Increased Transfer Activity',
            'message' => $message,
            'data' => [
                'current_transfers' => $currentTransfers,
                'previous_transfers' => $previousTransfers,
                'increase_percentage' => round($increasePercentage, 2),
                'alert_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Transfer activity increase notification sent", [
            'user_id' => $user->id,
            'current_transfers' => $currentTransfers,
            'previous_transfers' => $previousTransfers,
            'increase_percentage' => $increasePercentage
        ]);

        return $notification;
    }

    /**
     * Send transfer volume increase notification
     */
    protected function sendTransferVolumeIncreaseNotification(User $user, $currentVolume, $previousVolume, $increasePercentage)
    {
        $type = 'transfer_volume_increase';
        
        // Check if similar notification was already sent recently (within current month)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->startOfMonth())
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "Transfer volume has increased by " . round($increasePercentage, 1) . "% this month. You've transferred ₱" . number_format($currentVolume, 2) . " compared to ₱" . number_format($previousVolume, 2) . " last month.";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Increased Transfer Volume',
            'message' => $message,
            'data' => [
                'current_volume' => $currentVolume,
                'previous_volume' => $previousVolume,
                'increase_percentage' => round($increasePercentage, 2),
                'alert_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Transfer volume increase notification sent", [
            'user_id' => $user->id,
            'current_volume' => $currentVolume,
            'previous_volume' => $previousVolume,
            'increase_percentage' => $increasePercentage
        ]);

        return $notification;
    }

    /**
     * Send transfer volume threshold notification
     */
    protected function sendTransferVolumeThresholdNotification(User $user, $threshold, $currentVolume, $message)
    {
        $type = 'transfer_volume_threshold';
        
        // Check if similar notification was already sent recently (within current month)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->whereJsonContains('data->threshold', $threshold)
            ->where('created_at', '>=', now()->startOfMonth())
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Transfer Volume Alert',
            'message' => $message,
            'data' => [
                'threshold' => $threshold,
                'current_volume' => $currentVolume,
                'alert_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Transfer volume threshold notification sent", [
            'user_id' => $user->id,
            'threshold' => $threshold,
            'current_volume' => $currentVolume
        ]);

        return $notification;
    }

    /**
     * Send high frequency transfer notification
     */
    protected function sendHighFrequencyTransferNotification(User $user, $transferCount, $totalAmount)
    {
        $type = 'high_frequency_transfers';
        
        // Check if similar notification was already sent recently (within 8 hours)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHours(8))
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "High transfer activity detected! You've made {$transferCount} transfers totaling ₱" . number_format($totalAmount, 2) . " in the last 4 hours.";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'High Transfer Activity',
            'message' => $message,
            'data' => [
                'transfer_count' => $transferCount,
                'total_amount' => $totalAmount,
                'time_period' => '4 hours',
                'alert_date' => now()->toDateString(),
                'alert_time' => now()->toTimeString()
            ],
            'is_sent' => true
        ]);

        Log::info("High frequency transfer notification sent", [
            'user_id' => $user->id,
            'transfer_count' => $transferCount,
            'total_amount' => $totalAmount
        ]);

        return $notification;
    }

    /**
     * Send multiple large transfers notification
     */
    protected function sendMultipleLargeTransfersNotification(User $user, $transferCount, $totalAmount)
    {
        $type = 'multiple_large_transfers';
        
        // Check if similar notification was already sent recently (within 24 hours)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "Multiple large transfers detected! You've made {$transferCount} transfers of ₱50,000+ today, totaling ₱" . number_format($totalAmount, 2) . ".";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Multiple Large Transfers',
            'message' => $message,
            'data' => [
                'transfer_count' => $transferCount,
                'total_amount' => $totalAmount,
                'minimum_amount' => 50000,
                'alert_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Multiple large transfers notification sent", [
            'user_id' => $user->id,
            'transfer_count' => $transferCount,
            'total_amount' => $totalAmount
        ]);

        return $notification;
    }

    /**
     * Send frequent currency conversion notification
     */
    protected function sendFrequentCurrencyConversionNotification(User $user, $conversionCount, $totalVolume)
    {
        $type = 'frequent_currency_conversions';
        
        // Check if similar notification was already sent recently (within 7 days)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subDays(7))
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        $message = "Frequent currency conversions detected! You've made {$conversionCount} currency transfers totaling ₱" . number_format($totalVolume, 2) . " this week.";

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => 'Frequent Currency Conversions',
            'message' => $message,
            'data' => [
                'conversion_count' => $conversionCount,
                'total_volume' => $totalVolume,
                'time_period' => '7 days',
                'alert_date' => now()->toDateString()
            ],
            'is_sent' => true
        ]);

        Log::info("Frequent currency conversion notification sent", [
            'user_id' => $user->id,
            'conversion_count' => $conversionCount,
            'total_volume' => $totalVolume
        ]);

        return $notification;
    }

    /**
     * Send notification when a regular transfer is created (same currency)
     */
    public function sendTransferCreatedNotification($result, User $user)
    {
        $outgoingTransaction = $result['outgoing'];
        $incomingTransaction = $result['incoming'];

        // Only send notification for the outgoing transaction to avoid duplicate notifications
        $sourceAccount = $outgoingTransaction->account;
        $destinationAccount = $incomingTransaction->account;

        $message = "Transfer completed: ₱" . number_format($outgoingTransaction->amount, 2) . " from {$sourceAccount->name} to {$destinationAccount->name}.";

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'transfer_completed',
            'title' => 'Transfer Completed',
            'message' => $message,
            'data' => [
                'outgoing_transaction_id' => $outgoingTransaction->id,
                'incoming_transaction_id' => $incomingTransaction->id,
                'outgoing_uuid' => $outgoingTransaction->uuid,
                'incoming_uuid' => $incomingTransaction->uuid,
                'amount' => $outgoingTransaction->amount,
                'source_account' => $sourceAccount->name,
                'destination_account' => $destinationAccount->name,
                'transfer_type' => 'same_currency',
                'date' => $outgoingTransaction->transaction_date->toDateString()
            ],
            'is_sent' => true
        ]);
    }

    /**
     * Send notification when a currency transfer is created (different currencies)
     */
    public function sendCurrencyTransferCreatedNotification($result, User $user)
    {
        $outgoingTransaction = $result['outgoing'];
        $incomingTransaction = $result['incoming'];
        $exchangeRate = $result['exchange_rate'] ?? null;

        $sourceAccount = $outgoingTransaction->account;
        $destinationAccount = $incomingTransaction->account;
        $sourceCurrency = $sourceAccount->currency->code;
        $destinationCurrency = $destinationAccount->currency->code;

        $exchangeRateText = $exchangeRate ? " at rate 1 {$sourceCurrency} = " . number_format($exchangeRate, 4) . " {$destinationCurrency}" : '';

        $message = "Currency transfer completed: {$sourceCurrency} " . number_format($outgoingTransaction->amount, 2) . " from {$sourceAccount->name} to {$destinationCurrency} " . number_format($incomingTransaction->amount, 2) . " in {$destinationAccount->name}{$exchangeRateText}.";

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'currency_transfer_completed',
            'title' => 'Currency Transfer Completed',
            'message' => $message,
            'data' => [
                'outgoing_transaction_id' => $outgoingTransaction->id,
                'incoming_transaction_id' => $incomingTransaction->id,
                'outgoing_uuid' => $outgoingTransaction->uuid,
                'incoming_uuid' => $incomingTransaction->uuid,
                'source_amount' => $outgoingTransaction->amount,
                'destination_amount' => $incomingTransaction->amount,
                'source_currency' => $sourceCurrency,
                'destination_currency' => $destinationCurrency,
                'exchange_rate' => $exchangeRate,
                'source_account' => $sourceAccount->name,
                'destination_account' => $destinationAccount->name,
                'transfer_type' => 'currency_conversion',
                'date' => $outgoingTransaction->transaction_date->toDateString()
            ],
            'is_sent' => true
        ]);
    }

    /**
     * Send notification when a large transfer is created (₱100,000+)
     */
    public function sendLargeTransferNotification($result, User $user)
    {
        $outgoingTransaction = $result['outgoing'];
        $incomingTransaction = $result['incoming'];

        // Only send for transfers of ₱100,000 or more
        if ($outgoingTransaction->amount < 100000) {
            return;
        }

        $sourceAccount = $outgoingTransaction->account;
        $destinationAccount = $incomingTransaction->account;

        // Check if this is a currency transfer
        $isCurrencyTransfer = isset($result['exchange_rate']) && $result['exchange_rate'] !== null;
        
        if ($isCurrencyTransfer) {
            $sourceCurrency = $sourceAccount->currency->code;
            $destinationCurrency = $destinationAccount->currency->code;
            $exchangeRate = $result['exchange_rate'];
            
            $message = "Large currency transfer alert! {$sourceCurrency} " . number_format($outgoingTransaction->amount, 2) . " converted to {$destinationCurrency} " . number_format($incomingTransaction->amount, 2) . " (Rate: 1 {$sourceCurrency} = " . number_format($exchangeRate, 4) . " {$destinationCurrency}).";
        } else {
            $message = "Large transfer alert! ₱" . number_format($outgoingTransaction->amount, 2) . " transferred from {$sourceAccount->name} to {$destinationAccount->name}.";
        }

        // Check if similar notification was already sent recently (within 2 hours)
        $recentNotification = Notification::where('user_id', $user->id)
            ->where('type', 'large_transfer_alert')
            ->where('created_at', '>=', now()->subHours(2))
            ->first();

        if ($recentNotification) {
            return; // Don't send duplicate notifications
        }

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'large_transfer_alert',
            'title' => 'Large Transfer Alert',
            'message' => $message,
            'data' => [
                'outgoing_transaction_id' => $outgoingTransaction->id,
                'incoming_transaction_id' => $incomingTransaction->id,
                'outgoing_uuid' => $outgoingTransaction->uuid,
                'incoming_uuid' => $incomingTransaction->uuid,
                'source_amount' => $outgoingTransaction->amount,
                'destination_amount' => $incomingTransaction->amount,
                'source_account' => $sourceAccount->name,
                'destination_account' => $destinationAccount->name,
                'is_currency_transfer' => $isCurrencyTransfer,
                'exchange_rate' => $result['exchange_rate'] ?? null,
                'source_currency' => $isCurrencyTransfer ? $sourceAccount->currency->code : null,
                'destination_currency' => $isCurrencyTransfer ? $destinationAccount->currency->code : null,
                'date' => $outgoingTransaction->transaction_date->toDateString()
            ],
            'is_sent' => true
        ]);
    }

    /**
     * Send notification when transfer transactions are updated
     */
    public function sendTransferUpdatedNotification(Transaction $transaction)
    {
        if ($transaction->type !== 'transfer') {
            return;
        }

        $user = $transaction->account->user;

        // Check if this is part of a transfer pair
        $isOutgoing = $transaction->isTransferOut();
        $isIncoming = $transaction->isTransferIn();
        
        if (!$isOutgoing && !$isIncoming) {
            return; // Not a complete transfer
        }

        $transferDirection = $isOutgoing ? 'outgoing' : 'incoming';
        $accountName = $transaction->account->name;

        $message = "Transfer transaction updated: ₱" . number_format($transaction->amount, 2) . " ({$transferDirection} from {$accountName}).";

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'transfer_updated',
            'title' => 'Transfer Updated',
            'message' => $message,
            'data' => [
                'transaction_id' => $transaction->id,
                'transaction_uuid' => $transaction->uuid,
                'amount' => $transaction->amount,
                'account_name' => $accountName,
                'transfer_direction' => $transferDirection,
                'description' => $transaction->description,
                'is_currency_transfer' => $transaction->isCurrencyTransfer(),
                'date' => $transaction->transaction_date->toDateString()
            ],
            'is_sent' => true
        ]);
    }

    /**
     * Send notification when transfer transactions are deleted
     */
    public function sendTransferDeletedNotification($transactionAmount, $accountName, $userId, $transferDirection = 'transfer')
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => 'transfer_deleted',
            'title' => 'Transfer Deleted',
            'message' => "Your transfer transaction of ₱" . number_format($transactionAmount, 2) . " from {$accountName} has been deleted successfully.",
            'data' => [
                'amount' => $transactionAmount,
                'account_name' => $accountName,
                'transfer_direction' => $transferDirection
            ],
            'is_sent' => true
        ]);
    }
}
