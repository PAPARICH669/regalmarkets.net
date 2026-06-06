<?php

namespace Tests\Feature;

use App\Models\Rank;
use App\Models\User;
use App\Models\Wallet;
use App\Services\FundService;
use App\Services\WalletService;
use Database\Seeders\RankSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundTest extends TestCase
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
    public function fund_uses_a_wallet_only()
    {
        $user = $this->makeUser('compounder');
        // E-WALLET funds alone must NOT be fundable (E-WALLET is withdrawal-only).
        app(WalletService::class)->credit($user, 'E', 100, 'roi');

        try {
            app(FundService::class)->fund($user, 100);
            $this->fail('Fund should fail without A-WALLET funds.');
        } catch (\Throwable $e) {
            // expected: insufficient A-WALLET balance
        }

        // Fund A-WALLET and the fund succeeds, debiting A-WALLET only.
        app(WalletService::class)->credit($user, 'A', 100, 'deposit');
        $package = app(FundService::class)->fund($user, 100);

        $this->assertEquals('100.00000000', $package->principal);
        $this->assertEquals('fund', $package->source);
        $this->assertEquals('0.00000000', $user->walletA->refresh()->balance);
        $this->assertEquals('100.00000000', $user->walletE->refresh()->balance); // untouched
    }
}
