<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\MailService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * LD (Leader-Distributor) credit transfers from the LD WALLET (type 'L') into a
 * member's A-WALLET. Two steps, gated by the LD's own PASSWORD (no email TAC):
 *   1) init()    — validate + lookup recipient, stash the pending details server-side
 *   2) confirm() — re-enter password, then move LD WALLET -> recipient A-WALLET
 * The pending recipient/amount live on the server so what the LD confirmed on
 * screen is exactly what is executed. The member's own A-WALLET is never a source.
 */
class LdController extends Controller
{
    public function __construct(protected WalletService $wallets) {}

    protected function ensureLd(Request $request): void
    {
        abort_unless((bool) $request->user()->is_ld, 403, 'Not authorized.');
    }

    /** Step 1: validate + look up the recipient, stash the pending transfer. */
    public function init(Request $request)
    {
        $this->ensureLd($request);
        $data = $request->validate([
            'username' => ['required', 'string', 'exists:users,username'],
            'amount'   => ['required', 'numeric', 'min:1'],
        ], [], ['username' => 'username']);

        $ld     = $request->user();
        $target = User::where('username', $data['username'])->first();
        if ($target->id === $ld->id) {
            throw ValidationException::withMessages(['username' => 'You cannot transfer to yourself.']);
        }

        $amount = number_format((float) $data['amount'], 8, '.', '');
        if (bccomp($this->wallets->balance($ld, 'L'), $amount, 8) < 0) {
            throw ValidationException::withMessages(['amount' => 'Insufficient LD WALLET balance.']);
        }

        // Stash server-side so confirm() executes exactly what was shown on the
        // confirmation screen. No TAC — the LD's password is the gate.
        $ld->update([
            'ld_tac_code'       => null,
            'ld_tac_expires_at' => now()->addMinutes(15),
            'ld_tac_member_id'  => $target->id,
            'ld_tac_amount'     => $amount,
        ]);

        return response()->json([
            'message'   => 'Sila semak butiran dan masukkan kata laluan anda untuk sahkan.',
            'recipient' => ['id' => $target->id, 'username' => $target->username, 'name' => $target->name ?: $target->username],
            'amount'    => $amount,
        ]);
    }

    /** Step 2: verify the LD's password and execute the LD WALLET -> A-WALLET transfer. */
    public function confirm(Request $request)
    {
        $this->ensureLd($request);
        $data = $request->validate(['password' => ['required', 'string']]);
        $ld   = $request->user();

        if (! \Illuminate\Support\Facades\Hash::check($data['password'], $ld->password)) {
            throw ValidationException::withMessages(['password' => 'Kata laluan salah.']);
        }

        $pending = $ld->ld_tac_member_id
            && $ld->ld_tac_amount
            && $ld->ld_tac_expires_at
            && now()->lessThanOrEqualTo($ld->ld_tac_expires_at);

        if (! $pending) {
            throw ValidationException::withMessages(['password' => 'Sesi tamat tempoh. Sila mula semula.']);
        }

        $target = User::find($ld->ld_tac_member_id);
        $amount = number_format((float) $ld->ld_tac_amount, 8, '.', '');
        if (! $target) {
            throw ValidationException::withMessages(['password' => 'Recipient no longer exists.']);
        }
        if (bccomp($this->wallets->balance($ld, 'L'), $amount, 8) < 0) {
            throw ValidationException::withMessages(['password' => 'Insufficient LD WALLET balance.']);
        }

        $transfer = DB::transaction(function () use ($ld, $target, $amount) {
            $this->wallets->debit($ld, 'L', $amount, 'ld_transfer', null, ['to_user_id' => $target->id], 'LD transfer to @' . $target->username);
            $this->wallets->credit($target, 'A', $amount, 'ld_transfer', null, ['from_user_id' => $ld->id], 'LD credit from @' . $ld->username);
            $t = Transfer::create([
                'from_user_id' => $ld->id,
                'to_user_id'   => $target->id,
                'from_wallet'  => 'A', // ledger uses A/E; this is an LD WALLET -> A-WALLET move
                'to_wallet'    => 'A',
                'amount'       => $amount,
                'type'         => 'ld_transfer',
                'note'         => 'LD WALLET to A-WALLET',
            ]);
            $ld->update(['ld_tac_code' => null, 'ld_tac_expires_at' => null, 'ld_tac_member_id' => null, 'ld_tac_amount' => null]);
            return $t;
        });

        return response()->json([
            'message'  => 'Berjaya transfer ' . number_format((float) $amount, 2) . ' USDT ke A-WALLET ' . ($target->name ?: $target->username) . '.',
            'transfer' => $transfer,
        ], 201);
    }

    /** LD WALLET transaction history (top-ups in + transfers out) — username + amount. */
    public function walletHistory(Request $request)
    {
        $this->ensureLd($request);
        $rows = WalletTransaction::where('user_id', $request->user()->id)
            ->where('wallet_type', 'L')
            ->latest()->take(60)
            ->get(['id', 'type', 'direction', 'amount', 'note', 'meta', 'created_at']);

        return $rows->map(function ($t) {
            $who = null;
            if (is_array($t->meta) && ! empty($t->meta['to_user_id'])) {
                $who = optional(User::find($t->meta['to_user_id']))->username;
            }
            return [
                'id'         => $t->id,
                'direction'  => $t->direction,      // credit = top-up in, debit = transfer out
                'amount'     => $t->amount,
                'note'       => $t->note,
                'to_username'=> $who,
                'created_at' => $t->created_at,
            ];
        });
    }
}
