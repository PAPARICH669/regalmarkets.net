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
}
