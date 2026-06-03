<?php

namespace App\Console\Commands;

use App\Services\RankService;
use Illuminate\Console\Command;

class UpdateRanks extends Command
{
    protected $signature = 'rank:update';
    protected $description = 'Recompute member ranks from fund and downline production.';

    public function handle(RankService $ranks): int
    {
        $this->info('Updating ranks ...');
        $result = $ranks->updateAll();
        $this->info("Ranks updated. {$result['changed']} member(s) changed rank.");
        return self::SUCCESS;
    }
}
