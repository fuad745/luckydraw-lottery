<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendTelegramMessage;
use App\Services\TelegramNotifier;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TelegramNotifierTest extends TestCase
{
    public function test_broadcast_dedupes_and_skips_invalid_ids(): void
    {
        Queue::fake();

        app(TelegramNotifier::class)->broadcast([111, 111, 222, 0, -5], 'hi');

        // 111 (once), 222 — invalid/duplicate ids dropped.
        Queue::assertPushed(SendTelegramMessage::class, 2);
    }

    public function test_broadcast_staggers_dispatch_to_respect_rate_limits(): void
    {
        Queue::fake();

        app(TelegramNotifier::class)->broadcast([111, 222, 333], 'hi');

        // Each successive message is scheduled later than the last, so a big
        // broadcast trickles out under Telegram's rate limit.
        $delays = [];
        Queue::assertPushed(SendTelegramMessage::class, function (SendTelegramMessage $job) use (&$delays) {
            $delays[] = $job->delay;

            return true;
        });

        $this->assertCount(3, $delays);
        $this->assertTrue($delays[0] < $delays[1] && $delays[1] < $delays[2], 'broadcast should stagger dispatch');
    }
}
