<?php

namespace Tests\Feature;

use App\Models\Rank;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TransferService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Carbon\Carbon;
use Database\Seeders\RankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WalletRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RankSeeder::class);
        // Freeze time inside the withdrawal window (Wed 10:00 Malaysia time).
        Carbon::setTestNow(Carbon::create(2026, 6, 10, 10, 0, 0, 'Asia/Kuala_Lumpur'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeUser(string $username): User
    {
        $user = User::create([
            'username' => $username, 'email' => "$username@test.com", 'password' => bcrypt('password'),
            'rank_id' => Rank::byName('USER')->id, 'referral_code' => strtoupper($username),
        ]);
        Wallet::create(['user_id' => $user->id, 'type' => 'A', 'balance' => 0]);
        Wallet::create(['user_id' => $user->id, 'type' => 'E', 'balance' => 0]);
        return $user;
    }

    /** @test */
    public function e_to_a_self_transfer_is_allowed()
    {
        $user = $this->makeUser('alice');
        app(WalletService::class)->credit($user, 'E', 50, 'roi');

        app(TransferService::class)->selfEtoA($user, 30);

        // 10% fee on 30 = 3 → A-WALLET receives 27; full 30 leaves E-WALLET.
        $this->assertEquals('20.00000000', $user->walletE->refresh()->balance);
        $this->assertEquals('27.00000000', $user->walletA->refresh()->balance);
    }

    /** @test */
    public function member_to_member_transfer_moves_a_wallet()
    {
        $from = $this->makeUser('bob');
        $to   = $this->makeUser('carol');
        app(WalletService::class)->credit($from, 'A', 100, 'deposit');

        app(TransferService::class)->memberToMember($from, 'carol', 40);

        $this->assertEquals('60.00000000', $from->walletA->refresh()->balance);
        $this->assertEquals('40.00000000', $to->walletA->refresh()->balance);
    }

    /** @test */
    public function withdrawal_over_daily_max_is_rejected()
    {
        $user = $this->makeUser('dave');
        app(WalletService::class)->credit($user, 'E', 5000, 'roi');

        $this->expectException(ValidationException::class);
        app(WithdrawalService::class)->request($user, 2000, 'addr'); // max 1000/day
    }

    /** @test */
    public function withdrawal_holds_and_refunds_on_reject()
    {
        $user  = $this->makeUser('erin');
        $admin = $this->makeUser('admin1');
        app(WalletService::class)->credit($user, 'E', 500, 'roi');

        $w = app(WithdrawalService::class)->request($user, 100, 'addr');
        // 100 held out of E-WALLET
        $this->assertEquals('400.00000000', $user->walletE->refresh()->balance);

        app(WithdrawalService::class)->reject($w, $admin, 'test');
        // refunded
        $this->assertEquals('500.00000000', $user->walletE->refresh()->balance);
    }
}
