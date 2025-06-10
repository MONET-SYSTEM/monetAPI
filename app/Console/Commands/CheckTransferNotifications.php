<?php

namespace App\Console\Commands;

use App\Services\TransferNotificationService;
use Illuminate\Console\Command;

class CheckTransferNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transfer:check-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check transfer patterns, volumes, and currency conversion activities for all users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking transfer notifications...');
        
        $service = new TransferNotificationService();
        $service->checkTransferNotifications();
        
        $this->info('Transfer notifications check completed.');
        
        return Command::SUCCESS;
    }
}
