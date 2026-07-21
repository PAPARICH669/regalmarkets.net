<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rank;
use App\Models\RankHistory;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NetworkService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class AdminMemberController extends Controller
{
    public function __construct(
        protected WalletService $wallets,
        protected NetworkService $network,
        protected AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $q = User::with(['rank:id,name,level', 'sponsor:id,username'])->withCount('referrals')->latest();
        if ($search = $request->query('search')) {
            $q->where(fn ($w) => $w->where('username', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%"));
        }
        if ($request->boolean('frozen')) {
            $q->where('is_frozen', true);
        }
        return $q->paginate(20);
    }

    /** Pending counts for the admin/staff notification badges (verify + approvals). */
    public function pendingCounts()
    {
        return response()->json([
            'deposits'        => \App\Models\Deposit::where('status', 'pending')->count(),
            'withdrawals'     => \App\Models\Withdrawal::where('status', 'pending')->count(),
            'kyc'             => User::where('kyc_status', 'pending')->count(),
            'change_requests' => \App\Models\AccountChangeRequest::where('status', 'pending')->count(),
            'sponsor_rewards' => \App\Models\SponsorReward::where('status', 'pending')->count(),
        ]);
    }

    /** Pending member-initiated email / wallet-address change requests. */
    public function changeRequests()
    {
        return \App\Models\AccountChangeRequest::with('user:id,username,email')
            ->where('status', 'pending')->latest()->get();
    }

    /** Approve a change request and apply it to the member. */
    public function approveChange(Request $request, \App\Models\AccountChangeRequest $changeRequest)
    {
        if ($changeRequest->status !== 'pending') {
            return response()->json(['message' => 'This request was already processed.'], 422);
        }
        $user = $changeRequest->user;
        if (! $user) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        if ($changeRequest->field === 'email') {
            $exists = User::where('email', $changeRequest->new_value)->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return response()->json(['message' => 'That email is already in use by another member.'], 422);
            }
            $user->update(['email' => $changeRequest->new_value]);
        } elseif ($changeRequest->field === 'phone') {
            $user->update(['phone' => $changeRequest->new_value]);
        } else { // wallet_address
            $user->update(['wallet_address' => $changeRequest->new_value]);
        }

        $changeRequest->update([
            'status'       => 'approved',
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);
        $this->audit->log($request, 'change_request.approve', $user, ['field' => $changeRequest->field]);

        return response()->json(['message' => 'Change approved and applied.']);
    }

    /** Reject a change request. */
    public function rejectChange(Request $request, \App\Models\AccountChangeRequest $changeRequest)
    {
        if ($changeRequest->status !== 'pending') {
            return response()->json(['message' => 'This request was already processed.'], 422);
        }
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $changeRequest->update([
            'status'       => 'rejected',
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
            'note'         => $data['note'] ?? null,
        ]);
        $this->audit->log($request, 'change_request.reject', $changeRequest->user, ['field' => $changeRequest->field]);

        return response()->json(['message' => 'Change request rejected.']);
    }

    public function show(User $user)
    {
        $user->load('rank', 'wallets', 'sponsor:id,username');
        return response()->json([
            'user'    => $user,
            'wallets' => ['A' => (float) $user->walletBalance('A'), 'E' => (float) $user->walletBalance('E')],
            'stats'   => $this->network->stats($user),
        ]);
    }

    public function freeze(Request $request, User $user)
    {
        $user->update(['is_frozen' => ! $user->is_frozen]);
        $this->audit->log($request, 'member.freeze', $user, ['frozen' => $user->is_frozen]);
        return response()->json(['message' => $user->is_frozen ? 'Member frozen.' : 'Member unfrozen.', 'user' => $user]);
    }

    public function adjustWallet(Request $request, User $user)
    {
        $data = $request->validate([
            'type'      => ['required', 'in:A,E,L'],
            'direction' => ['required', 'in:credit,debit'],
            'amount'    => ['required', 'numeric', 'min:0.00000001'],
            'note'      => ['nullable', 'string', 'max:255'],
            'free'      => ['nullable', 'boolean'],
        ]);

        // A "free" credit (promo/gift) is recorded as type `free_credit` so it is
        // NEVER counted as a real deposit in dashboard/report totals. The wallet
        // balance still increases. Only applies to credits.
        $isFree = $data['direction'] === 'credit' && ! empty($data['free']);

        if ($data['direction'] === 'credit') {
            $this->wallets->credit($user, $data['type'], $data['amount'], $isFree ? 'free_credit' : 'admin_adjust',
                null, ['admin' => $request->user()->id, 'free' => $isFree], $data['note'] ?? ($isFree ? 'Free credit' : 'Admin credit'));
        } else {
            $this->wallets->debit($user, $data['type'], $data['amount'], 'admin_adjust', null, ['admin' => $request->user()->id], $data['note'] ?? 'Admin debit');
        }

        $this->audit->log($request, 'member.adjust_wallet', $user, $data);
        return response()->json(['message' => 'Wallet adjusted.']);
    }

    public function editRank(Request $request, User $user)
    {
        $data = $request->validate(['rank_id' => ['required', 'exists:ranks,id']]);
        $from = $user->rank_id;
        $user->update(['rank_id' => $data['rank_id']]);
        RankHistory::create([
            'user_id' => $user->id, 'from_rank_id' => $from, 'to_rank_id' => $data['rank_id'], 'reason' => 'admin manual',
        ]);
        $this->audit->log($request, 'member.edit_rank', $user, $data);
        return response()->json(['message' => 'Rank updated.', 'user' => $user->fresh('rank')]);
    }

    public function tree(User $user, NetworkService $network)
    {
        return response()->json($network->tree($user, 6));
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        // Use the provided password, or generate a temporary one to hand to the member.
        $newPassword = $data['password'] ?? (\Illuminate\Support\Str::random(10));
        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($newPassword)]);
        $user->tokens()->delete(); // log the member out everywhere

        $this->audit->log($request, 'member.reset_password', $user);

        return response()->json([
            'message'      => 'Password reset. Share the new password with the member securely.',
            'new_password' => $newPassword,
        ]);
    }

    /** Admin edits a member's email / phone (members cannot change these themselves). */
    public function editContact(Request $request, User $user)
    {
        $data = $request->validate([
            'email' => ['required', 'email', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', \Illuminate\Validation\Rule::unique('users', 'phone')->ignore($user->id)],
        ]);
        $user->update($data);
        $this->audit->log($request, 'member.edit_contact', $user, $data);
        return response()->json(['message' => 'Contact details updated.', 'user' => $user]);
    }

    /** Admin sets/changes a member's USDT (BEP20) withdrawal address. Admin only. */
    public function updateWalletAddress(Request $request, User $user)
    {
        $data = $request->validate([
            'wallet_address' => ['nullable', 'string', 'max:120'],
        ]);
        $user->update(['wallet_address' => $data['wallet_address'] ?? null]);
        $this->audit->log($request, 'member.edit_wallet_address', $user, $data);
        return response()->json(['message' => 'Withdrawal address updated.', 'user' => $user]);
    }

    /** Grant/revoke limited staff access (KYC + edit profile). Admin only. */
    public function toggleStaff(Request $request, User $user)
    {
        if ($user->is_admin) {
            return response()->json(['message' => 'Cannot change staff role of an admin.'], 422);
        }
        $user->update(['is_staff' => ! $user->is_staff]);
        $this->audit->log($request, 'member.toggle_staff', $user, ['is_staff' => $user->is_staff]);
        return response()->json([
            'message' => $user->is_staff ? 'Staff access granted.' : 'Staff access revoked.',
            'user'    => $user,
        ]);
    }

    /** Assign/remove the KYC duty. The member stays a full member. Admin only. */
    public function toggleCanKyc(Request $request, User $user)
    {
        if ($user->is_admin) {
            return response()->json(['message' => 'Cannot assign a duty to an admin.'], 422);
        }
        $user->can_kyc  = ! $user->can_kyc;
        $user->is_staff = $user->can_kyc || $user->can_cr;
        $user->save();
        $this->audit->log($request, 'member.toggle_can_kyc', $user, ['can_kyc' => $user->can_kyc]);
        return response()->json(['message' => $user->can_kyc ? 'KYC duty assigned.' : 'KYC duty removed.', 'user' => $user]);
    }

    /** Assign/remove the Change Request duty. The member stays a full member. Admin only. */
    public function toggleCanCr(Request $request, User $user)
    {
        if ($user->is_admin) {
            return response()->json(['message' => 'Cannot assign a duty to an admin.'], 422);
        }
        $user->can_cr   = ! $user->can_cr;
        $user->is_staff = $user->can_kyc || $user->can_cr;
        $user->save();
        $this->audit->log($request, 'member.toggle_can_cr', $user, ['can_cr' => $user->can_cr]);
        return response()->json(['message' => $user->can_cr ? 'Change Request duty assigned.' : 'Change Request duty removed.', 'user' => $user]);
    }

    /** Grant/revoke LD (Leader-Distributor) role. Revoking returns them to a normal member. Admin only. */
    public function toggleLd(Request $request, User $user)
    {
        if ($user->is_admin) {
            return response()->json(['message' => 'Cannot make an admin an LD.'], 422);
        }
        $user->update(['is_ld' => ! $user->is_ld]);
        $this->audit->log($request, 'member.toggle_ld', $user, ['is_ld' => $user->is_ld]);
        return response()->json([
            'message' => $user->is_ld ? 'LD role granted.' : 'LD role revoked.',
            'user'    => $user,
        ]);
    }

    /** History of admin wallet adjustments (credit/debit) — id, amount, wallet, note. */
    public function walletAdjustments()
    {
        return \App\Models\WalletTransaction::whereIn('type', ['admin_adjust', 'free_credit'])
            ->with('user:id,username')
            ->latest()->take(100)
            ->get(['id', 'user_id', 'wallet_type', 'type', 'direction', 'amount', 'note', 'created_at']);
    }

    /** List KYC submissions (default pending). */
    public function kycList(Request $request)
    {
        $status = $request->query('status', 'pending');
        return User::where('kyc_status', $status)
            ->select('id', 'username', 'name', 'email', 'phone', 'kyc_country', 'id_type', 'id_number', 'kyc_status', 'kyc_document_path', 'kyc_selfie_path', 'kyc_note', 'updated_at')
            ->latest('updated_at')->paginate(20);
    }

    public function verifyKyc(Request $request, User $user)
    {
        $user->update([
            'kyc_status'      => 'verified',
            'kyc_verified_at' => now(),
            'kyc_verified_by' => $request->user()->id,
            'kyc_note'        => null,
        ]);
        $this->audit->log($request, 'member.kyc_verify', $user);

        // Notify the member by email (failure must not block the approval).
        try {
            app(\App\Services\MailService::class)->sendKycVerified($user->email, $user->name ?: $user->username);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('KYC verified email failed: ' . $e->getMessage());
        }

        return response()->json(['message' => 'KYC verified.']);
    }

    public function rejectKyc(Request $request, User $user)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $note = $data['note'] ?? 'Rejected by admin';
        $user->update(['kyc_status' => 'rejected', 'kyc_note' => $note]);
        $this->audit->log($request, 'member.kyc_reject', $user);

        // Notify the member by email (failure must not block the rejection).
        try {
            app(\App\Services\MailService::class)->sendKycRejected($user->email, $user->name ?: $user->username, $note);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('KYC rejected email failed: ' . $e->getMessage());
        }

        return response()->json(['message' => 'KYC rejected.']);
    }

    /** Stream a member's KYC document (admin only, private disk). */
    public function kycDocument(User $user)
    {
        abort_unless($user->kyc_document_path, 404);
        $disk = \Illuminate\Support\Facades\Storage::disk(config('regal.proof_disk', 'local'));
        abort_unless($disk->exists($user->kyc_document_path), 404);
        return $disk->response($user->kyc_document_path);
    }

    /** Stream a member's KYC selfie for the manual face-check (admin only). */
    public function kycSelfie(User $user)
    {
        abort_unless($user->kyc_selfie_path, 404);
        $disk = \Illuminate\Support\Facades\Storage::disk(config('regal.proof_disk', 'local'));
        abort_unless($disk->exists($user->kyc_selfie_path), 404);
        return $disk->response($user->kyc_selfie_path);
    }
}
