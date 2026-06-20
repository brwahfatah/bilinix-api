<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\GenerateRenewals::class,
        \App\Console\Commands\BillingAutomation::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // 1️⃣ Generate renewal invoices (daily)
        $schedule->command('billing:generate-renewals')
            ->dailyAt('01:00')
            ->withoutOverlapping();

        // 2️⃣ Suspend / terminate overdue services
        $schedule->command('billing:automation')
            ->dailyAt('02:00')
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
