<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_round_trips_amounts_without_drift(): void
    {
        foreach ([0.0, 0.1, 0.2, 10.05, 33.33, 999999.99] as $amount) {
            $this->assertSame($amount, Money::toAmount(Money::toCents($amount)));
        }
    }

    public function test_accumulating_cents_has_no_float_error(): void
    {
        // 0.1 + 0.2 in floats is 0.30000000000000004; in cents it is exact.
        $cents = Money::toCents(0.1) + Money::toCents(0.2);
        $this->assertSame(30, $cents);
        $this->assertSame(0.3, Money::toAmount($cents));
    }

    public function test_allocate_distributes_every_cent(): void
    {
        // 101 cents split 50/50 → 51 + 50, never 50 + 50 (lost cent) or 51 + 51.
        $this->assertSame([51, 50], Money::allocate(101, [0.5, 0.5]));
        $this->assertSame(101, array_sum(Money::allocate(101, [0.5, 0.5])));
    }

    public function test_allocate_sums_back_to_total_for_three_way_split(): void
    {
        $parts = Money::allocate(100, [1 / 3, 1 / 3, 1 / 3]);
        $this->assertSame(100, array_sum($parts));
    }

    public function test_allocate_handles_zero_and_empty(): void
    {
        $this->assertSame([0, 0], Money::allocate(0, [0.5, 0.5]));
        $this->assertSame([0], Money::allocate(50, [0.0]));
    }
}
