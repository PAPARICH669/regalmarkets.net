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
        $q = User::with('rank:id,name,level')->withCount('referrals')->latest();
        if ($search = $request->query('search')) {
            $q->where(fn ($w) => $w->where('username', 'like', "%$search%")->orWhere('email', 'like', "%$search%"));
        }
        if ($request->boolean('frozen')) {
            $q->where('is_frozen', true);
        }
        return $q->paginate(20);
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
            'type'      => ['required', 'in:A,E'],
            'direction' => ['required', 'in:credit,debit'],
            'amount'    => ['required', 'numeric', 'min:0.00000001'],
            'note'      => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['direction'] === 'credit') {
            $this->wallets->credit($user, $data['type'], $data['amount'], 'admin_adjust', null, ['admin' => $request->user()->id], $data['note'] ?? 'Admin credit');
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
}
