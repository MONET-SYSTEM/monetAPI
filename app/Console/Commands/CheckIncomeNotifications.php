<?php

namespace App\Console\Commands;

use App\Services\IncomeNotificationService;
use Illuminate\Console\Command;

class CheckIncomeNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'income:check-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check income milestones and send notifications for achievements';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking income notifications...');
        
        $service = new IncomeNotificationService();
        $service->checkIncomeNotifications();
        
        $this->info('Income notifications check completed.');
        
        return Command::SUCCESS;
    }
}
