<?php

namespace App\Console\Commands;

use App\Services\RoiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunRoi extends Command
{
    protected $signature = 'roi:run {--date= : Date (Y-m-d) to run ROI for, defaults to today} {--percent= : Manual daily ROI %% (overrides the setting for this run)}';
    protected $description = 'Distribute daily ROI to active investment packages and run matching bonus rollup.';

    public function handle(RoiService $roi): int
    {
        $date    = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $percent = $this->option('percent') !== null ? (float) $this->option('percent') : null;
        $this->info("Running ROI for {$date->toDateString()} ...");

        $stats = $roi->runForDate($date, $percent);

        $this->table(
            ['Daily %', 'Packages paid', 'Total ROI', 'Completed', 'Skipped'],
            [[$stats['percent'] . '%', $stats['paid'], $stats['amount'] . ' USDT', $stats['completed'], $stats['skipped']]]
        );

        return self::SUCCESS;
    }
}
