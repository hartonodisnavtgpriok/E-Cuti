<?php

namespace App\Domain\Leave;

use DateTimeImmutable;
use InvalidArgumentException;

final class WorkingDays
{
    /**
     * @param  list<string>  $holidays  Dates in YYYY-MM-DD format.
     */
    public function count(DateTimeImmutable $start, DateTimeImmutable $end, array $holidays): int
    {
        $start = $start->setTime(0, 0);
        $end = $end->setTime(0, 0);

        if ($end < $start) {
            throw new InvalidArgumentException('Tanggal akhir tidak boleh sebelum tanggal mulai.');
        }

        $holidayDates = [];
        foreach ($holidays as $holiday) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $holiday);
            if ($date === false || $date->format('Y-m-d') !== $holiday) {
                throw new InvalidArgumentException('Tanggal hari libur tidak valid.');
            }
            $holidayDates[$holiday] = true;
        }

        $totalDays = $start->diff($end)->days + 1;
        $count = intdiv($totalDays, 7) * 5;
        $startDay = (int) $start->format('N');
        for ($offset = 0; $offset < $totalDays % 7; $offset++) {
            if (($startDay - 1 + $offset) % 7 < 5) {
                $count++;
            }
        }
        foreach (array_keys($holidayDates) as $holiday) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $holiday);
            if ($date >= $start && $date <= $end && (int) $date->format('N') <= 5) {
                $count--;
            }
        }

        return $count;
    }
}
