<?php

namespace Database\Seeders;

use App\Models\Rank;
use Illuminate\Database\Seeder;

class RankSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('regal.ranks') as $name => $cfg) {
            Rank::updateOrCreate(['name' => $name], [
                'level'              => $cfg['level'],
                'match_percent'      => $cfg['match'],
                'min_fund'           => $cfg['min_fund'] ?? 0,
                'direct_min_deposit' => $cfg['direct_min_deposit'] ?? null,
                'directs_required'   => $cfg['directs_required'] ?? null,
                'produce_rank'       => $cfg['produce_rank'] ?? null,
                'produce_count'      => $cfg['produce_count'] ?? null,
            ]);
        }
    }
}
