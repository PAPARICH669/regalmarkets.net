<?php

namespace Tests\Feature;

use App\Models\Rank;
use App\Models\User;
use App\Models\Wallet;
use App\Services\RankService;
use Database\Seeders\RankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RankSeeder::class);
    }

    protected function makeUser(string $username, ?User $sponsor, float $fund): User
    {
        $u = User::create([
            'username'       => $username,
            'email'          => "$username@test.com",
            'password'       => bcrypt('password'),
            'sponsor_id'     => $sponsor?->id,
            'rank_id'        => Rank::byName('USER')->id,
            'referral_code'  => strtoupper($username),
            'total_invested' => $fund, // rank eligibility is measured by total_invested
        ]);
        Wallet::create(['user_id' => $u->id, 'type' => 'A', 'balance' => 0]);
        Wallet::create(['user_id' => $u->id, 'type' => 'E', 'balance' => 0]);
        return $u;
    }

    /** @test */
    public function rank_is_retained_after_requirements_drop()
    {
        // Leader funds 100 and has 3 directs each funded 100 → qualifies for FAN.
        $leader = $this->makeUser('leader', null, 100);
        $d1 = $this->makeUser('d1', $leader, 100);
        $d2 = $this->makeUser('d2', $leader, 100);
        $d3 = $this->makeUser('d3', $leader, 100);

        app(RankService::class)->updateAll();
        $this->assertEquals('FAN', $leader->fresh()->rankName(), 'leader should reach FAN');

        // Simulate "200% ROI done / downline habis": requirements effectively drop
        // (e.g. a direct no longer counts). Rank must NOT be demoted.
        $d1->update(['total_invested' => 0]);
        $d2->update(['total_invested' => 0]);
        $d3->update(['total_invested' => 0]);
        $leader->update(['total_invested' => 0]);

        app(RankService::class)->updateAll();
        $this->assertEquals('FAN', $leader->fresh()->rankName(), 'rank must be retained once achieved');
    }

    /** @test */
    public function higher_rank_is_retained_when_downline_production_stops()
    {
        // GROUP LEADER retains rank even if its whole downline later drops to USER.
        $boss = $this->makeUser('boss', null, 5000);
        $boss->update(['rank_id' => Rank::byName('GROUP LEADER')->id]); // already earned
        $x = $this->makeUser('x', $boss, 0);

        app(RankService::class)->updateAll();

        $this->assertEquals('GROUP LEADER', $boss->fresh()->rankName(), 'earned top rank must stay');
        $this->assertEquals('USER', $x->fresh()->rankName());
    }
}
