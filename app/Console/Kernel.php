<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */    protected function schedule(Schedule $schedule): void
    {
        // Test schedule - simple command every minute for testing
        $schedule->command('list')->everyMinute();
        
        // Check budget notifications every hour
        $schedule->command('budget:check-notifications')->hourly();
        
        // Check income notifications daily at 9 AM
        $schedule->command('income:check-notifications')->dailyAt('09:00');
        
        // Check expense notifications daily at 10 AM
        $schedule->command('expense:check-notifications')->dailyAt('10:00');
        
        // Check transfer notifications daily at 11 AM
        $schedule->command('transfer:check-notifications')->dailyAt('11:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
