<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\BscVerifier;
use App\Services\DepositService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DepositController extends Controller
{
    public function __construct(protected DepositService $deposits, protected SettingsService $settings) {}

    public function index(Request $request)
    {
        return $request->user()->deposits()->latest()->paginate(15);
    }

    /** Auto-deposit is active only when enabled AND an API key is configured. */
    protected function autoEnabled(BscVerifier $verifier): bool
    {
        return (bool) config('regal.deposit.auto_verify') && $verifier->isConfigured();
    }

    public function store(Request $request, BscVerifier $verifier)
    {
        return $this->autoEnabled($verifier)
            ? $this->storeAuto($request, $verifier)
            : $this->storeManual($request);
    }

    // -------------------------------------------------------------------------
    // Cadangan A — verify the TX hash on-chain and credit automatically.
    // Member submits ONLY the transaction hash; the amount is read from chain.
    // -------------------------------------------------------------------------
    protected function storeAuto(Request $request, BscVerifier $verifier)
    {
        $data = $request->validate([
            'tx_hash' => ['required', 'string', 'regex:/^0x[0-9a-fA-F]{64}$/'],
        ], [
            'tx_hash.regex'    => 'Enter a valid transaction hash (0x… , 66 characters).',
            'tx_hash.required' => 'The transaction hash is required.',
        ]);

        $hash = strtolower(trim($data['tx_hash']));

        // Double-claim guard #1 (fast path). The DB unique index on tx_hash is
        // the authoritative guard against races.
        if (Deposit::where('tx_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['tx_hash' => 'This transaction hash has already been used.']);
        }

        $r = $verifier->verify($hash);

        if ($r['error'] === 'deposit_address_unset') {
            throw ValidationException::withMessages(['tx_hash' => 'Deposits are temporarily unavailable. Please contact support.']);
        }
        if ($r['error'] === 'api_error') {
            // Transient: accept as verifying, the scheduler will re-check.
            return $this->createVerifying($request, $hash, 'Awaiting blockchain verification.');
        }
        if (! $r['found']) {
            // Possibly just not indexed yet — keep verifying and re-check later.
            return $this->createVerifying($request, $hash, 'Transaction not found yet — verifying.');
        }
        if (! $r['success']) {
            throw ValidationException::withMessages(['tx_hash' => 'This transaction failed on-chain.']);
        }
        if (! $r['matched'] || bccomp($r['amount'], '0', 8) <= 0) {
            throw ValidationException::withMessages(['tx_hash' => 'This is not a USDT (BEP20) transfer to our deposit address.']);
        }

        $min = (float) $this->settings->get('min_deposit');
        if ((float) $r['amount'] < $min) {
            throw ValidationException::withMessages(['tx_hash' => "The deposit is below the minimum of {$min} USDT."]);
        }

        // Decide the landing state.
        $flag = $this->reviewReason($request->user()->id, $r);
        $confirmed = $r['confirmations'] >= (int) config('regal.deposit.min_confirmations');

        // Persist as review (flagged) or verifying (to be credited). NOTE: never
        // persist as 'approved' here — confirmAuto()/credit() short-circuit on an
        // already-approved row (double-credit guard), so it must flip a
        // verifying → approved itself, which is also what does the crediting.
        $deposit = $this->persist($request, $hash, $r, $flag ? 'review' : 'verifying', $flag);

        if ($flag) {
            $this->notify($deposit, 'review: ' . $flag);
            $msg = 'Deposit received (' . number_format((float) $deposit->amount, 2) . ' USDT). It needs a quick manual check.';
        } elseif ($confirmed) {
            $deposit = $this->deposits->confirmAuto($deposit);
            $this->notify($deposit, 'auto-credited');
            $msg = 'Deposit of ' . number_format((float) $deposit->amount, 2) . ' USDT credited to your A-Wallet.';
        } else {
            $msg = 'Deposit found (' . number_format((float) $deposit->amount, 2) . ' USDT). Waiting for confirmations — it will credit automatically.';
        }

        return response()->json(['message' => $msg, 'deposit' => $deposit], 201);
    }

    /** Create a bare verifying record when the chain data is not ready yet. */
    protected function createVerifying(Request $request, string $hash, string $note)
    {
        $deposit = Deposit::create([
            'user_id' => $request->user()->id,
            'amount'  => 0,
            'tx_hash' => $hash,
            'txid'    => $hash,
            'status'  => 'verifying',
            'note'    => $note,
        ]);

        return response()->json([
            'message' => 'Deposit submitted. We are verifying it on the blockchain — it will credit automatically.',
            'deposit' => $deposit,
        ], 201);
    }

    protected function persist(Request $request, string $hash, array $r, string $status, ?string $note): Deposit
    {
        return Deposit::create([
            'user_id'       => $request->user()->id,
            'amount'        => $r['amount'],
            'tx_hash'       => $hash,
            'txid'          => $hash,
            'from_address'  => $r['from'],
            'block_number'  => $r['block'] ?: null,
            'confirmations' => $r['confirmations'],
            'status'        => $status,
            'note'          => $note,
        ]);
    }

    /**
     * Return a non-null reason to route a verified deposit to manual review:
     *  - the TX is older than the recency window (guards claiming a stranger's
     *    old unclaimed deposit), or
     *  - the sender address has already been used by several other accounts.
     */
    protected function reviewReason(int $userId, array $r): ?string
    {
        // ~1 BSC block / 3s → blocks per recency window.
        $days = (int) config('regal.deposit.recency_days');
        if ($days > 0 && $r['confirmations'] > $days * 28800) {
            return 'transaction older than ' . $days . ' days';
        }

        if ($r['from']) {
            $others = Deposit::where('from_address', $r['from'])
                ->where('user_id', '!=', $userId)
                ->distinct()->count('user_id');
            if ($others >= (int) config('regal.deposit.review_from_reuse')) {
                return 'sender used on other accounts';
            }
        }

        return null;
    }

    protected function notify(Deposit $deposit, string $kind): void
    {
        app(\App\Services\TelegramService::class)->notify('💰 Auto Deposit (' . $kind . ')', [
            'User'    => '@' . $deposit->user->username,
            'Amount'  => number_format((float) $deposit->amount, 2) . ' USDT',
            'TX'      => $deposit->tx_hash,
            'Conf'    => (string) $deposit->confirmations,
        ]);
    }

    // -------------------------------------------------------------------------
    // Manual deposit (default) — amount + proof image, admin approves.
    // -------------------------------------------------------------------------
    protected function storeManual(Request $request)
    {
        $min = (float) $this->settings->get('min_deposit');

        $data = $request->validate([
            'amount' => ['required', 'numeric', "min:{$min}"],
            'txid'   => ['nullable', 'string', 'max:191'],
            'proof'  => ['required', 'image', 'max:20480'],
        ], [
            'proof.required' => 'A payment proof image is required for every deposit.',
            'proof.image'    => 'The payment proof must be an image (JPG/PNG).',
        ]);

        $proofPath = $request->file('proof')->store('proofs', config('regal.proof_disk', 'local'));

        $deposit = $this->deposits->request($request->user(), $data['amount'], $data['txid'] ?? null, $proofPath);

        app(\App\Services\TelegramService::class)->notify('💰 New Deposit Request', [
            'User'   => '@' . $request->user()->username,
            'Amount' => number_format((float) $deposit->amount, 2) . ' USDT',
            'TXID'   => $deposit->txid ?? '—',
        ]);

        return response()->json([
            'message' => 'Deposit submitted and pending admin approval.',
            'deposit' => $deposit,
        ], 201);
    }
}
