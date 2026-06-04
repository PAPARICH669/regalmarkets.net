<?php

namespace Tests\Feature;

use App\Models\Rank;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ReinvestService;
use App\Services\TransferService;
use App\Services\WalletService;
use Database\Seeders\RankSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferReinvestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RankSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    protected function makeUser(string $username): User
    {
        $user = User::create([
            'username'      => $username,
            'email'         => "$username@test.com",
            'password'      => bcrypt('password'),
            'rank_id'       => Rank::byName('USER')->id,
            'referral_code' => strtoupper($username),
        ]);
        Wallet::create(['user_id' => $user->id, 'type' => 'A', 'balance' => 0]);
        Wallet::create(['user_id' => $user->id, 'type' => 'E', 'balance' => 0]);
        return $user;
    }

    /** @test */
    public function self_transfer_charges_10_percent_fee()
    {
        $user = $this->makeUser('mover');
        app(WalletService::class)->credit($user, 'E', 100, 'roi');

        $transfer = app(TransferService::class)->selfEtoA($user, 100);

        // 10% fee on 100 => 10 fee, 90 net into A-WALLET, E-WALLET emptied.
        $this->assertEquals('10.00000000', $transfer->fee);
        $this->assertEquals('90.00000000', $transfer->net_amount);
        $this->assertEquals('0.00000000', $user->walletE->refresh()->balance);
        $this->assertEquals('90.00000000', $user->walletA->refresh()->balance);
    }

    /** @test */
    public function reinvest_uses_a_wallet_only()
    {
        $user = $this->makeUser('compounder');
        // E-WALLET funds alone must NOT be reinvestable.
        app(WalletService::class)->credit($user, 'E', 100, 'roi');

        try {
            app(ReinvestService::class)->reinvest($user, 100);
            $this->fail('Reinvest should fail without A-WALLET funds.');
        } catch (\Throwable $e) {
            // expected: insufficient A-WALLET balance (E-WALLET cannot be reinvested directly)
        }

        // Fund A-WALLET and reinvest succeeds, debiting A-WALLET.
        app(WalletService::class)->credit($user, 'A', 100, 'deposit');
        $package = app(ReinvestService::class)->reinvest($user, 100);

        $this->assertEquals('100.00000000', $package->principal);
        $this->assertEquals('reinvest', $package->source);
        $this->assertEquals('0.00000000', $user->walletA->refresh()->balance);
        $this->assertEquals('100.00000000', $user->walletE->refresh()->balance); // untouched
    }
}
