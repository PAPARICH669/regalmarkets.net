<?php

namespace App\Console\Commands;

use App\Services\SponsorRewardService;
use Illuminate\Console\Command;

class GenerateTopSponsorRewards extends Command
{
    protected $signature = 'rewards:top-sponsors {--period= : Month YYYY-MM (defaults to the previous month)}';
    protected $description = 'Generate the monthly Top-5 Sponsor reward list (pending admin settlement).';

    public function handle(SponsorRewardService $service): int
    {
        $period = $this->option('period') ?: $service->previousPeriod();
        $this->info("Generating Top-5 Sponsor rewards for {$period} ...");
        $result = $service->generate($period);
        $this->info("Done. Created {$result['created']} reward(s)" . (isset($result['note']) ? " ({$result['note']})" : '') . '.');
        return self::SUCCESS;
    }
}
