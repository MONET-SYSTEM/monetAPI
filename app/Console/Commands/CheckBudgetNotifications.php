<?php

namespace App\Console\Commands;

use App\Services\BudgetNotificationService;
use Illuminate\Console\Command;

class CheckBudgetNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'budget:check-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check budget thresholds and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking budget notifications...');
        
        $service = app(BudgetNotificationService::class);
        $service->checkBudgetNotifications();
        
        $this->info('Budget notifications checked successfully!');
        
        return Command::SUCCESS;
    }
}
