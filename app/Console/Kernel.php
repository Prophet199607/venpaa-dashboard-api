<?php

namespace App\Console;

use App\Console\Commands\MakeCrud;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Separate zip per database on Google Drive, twice per day.
        $schedule->command('backup:run-databases --disable-notifications')
            ->twiceDaily(1, 13);

        // Prune old backups daily according to config/backup.php cleanup strategy.
        $schedule->command('backup:clean --disable-notifications')
            ->dailyAt('00:30');
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
