<?php

namespace App\Console;

use App\Console\Commands\MakeCrud;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $logFile = storage_path('logs/backup-scheduler.log');

        // Runs only at 01:00 and 13:00 (Asia/Colombo). "schedule:run" must be called every minute via cron.
        $schedule->command('backup:run-databases --disable-notifications')
            ->twiceDaily(1, 13)
            ->appendOutputTo($logFile);

        $schedule->command('backup:clean --disable-notifications')
            ->dailyAt('00:30')
            ->appendOutputTo($logFile);
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    protected $commands = [
        MakeCrud::class,
    ];
}
