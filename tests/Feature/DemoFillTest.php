<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoundStatus;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class DemoFillTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_fill_sells_out_and_starts_the_draw(): void
    {
        Queue::fake();
        $round = Round::factory()->create([
            'total_tickets' => 8,
            'status' => RoundStatus::Open,
            'auto_draw' => true,
            'allow_half_tickets' => false,
        ]);

        $this->artisan('lottery:demo-fill', ['--players' => 4])
            ->assertSuccessful();

        $round->refresh();
        $this->assertTrue($round->isFull());
        $this->assertSame(RoundStatus::Drawing, $round->status);
    }
}
