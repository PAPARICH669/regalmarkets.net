<?php

namespace App\Console\Commands;

use App\Services\MaintenanceService;
use Illuminate\Console\Command;

class SyncMaintenance extends Command
{
    protected $signature = 'maintenance:sync';
    protected $description = 'Open/close the daily maintenance window based on the configured schedule.';

    public function handle(MaintenanceService $maintenance): int
    {
        $maintenance->sync();
        $this->info($maintenance->isActive() ? 'Maintenance ACTIVE.' : 'System LIVE.');
        return self::SUCCESS;
    }
}
