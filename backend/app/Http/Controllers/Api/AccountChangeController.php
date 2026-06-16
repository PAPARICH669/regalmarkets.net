<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountChangeRequest;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Member-initiated changes to email / wallet_address. Each request must be
 * confirmed with an email TAC (sent to the CURRENT email) and then approved by
 * an admin/staff before it is applied. See AdminMemberController::approveChange.
 */
class AccountChangeController extends Controller
{
    /** The member's recent change requests (to show status on the profile page). */
    public function index(Request $request)
    {
        return AccountChangeRequest::where('user_id', $request->user()->id)
            ->latest()->take(10)->get();
    }

    /** Step 1: submit a new value, email a TAC to the current address. */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'field'     => ['required', 'in:email,wallet_address'],
            'new_value' => ['required', 'string', 'max:191'],
        ]);

        if ($data['field'] === 'email') {
            $request->validate([
                'new_value' => ['email', Rule::unique('users', 'email')->ignore($user->id)],
            ], [], ['new_value' => 'email']);
            if (strcasecmp(trim($data['new_value']), (string) $user->email) === 0) {
                throw ValidationException::withMessages(['new_value' => 'That is already your current email.']);
            }
        }

        // Only one open request per field — replace any existing pending one.
        AccountChangeRequest::where('user_id', $user->id)
            ->where('field', $data['field'])
            ->whereIn('status', ['pending_tac', 'pending'])
            ->delete();

        $code = (string) random_int(100000, 999999);
        $req = AccountChangeRequest::create([
            'user_id'        => $user->id,
            'field'          => $data['field'],
            'old_value'      => $data['field'] === 'email' ? $user->email : $user->wallet_address,
            'new_value'      => trim($data['new_value']),
            'status'         => 'pending_tac',
            'tac_code'       => $code,
            'tac_expires_at' => now()->addMinutes(15),
        ]);

        $this->sendTac($user, $code, $data['field']);

        return response()->json([
            'message'    => 'A confirmation code (TAC) has been sent to your current email.',
            'request_id' => $req->id,
        ], 201);
    }

    /** Step 2: confirm the TAC -> request becomes pending admin approval. */
    public function verifyTac(Request $request)
    {
        $data = $request->validate([
            'request_id' => ['required', 'integer'],
            'code'       => ['required', 'string'],
        ]);

        $req = AccountChangeRequest::where('id', $data['request_id'])
            ->where('user_id', $request->user()->id)->first();

        $valid = $req
            && $req->status === 'pending_tac'
            && $req->tac_code
            && $req->tac_expires_at
            && now()->lessThanOrEqualTo($req->tac_expires_at)
            && hash_equals($req->tac_code, trim($data['code']));

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        $req->update(['status' => 'pending', 'tac_code' => null, 'tac_verified_at' => now()]);

        return response()->json(['message' => 'Confirmed. Your request is now pending admin/staff approval.']);
    }

    /** Re-send the TAC for an open (pending_tac) request. */
    public function resendTac(Request $request)
    {
        $data = $request->validate(['request_id' => ['required', 'integer']]);
        $req  = AccountChangeRequest::where('id', $data['request_id'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending_tac')->first();

        if ($req) {
            $code = (string) random_int(100000, 999999);
            $req->update(['tac_code' => $code, 'tac_expires_at' => now()->addMinutes(15)]);
            $this->sendTac($request->user(), $code, $req->field);
        }

        return response()->json(['message' => 'If the request is still open, a new code has been sent.']);
    }

    protected function sendTac($user, string $code, string $field): void
    {
        $label = $field === 'email' ? 'email address' : 'USDT withdrawal address';
        try {
            $html = '<div style="font-family:Arial,sans-serif;background:#04102a;padding:32px;color:#eef3fc">'
                . '<div style="max-width:520px;margin:auto;background:#0a1c40;border:1px solid rgba(201,162,39,.3);border-radius:14px;padding:28px">'
                . '<h2 style="color:#e7c873;margin:0 0 8px">Regal Markets</h2>'
                . '<p style="margin:0 0 16px;color:#9fb1d4">You requested to change your <b>' . $label . '</b>. Your confirmation code (TAC) is:</p>'
                . '<p style="font-size:34px;font-weight:bold;letter-spacing:8px;color:#e7c873;margin:0 0 16px">' . $code . '</p>'
                . '<p style="margin:0;color:#9fb1d4;font-size:12px">This code expires in 15 minutes. After confirming, an admin will review your request. If you did not request this, ignore this email and change your password.</p>'
                . '</div></div>';
            app(MailService::class)->send($user->email, $user->username, 'Confirm your account change — Regal Markets', $html);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Account-change TAC email failed: ' . $e->getMessage());
        }
    }
}
