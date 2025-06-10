<?php

namespace App\Console\Commands;

use App\Services\ExpenseNotificationService;
use Illuminate\Console\Command;

class CheckExpenseNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expense:check-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check expense patterns, budgets, and spending alerts for all users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking expense notifications...');
        
        $service = new ExpenseNotificationService();
        $service->checkExpenseNotifications();
        
        $this->info('Expense notifications check completed.');
        
        return Command::SUCCESS;
    }
}
