<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The ONLY class allowed to mutate wallet balances. Every mutation appends one
 * row to wallet_transactions (append-only ledger) recording balance_after.
 *
 * Wallet types: 'A' = capital/package wallet, 'E' = earning wallet.
 */
class WalletService
{
    public function wallet(User $user, string $type): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'type' => $type],
            ['balance' => 0]
        );
    }

    public function balance(User $user, string $type): string
    {
        return $this->wallet($user, $type)->balance;
    }

    /**
     * Credit a wallet. Must be called inside a DB transaction by the caller for
     * multi-step operations; safe standalone for single credits.
     */
    public function credit(
        User $user,
        string $type,
        $amount,
        string $txnType,
        ?Model $reference = null,
        array $meta = [],
        ?string $note = null
    ): WalletTransaction {
        $amount = $this->normalize($amount);
        if (bccomp($amount, '0', 8) <= 0) {
            throw new RuntimeException('Credit amount must be positive.');
        }

        $wallet = $this->wallet($user, $type);
        $wallet->refresh();
        $wallet->lockForUpdate();

        $newBalance = bcadd($wallet->balance, $amount, 8);
        $wallet->update(['balance' => $newBalance]);

        return $this->record($wallet, $user, $type, $txnType, 'credit', $amount, $newBalance, $reference, $meta, $note);
    }

    /**
     * Debit a wallet. Throws if balance would go negative.
     */
    public function debit(
        User $user,
        string $type,
        $amount,
        string $txnType,
        ?Model $reference = null,
        array $meta = [],
        ?string $note = null
    ): WalletTransaction {
        $amount = $this->normalize($amount);
        if (bccomp($amount, '0', 8) <= 0) {
            throw new RuntimeException('Debit amount must be positive.');
        }

        $wallet = $this->wallet($user, $type);
        $wallet->refresh();
        $wallet->lockForUpdate();

        if (bccomp($wallet->balance, $amount, 8) < 0) {
            throw new RuntimeException("Insufficient {$type}-WALLET balance.");
        }

        $newBalance = bcsub($wallet->balance, $amount, 8);
        $wallet->update(['balance' => $newBalance]);

        return $this->record($wallet, $user, $type, $txnType, 'debit', $amount, $newBalance, $reference, $meta, $note);
    }

    protected function record($wallet, $user, $type, $txnType, $direction, $amount, $balanceAfter, $reference, $meta, $note): WalletTransaction
    {
        return WalletTransaction::create([
            'wallet_id'       => $wallet->id,
            'user_id'         => $user->id,
            'wallet_type'     => $type,
            'type'            => $txnType,
            'direction'       => $direction,
            'amount'          => $amount,
            'balance_after'   => $balanceAfter,
            'reference_type'  => $reference ? get_class($reference) : null,
            'reference_id'    => $reference?->getKey(),
            'meta'            => $meta ?: null,
            'note'            => $note,
        ]);
    }

    protected function normalize($amount): string
    {
        return number_format((float) $amount, 8, '.', '');
    }
}
