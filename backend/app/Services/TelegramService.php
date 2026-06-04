<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends admin notifications to Telegram via the Bot HTTP API (port 443).
 * Configure TELEGRAM_BOT_TOKEN + TELEGRAM_CHAT_ID in .env.
 */
class TelegramService
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.telegram.token')) && ! empty(config('services.telegram.chat_id'));
    }

    public function send(string $text): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        try {
            $token = config('services.telegram.token');
            $res = Http::asForm()->timeout(8)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'                  => config('services.telegram.chat_id'),
                'text'                     => $text,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ]);
            if (! $res->successful()) {
                Log::warning('Telegram send failed (' . $res->status() . '): ' . $res->body());
            }
            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram error: ' . $e->getMessage());
            return false;
        }
    }

    /** Build a titled message from key/value lines and send it. */
    public function notify(string $title, array $lines): bool
    {
        $text = "<b>" . e($title) . "</b>\n";
        foreach ($lines as $k => $v) {
            $text .= "• <b>" . e($k) . ":</b> " . e((string) $v) . "\n";
        }
        $text .= "\n<i>Regal Markets · " . now()->setTimezone(config('app.timezone'))->format('d M Y, g:i A') . "</i>";
        return $this->send($text);
    }
}
