<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends transactional email via the Brevo HTTP API (https://api.brevo.com).
 *
 * We use the HTTP API (port 443) instead of SMTP because the VPS blocks
 * outbound SMTP ports (25/465/587). Configure BREVO_API_KEY + a verified
 * sender (MAIL_FROM_ADDRESS) in .env.
 */
class MailService
{
    public function send(string $toEmail, ?string $toName, string $subject, string $html): bool
    {
        $key = config('services.brevo.key');
        if (empty($key)) {
            Log::warning('Brevo API key not set; email not sent to ' . $toEmail);
            return false;
        }

        $response = Http::withHeaders([
            'api-key'      => $key,
            'accept'       => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender'      => [
                'name'  => config('mail.from.name', 'Regal Markets'),
                'email' => config('mail.from.address'),
            ],
            'to'          => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
            'subject'     => $subject,
            'htmlContent' => $html,
        ]);

        if (! $response->successful()) {
            Log::warning('Brevo send failed (' . $response->status() . '): ' . $response->body());
            throw new RuntimeException('Brevo send failed: ' . $response->status());
        }

        return true;
    }

    /** Branded password-reset email. */
    public function sendPasswordReset(string $toEmail, ?string $toName, string $resetUrl): bool
    {
        $html = <<<HTML
        <div style="font-family:Arial,sans-serif;background:#04102a;padding:32px;color:#eef3fc">
          <div style="max-width:520px;margin:auto;background:#0a1c40;border:1px solid rgba(201,162,39,.3);border-radius:14px;padding:28px">
            <h2 style="color:#e7c873;margin:0 0 8px">Regal Markets</h2>
            <p style="margin:0 0 16px;color:#9fb1d4">We received a request to reset your password.</p>
            <p style="margin:0 0 22px">Click the button below to set a new password. This link expires in 60 minutes.</p>
            <a href="{$resetUrl}" style="display:inline-block;background:linear-gradient(120deg,#c9a227,#e7c873);color:#1a1405;font-weight:bold;text-decoration:none;padding:12px 22px;border-radius:8px">Reset Password</a>
            <p style="margin:22px 0 0;color:#9fb1d4;font-size:12px">If you didn't request this, you can safely ignore this email.</p>
            <p style="margin:6px 0 0;color:#6b7a99;font-size:12px;word-break:break-all">{$resetUrl}</p>
          </div>
        </div>
        HTML;

        return $this->send($toEmail, $toName, 'Reset your Regal Markets password', $html);
    }

    /** Wrap inner HTML in the branded card shell. */
    protected function shell(string $heading, string $inner): string
    {
        return <<<HTML
        <div style="font-family:Arial,sans-serif;background:#04102a;padding:32px;color:#eef3fc">
          <div style="max-width:520px;margin:auto;background:#0a1c40;border:1px solid rgba(201,162,39,.3);border-radius:14px;padding:28px">
            <h2 style="color:#e7c873;margin:0 0 4px">Regal Markets</h2>
            <h3 style="color:#eef3fc;margin:0 0 16px">{$heading}</h3>
            {$inner}
            <p style="margin:22px 0 0;color:#6b7a99;font-size:12px">This is an automated message from Regal Markets. Please do not reply.</p>
          </div>
        </div>
        HTML;
    }

    /** KYC approved notification. */
    public function sendKycVerified(string $toEmail, ?string $toName): bool
    {
        $name  = $toName ?: 'there';
        $inner = <<<HTML
        <p style="margin:0 0 14px;color:#9fb1d4">Hi {$name},</p>
        <p style="margin:0 0 14px">✅ Great news — your <b style="color:#e7c873">KYC verification</b> has been <b>approved</b>.</p>
        <p style="margin:0 0 14px;color:#9fb1d4">Your account is now fully verified. You can deposit, fund, and withdraw without restrictions.</p>
        HTML;

        return $this->send($toEmail, $toName, 'Your KYC is verified — Regal Markets', $this->shell('KYC Verified', $inner));
    }

    /** Withdrawal approved/paid notification. */
    public function sendWithdrawalApproved(string $toEmail, ?string $toName, string $amount, string $net, string $address, ?string $txid = null): bool
    {
        $name   = $toName ?: 'there';
        $txRow  = $txid ? "<tr><td style=\"padding:4px 0;color:#9fb1d4\">TxID</td><td style=\"padding:4px 0;text-align:right;word-break:break-all\">{$txid}</td></tr>" : '';
        $inner  = <<<HTML
        <p style="margin:0 0 14px;color:#9fb1d4">Hi {$name},</p>
        <p style="margin:0 0 14px">🏧 Your withdrawal has been <b style="color:#e7c873">approved and processed</b>.</p>
        <table style="width:100%;border-collapse:collapse;margin:0 0 8px;font-size:14px">
          <tr><td style="padding:4px 0;color:#9fb1d4">Amount</td><td style="padding:4px 0;text-align:right">{$amount} USDT</td></tr>
          <tr><td style="padding:4px 0;color:#9fb1d4">Received (net)</td><td style="padding:4px 0;text-align:right;color:#e7c873;font-weight:bold">{$net} USDT</td></tr>
          <tr><td style="padding:4px 0;color:#9fb1d4">To address</td><td style="padding:4px 0;text-align:right;word-break:break-all">{$address}</td></tr>
          {$txRow}
        </table>
        <p style="margin:0 0 14px;color:#9fb1d4">Funds were sent to your BEP20 (BSC) USDT address. Allow a few minutes for on-chain confirmation.</p>
        HTML;

        return $this->send($toEmail, $toName, 'Withdrawal approved — Regal Markets', $this->shell('Withdrawal Successful', $inner));
    }

    /** Deposit approved notification. */
    public function sendDepositApproved(string $toEmail, ?string $toName, string $amount): bool
    {
        $name  = $toName ?: 'there';
        $inner = <<<HTML
        <p style="margin:0 0 14px;color:#9fb1d4">Hi {$name},</p>
        <p style="margin:0 0 14px">✅ Your deposit of <b style="color:#e7c873">{$amount} USDT</b> has been <b>approved</b>.</p>
        <p style="margin:0 0 14px;color:#9fb1d4">The amount is now credited to your A-WALLET. Go to <b>Fund</b> to activate your 200% package.</p>
        HTML;

        return $this->send($toEmail, $toName, 'Deposit approved — Regal Markets', $this->shell('Deposit Approved', $inner));
    }

    /** KYC rejected notification (with admin reason). */
    public function sendKycRejected(string $toEmail, ?string $toName, ?string $reason): bool
    {
        $name    = $toName ?: 'there';
        $reason  = $reason ?: 'Document could not be verified.';
        $reasonE = htmlspecialchars($reason, ENT_QUOTES);
        $inner = <<<HTML
        <p style="margin:0 0 14px;color:#9fb1d4">Hi {$name},</p>
        <p style="margin:0 0 14px">⚠️ Your <b style="color:#e7c873">KYC submission</b> was <b style="color:#ff8a8a">not approved</b>.</p>
        <p style="margin:0 0 8px;color:#9fb1d4">Reason:</p>
        <p style="margin:0 0 14px;padding:10px 12px;background:#0f2347;border-radius:8px">{$reasonE}</p>
        <p style="margin:0 0 14px;color:#9fb1d4">Please re-submit a valid, non-expired ID document from your Profile page.</p>
        HTML;

        return $this->send($toEmail, $toName, 'KYC not approved — Regal Markets', $this->shell('KYC Rejected', $inner));
    }
}
