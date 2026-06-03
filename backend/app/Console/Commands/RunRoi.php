<?php

namespace App\Console\Commands;

use App\Services\RoiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunRoi extends Command
{
    protected $signature = 'roi:run {--date= : Date (Y-m-d) to run ROI for, defaults to today}';
    protected $description = 'Distribute daily ROI to active investment packages and run matching bonus rollup.';

    public function handle(RoiService $roi): int
    {
        $date  = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $this->info("Running ROI for {$date->toDateString()} ...");

        $stats = $roi->runForDate($date);

        $this->table(
            ['Packages paid', 'Total ROI', 'Completed', 'Skipped (already paid/inactive)'],
            [[$stats['paid'], $stats['amount'] . ' USDT', $stats['completed'], $stats['skipped']]]
        );

        return self::SUCCESS;
    }
}
