<?php

namespace Database\Seeders;

use App\Models\Deposit;
use App\Models\Rank;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\DepositService;
use App\Services\RankService;
use App\Services\RoiService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoNetworkSeeder extends Seeder
{
    protected DepositService $deposits;

    public function run(): void
    {
        $this->deposits = app(DepositService::class);
        $userRank = Rank::byName('USER');

        // ---- Admin -----------------------------------------------------------
        $this->makeUser('admin', 'admin@regalmarkets.net', null, $userRank, true);

        // ---- Demonstration vertical chain (showcases differential matching) --
        $gl     = $this->makeUser('groupleader', 'gl@regalmarkets.net', null, $userRank);
        $tl     = $this->makeUser('teamleader',  'tl@regalmarkets.net', $gl, $userRank);
        $senior = $this->makeUser('senior',      'senior@regalmarkets.net', $tl, $userRank);
        $fan    = $this->makeUser('fan',         'fan@regalmarkets.net', $senior, $userRank);
        $member = $this->makeUser('member',      'member@regalmarkets.net', $fan, $userRank);

        $this->deposit($gl, 6000);
        $this->deposit($tl, 800);
        $this->deposit($senior, 250);
        $this->deposit($fan, 150);
        $this->deposit($member, 100);

        // Broaden the tree under each so the network views have data
        $extra = [];
        foreach ([$gl, $tl, $senior, $fan] as $i => $parent) {
            for ($j = 1; $j <= 3; $j++) {
                $u = $this->makeUser("u{$i}{$j}_" . Str::lower(Str::random(3)), null, $parent, $userRank);
                $this->deposit($u, [120, 80, 200][$j % 3] + 20);
                $extra[] = $u;
            }
        }

        // Manually assign the showcase ranks (engine never demotes these).
        $this->setRank($gl, 'GROUP LEADER');
        $this->setRank($tl, 'TEAM LEADER');
        $this->setRank($senior, 'SENIOR');
        $this->setRank($fan, 'FAN');

        // Let the rank engine promote anyone who naturally qualifies too.
        app(RankService::class)->updateAll();

        // ---- Generate history: run ROI for the last 6 days -------------------
        $roi = app(RoiService::class);
        for ($d = 6; $d >= 1; $d--) {
            $roi->runForDate(Carbon::today()->subDays($d));
        }

        // ---- Pending items so admin screens have something to action ---------
        Deposit::create([
            'user_id' => $member->id, 'amount' => 75, 'txid' => 'TXDEMOPENDING01', 'status' => 'pending',
        ]);
        Deposit::create([
            'user_id' => $fan->id, 'amount' => 300, 'txid' => 'TXDEMOPENDING02', 'status' => 'pending',
        ]);

        // A pending withdrawal from someone with E-WALLET earnings
        app(\App\Services\WithdrawalService::class)->request($fan->fresh(), 20, 'TGLdemoWalletAddrXXXXXXXXXXXXX');

        $this->command?->info('Demo network seeded. Admin: admin@regalmarkets.net / password');
    }

    protected function makeUser(string $username, ?string $email, ?User $sponsor, Rank $rank, bool $admin = false): User
    {
        $user = User::create([
            'username'      => $username,
            'name'          => Str::title(str_replace('_', ' ', $username)),
            'email'         => $email ?? ($username . '@demo.regalmarkets.net'),
            'password'      => Hash::make('password'),
            'sponsor_id'    => $sponsor?->id,
            'rank_id'       => $rank->id,
            'referral_code' => strtoupper(Str::random(8)),
            'is_admin'      => $admin,
        ]);

        Wallet::create(['user_id' => $user->id, 'type' => 'A', 'balance' => 0]);
        Wallet::create(['user_id' => $user->id, 'type' => 'E', 'balance' => 0]);

        return $user;
    }

    protected function deposit(User $user, $amount): void
    {
        $deposit = $this->deposits->request($user, $amount, 'TX' . strtoupper(Str::random(10)));
        $admin   = User::where('is_admin', true)->first();
        $this->deposits->approve($deposit, $admin);
    }

    protected function setRank(User $user, string $rankName): void
    {
        $user->update(['rank_id' => Rank::byName($rankName)->id]);
    }
}
