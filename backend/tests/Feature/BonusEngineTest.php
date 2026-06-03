<?php

namespace Tests\Feature;

use App\Models\Rank;
use App\Models\RoiLog;
use App\Models\User;
use App\Models\Wallet;
use App\Services\InvestmentService;
use App\Services\MatchingBonusService;
use App\Services\RoiService;
use App\Services\SponsorBonusService;
use App\Services\WalletService;
use Database\Seeders\RankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BonusEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RankSeeder::class);
    }

    protected function makeUser(string $username, ?User $sponsor, string $rankName): User
    {
        $user = User::create([
            'username'      => $username,
            'email'         => "$username@test.com",
            'password'      => bcrypt('password'),
            'sponsor_id'    => $sponsor?->id,
            'rank_id'       => Rank::byName($rankName)->id,
            'referral_code' => strtoupper($username),
        ]);
        Wallet::create(['user_id' => $user->id, 'type' => 'A', 'balance' => 0]);
        Wallet::create(['user_id' => $user->id, 'type' => 'E', 'balance' => 0]);
        return $user;
    }

    /** @test */
    public function matching_bonus_uses_differential_rollup()
    {
        // GROUP LEADER ← TEAM LEADER ← SENIOR ← USER(earner)
        $gl     = $this->makeUser('gl', null, 'GROUP LEADER');
        $tl     = $this->makeUser('tl', $gl, 'TEAM LEADER');
        $senior = $this->makeUser('senior', $tl, 'SENIOR');
        $earner = $this->makeUser('earner', $senior, 'USER');

        // Simulate a 100 USDT ROI for the earner
        $roiLog = RoiLog::create([
            'investment_package_id' => $this->stubPackage($earner),
            'user_id'               => $earner->id,
            'amount'                => 100,
            'roi_date'              => now()->toDateString(),
        ]);

        app(MatchingBonusService::class)->distributeForRoi($roiLog);

        // SENIOR 8% (8-0), TEAM LEADER 4% (12-8), GROUP LEADER 4% (16-12)
        $this->assertEquals('8.00000000', $senior->walletE->refresh()->balance);
        $this->assertEquals('4.00000000', $tl->walletE->refresh()->balance);
        $this->assertEquals('4.00000000', $gl->walletE->refresh()->balance);
    }

    /** @test */
    public function matching_bonus_stops_on_same_rank()
    {
        // GROUP LEADER ← GROUP LEADER ← USER(earner)
        $topGl  = $this->makeUser('topgl', null, 'GROUP LEADER');
        $gl2    = $this->makeUser('gl2', $topGl, 'GROUP LEADER');
        $earner = $this->makeUser('earner2', $gl2, 'USER');

        $roiLog = RoiLog::create([
            'investment_package_id' => $this->stubPackage($earner),
            'user_id'               => $earner->id,
            'amount'                => 100,
            'roi_date'              => now()->toDateString(),
        ]);

        app(MatchingBonusService::class)->distributeForRoi($roiLog);

        // First GL gets 16; second GL would be 16-16=0 -> stop, nothing above.
        $this->assertEquals('16.00000000', $gl2->walletE->refresh()->balance);
        $this->assertEquals('0.00000000', $topGl->walletE->refresh()->balance);
    }

    /** @test */
    public function sponsor_bonus_pays_five_levels()
    {
        $l5 = $this->makeUser('l5', null, 'USER');
        $l4 = $this->makeUser('l4', $l5, 'USER');
        $l3 = $this->makeUser('l3', $l4, 'USER');
        $l2 = $this->makeUser('l2', $l3, 'USER');
        $l1 = $this->makeUser('l1', $l2, 'USER');
        $member = $this->makeUser('depositor', $l1, 'USER');

        app(SponsorBonusService::class)->distribute($member, 100);

        // [10,5,3,2,1]% of 100
        $this->assertEquals('10.00000000', $l1->walletE->refresh()->balance);
        $this->assertEquals('5.00000000', $l2->walletE->refresh()->balance);
        $this->assertEquals('3.00000000', $l3->walletE->refresh()->balance);
        $this->assertEquals('2.00000000', $l4->walletE->refresh()->balance);
        $this->assertEquals('1.00000000', $l5->walletE->refresh()->balance);
    }

    /** @test */
    public function roi_caps_at_200_percent_and_completes()
    {
        $user = $this->makeUser('investor', null, 'USER');
        // Give A-WALLET capital so activate() can lock it
        app(WalletService::class)->credit($user, 'A', 100, 'deposit');
        $package = app(InvestmentService::class)->activate($user, 100, 'deposit');

        $roi = app(RoiService::class);
        // 1% of 100 = 1/day, 200 total -> 200 days. Fast-forward by running 205 distinct dates.
        for ($d = 0; $d < 205; $d++) {
            $roi->runForDate(now()->subDays(205 - $d));
        }

        $package->refresh();
        $this->assertEquals('200.00000000', $package->total_paid);
        $this->assertEquals('completed', $package->status);
        $this->assertEquals('200.00000000', $user->walletE->refresh()->balance);
    }

    protected function stubPackage(User $user): int
    {
        return \App\Models\InvestmentPackage::create([
            'user_id' => $user->id, 'principal' => 100, 'total_return' => 200, 'total_paid' => 0,
            'daily_roi_percent' => 1, 'daily_amount' => 1, 'status' => 'active', 'source' => 'deposit',
            'activated_at' => now(),
        ])->id;
    }
}
