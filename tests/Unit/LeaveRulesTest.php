<?php

namespace Tests\Unit;

use App\Domain\Leave\AnnualLeaveAllocator;
use App\Domain\Leave\WorkingDays;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LeaveRulesTest extends TestCase
{
    public function test_working_days_exclude_weekends_and_registered_holidays(): void
    {
        $days = (new WorkingDays)->count(
            new DateTimeImmutable('2026-10-12'),
            new DateTimeImmutable('2026-10-18'),
            ['2026-10-14', '2026-10-17'],
        );

        $this->assertSame(4, $days);
    }

    public function test_working_days_reject_invalid_ranges_and_holidays(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WorkingDays)->count(new DateTimeImmutable('2026-10-19'), new DateTimeImmutable('2026-10-18'), []);
    }

    public function test_working_days_reject_invalid_holiday_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WorkingDays)->count(new DateTimeImmutable('2026-10-12'), new DateTimeImmutable('2026-10-13'), ['2026-02-30']);
    }

    public function test_annual_leave_uses_oldest_eligible_balance_first(): void
    {
        $this->assertSame(
            [2024 => 2, 2025 => 3],
            (new AnnualLeaveAllocator)->allocate([2026 => 12, 2025 => 4, 2024 => 2], 5),
        );
    }

    public function test_annual_leave_cannot_be_allocated_if_balance_is_insufficient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AnnualLeaveAllocator)->allocate([2025 => 2, 2026 => 1], 4);
    }

    public function test_annual_leave_rejects_negative_balance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AnnualLeaveAllocator)->allocate([2026 => -1], 1);
    }
}
