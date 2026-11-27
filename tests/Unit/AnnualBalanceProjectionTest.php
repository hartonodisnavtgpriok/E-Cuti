<?php

namespace Tests\Unit;

use App\Domain\Leave\AnnualBalanceProjection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AnnualBalanceProjectionTest extends TestCase
{
    public function test_yearly_rollover_caps_each_previous_balance_at_six(): void
    {
        $result = (new AnnualBalanceProjection)->project(2026, [2024 => 5, 2025 => 6, 2026 => 12], [
            ['id' => 1, 'date' => '2026-03-01', 'days' => 7],
        ], 2027);

        $this->assertSame([2024 => 5, 2025 => 2], $result['allocations'][1]);
        $this->assertSame([2025 => 4, 2026 => 6, 2027 => 12], $result['years'][2027]['opening']);
    }

    public function test_backdated_release_recalculates_later_allocations(): void
    {
        $result = (new AnnualBalanceProjection)->project(2026, [2024 => 0, 2025 => 6, 2026 => 12], [
            ['id' => 2, 'date' => '2027-05-01', 'days' => 5],
            ['id' => 3, 'date' => '2026-04-01', 'days' => 4],
        ], 2027);

        $this->assertSame([2025 => 4], $result['allocations'][3]);
        $this->assertSame([2025 => 2, 2026 => 3], $result['allocations'][2]);
    }

    public function test_conflicting_backdated_release_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AnnualBalanceProjection)->project(2026, [2024 => 0, 2025 => 6, 2026 => 12], [
            ['id' => 1, 'date' => '2026-01-01', 'days' => 17],
            ['id' => 2, 'date' => '2027-01-01', 'days' => 14],
        ], 2027);
    }

    public function test_opening_carryover_cannot_exceed_six(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AnnualBalanceProjection)->project(2026, [2024 => 7, 2025 => 0, 2026 => 12], [], 2026);
    }
}
