<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationLogPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_logs_older_than_the_retention_window_are_prunable(): void
    {
        config(['lottery.notifications_retention_days' => 30]);

        $old = NotificationLog::create(['telegram_id' => 111, 'type' => 'x', 'message' => 'old', 'status' => 'sent', 'sent_at' => now()->subDays(31)]);
        $fresh = NotificationLog::create(['telegram_id' => 111, 'type' => 'x', 'message' => 'new', 'status' => 'sent', 'sent_at' => now()->subDays(5)]);

        $prunableIds = (new NotificationLog)->prunable()->pluck('id');

        $this->assertTrue($prunableIds->contains($old->id));
        $this->assertFalse($prunableIds->contains($fresh->id));
    }
}
