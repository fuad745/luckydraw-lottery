<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

final class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /** @param int|string $chatId numeric user id, or a channel id / @username */
    public function __construct(
        public readonly int|string $chatId,
        public readonly string $message,
        public readonly ?string $type = null,
        public readonly ?int $roundId = null,
    ) {}

    public function handle(): void
    {
        $token = config('lottery.bot_token');

        if (empty($token)) {
            $this->log('failed', 'TELEGRAM_BOT_TOKEN not configured');

            return;
        }

        $response = Http::asJson()
            ->timeout(15)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $this->message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

        if ($response->successful()) {
            $this->log('sent');

            return;
        }

        // 403 = user blocked the bot / never started it: don't retry forever.
        if ($response->status() === 403) {
            $this->log('failed', $response->json('description') ?? 'forbidden');

            return;
        }

        // 429 = rate limited. Honour Telegram's requested cool-off instead of a
        // fixed backoff, then retry (up to $tries) without marking it failed.
        if ($response->status() === 429) {
            $retryAfter = (int) ($response->json('parameters.retry_after')
                ?? $response->header('Retry-After')
                ?? $this->backoff);
            $this->release(max(1, $retryAfter));

            return;
        }

        $this->log('failed', $response->json('description') ?? 'http '.$response->status());
        $response->throw();
    }

    public function failed(\Throwable $e): void
    {
        $this->log('failed', $e->getMessage());
    }

    private function log(string $status, ?string $error = null): void
    {
        NotificationLog::create([
            // Channel ids (@name or -100…) don't fit the numeric column — log as 0.
            'telegram_id' => is_numeric($this->chatId) && $this->chatId > 0 ? (string) $this->chatId : 0,
            'type' => $this->type,
            'message' => $this->message,
            'round_id' => $this->roundId,
            'status' => $status,
            'error' => $error,
            'sent_at' => now(),
        ]);
    }
}
