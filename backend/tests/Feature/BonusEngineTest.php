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

        // Earner is USER(1%). Floor starts at 1:
        // SENIOR 5% (6-1), TEAM LEADER 4% (10-6), GROUP LEADER 5% (15-10)
        $this->assertEquals('5.00000000', $senior->walletE->refresh()->balance);
        $this->assertEquals('4.00000000', $tl->walletE->refresh()->balance);
        $this->assertEquals('5.00000000', $gl->walletE->refresh()->balance);
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

        // Earner USER(1): gl2 gets 15-1=14; topGl is same rank as gl2 -> 15-15=0 -> cut.
        $this->assertEquals('14.00000000', $gl2->walletE->refresh()->balance);
        $this->assertEquals('0.00000000', $topGl->walletE->refresh()->balance);
    }

    /** @test */
    public function user_rank_earns_1pct_from_level_1_only()
    {
        // u3 ← u2 ← u1 ← earner, all USER rank.
        $u3     = $this->makeUser('u3', null, 'USER');
        $u2     = $this->makeUser('u2', $u3, 'USER');
        $u1     = $this->makeUser('u1', $u2, 'USER');
        $earner = $this->makeUser('eu', $u1, 'USER');

        $roiLog = RoiLog::create([
            'investment_package_id' => $this->stubPackage($earner),
            'user_id' => $earner->id, 'amount' => 100, 'roi_date' => now()->toDateString(),
        ]);
        app(MatchingBonusService::class)->distributeForRoi($roiLog);

        // Only the level-1 USER upline earns 1% (even same rank); level 2+ earn nothing.
        $this->assertEquals('1.00000000', $u1->walletE->refresh()->balance);
        $this->assertEquals('0.00000000', $u2->walletE->refresh()->balance);
        $this->assertEquals('0.00000000', $u3->walletE->refresh()->balance);
    }

    /** @test */
    public function rank_difference_per_direct_leg()
    {
        // Ali = GROUP LEADER, with direct downlines of varying ranks (the spec example).
        $ali   = $this->makeUser('ali', null, 'GROUP LEADER');
        $samad = $this->makeUser('samad', $ali, 'TEAM LEADER'); // 15-10 = 5
        $yusri = $this->makeUser('yusri', $ali, 'SENIOR');      // 15-6  = 9
        $muaz  = $this->makeUser('muaz', $ali, 'FAN');          // 15-3  = 12
        $nurul = $this->makeUser('nurul', $ali, 'USER');        // 15-1  = 14

        foreach ([$samad, $yusri, $muaz, $nurul] as $earner) {
            $roiLog = RoiLog::create([
                'investment_package_id' => $this->stubPackage($earner),
                'user_id' => $earner->id, 'amount' => 100, 'roi_date' => now()->toDateString(),
            ]);
            app(MatchingBonusService::class)->distributeForRoi($roiLog);
        }

        // Ali earns 5 + 9 + 12 + 14 = 40 USDT total
        $this->assertEquals('40.00000000', $ali->walletE->refresh()->balance);
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

        // All uplines have their own fund -> eligible for every level.
        foreach ([$l1, $l2, $l3, $l4, $l5] as $u) {
            $u->update(['total_invested' => 100]);
        }

        app(SponsorBonusService::class)->distribute($member, 100);

        // [7,4,2,1,1]% of 100
        $this->assertEquals('7.00000000', $l1->walletE->refresh()->balance);
        $this->assertEquals('4.00000000', $l2->walletE->refresh()->balance);
        $this->assertEquals('2.00000000', $l3->walletE->refresh()->balance);
        $this->assertEquals('1.00000000', $l4->walletE->refresh()->balance);
        $this->assertEquals('1.00000000', $l5->walletE->refresh()->balance);
    }

    /** @test */
    public function sponsor_bonus_no_fund_upline_earns_level_1_only()
    {
        // No upline has invested anything (total_invested = 0).
        $l3 = $this->makeUser('nf3', null, 'USER');
        $l2 = $this->makeUser('nf2', $l3, 'USER');
        $l1 = $this->makeUser('nf1', $l2, 'USER');
        $member = $this->makeUser('nfdep', $l1, 'USER');

        app(SponsorBonusService::class)->distribute($member, 100);

        // Level 1 pays even without fund (7%); levels 2+ require fund -> nothing.
        $this->assertEquals('7.00000000', $l1->walletE->refresh()->balance);
        $this->assertEquals('0.00000000', $l2->walletE->refresh()->balance);
        $this->assertEquals('0.00000000', $l3->walletE->refresh()->balance);
    }

    /** @test */
    public function sponsor_bonus_skips_only_the_unfunded_upline()
    {
        // l1 funded, l2 NOT funded, l3 funded -> l2 skipped (lvl2), l3 still paid (lvl3).
        $l3 = $this->makeUser('mx3', null, 'USER');
        $l2 = $this->makeUser('mx2', $l3, 'USER');
        $l1 = $this->makeUser('mx1', $l2, 'USER');
        $member = $this->makeUser('mxdep', $l1, 'USER');
        $l1->update(['total_invested' => 50]);
        $l3->update(['total_invested' => 50]);

        app(SponsorBonusService::class)->distribute($member, 100);

        $this->assertEquals('7.00000000', $l1->walletE->refresh()->balance); // lvl1
        $this->assertEquals('0.00000000', $l2->walletE->refresh()->balance); // lvl2, no fund
        $this->assertEquals('2.00000000', $l3->walletE->refresh()->balance); // lvl3, funded
    }

    /** @test */
    public function roi_caps_at_200_percent_and_completes()
    {
        $user = $this->makeUser('investor', null, 'USER');
        // Give A-WALLET capital so activate() can lock it
        app(WalletService::class)->credit($user, 'A', 100, 'deposit');
        $package = app(InvestmentService::class)->activate($user, 100, 'deposit');
        // Commission starts the day AFTER funding, so backdate activation before the run window.
        $package->update(['activated_at' => now()->subDays(206)]);

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

    /** @test */
    public function commission_starts_the_day_after_funding()
    {
        $user = $this->makeUser('latestart', null, 'USER');
        app(WalletService::class)->credit($user, 'A', 100, 'deposit');
        app(InvestmentService::class)->activate($user, 100, 'deposit'); // funded "today"

        $roi = app(RoiService::class);

        // Same day as funding: NO commission yet.
        $roi->runForDate(now());
        $this->assertEquals('0.00000000', $user->walletE->refresh()->balance);

        // Next day: first commission paid (1% of 100 = 1).
        $roi->runForDate(now()->addDay());
        $this->assertEquals('1.00000000', $user->walletE->refresh()->balance);
    }

    /** @test */
    public function total_fund_drops_when_package_completes_200_percent()
    {
        $user = $this->makeUser('funder', null, 'USER');
        $user->update(['total_fund' => 100]);
        app(WalletService::class)->credit($user, 'A', 100, 'deposit');
        $package = app(InvestmentService::class)->activate($user, 100, 'fund');
        $package->update(['activated_at' => now()->subDays(206)]);

        $roi = app(RoiService::class);
        for ($d = 0; $d < 205; $d++) {
            $roi->runForDate(now()->subDays(205 - $d));
        }

        $this->assertEquals('completed', $package->refresh()->status);
        // The completed package's principal (100) is removed from total_fund.
        $this->assertEquals('0.00000000', $user->fresh()->total_fund);
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
