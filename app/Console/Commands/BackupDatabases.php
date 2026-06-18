<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class BackupDatabases extends Command
{
    protected $signature = 'backup:run-databases {--disable-notifications : Disable backup notifications}';

    protected $description = 'Backup each configured database as a separate zip on Google Drive';

    public function handle()
    {
        $timestamp = Carbon::now()->format('Y-m-d-H-i-s');

        $databases = [
            'mysql' => config('database.connections.mysql.database'),
            'mysql_cart' => config('database.connections.mysql_cart.database'),
        ];

        $options = [
            '--only-db' => true,
            '--disable-notifications' => $this->option('disable-notifications'),
        ];

        $exitCode = 0;

        foreach ($databases as $connection => $databaseName) {
            $this->info("Backing up {$databaseName}...");

            $code = $this->call('backup:run', array_merge($options, [
                '--db-name' => [$connection],
                '--filename' => "{$databaseName}-{$timestamp}.zip",
            ]));

            if ($code !== 0) {
                $this->error("Backup failed for {$databaseName}.");
                $exitCode = 1;
            }
        }

        return $exitCode;
    }
}
