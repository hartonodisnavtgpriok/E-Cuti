<?php

namespace App\Domain\Leave;

use DateTimeImmutable;
use InvalidArgumentException;

final class AnnualBalanceProjection
{
    /**
     * Recalculate all allocations in leave-date order from a verified opening balance.
     *
     * @param  array<int, int>  $opening  Origin year => opening days at the baseline.
     * @param  list<array{id: int, date: string, days: int}>  $requests
     * @return array{years: array<int, array{opening: array<int, int>, used: array<int, int>, remaining: array<int, int>}>, allocations: array<int, array<int, int>>}
     */
    public function project(int $baselineYear, array $opening, array $requests, int $throughYear): array
    {
        if ($baselineYear < 3 || $throughYear < $baselineYear || $throughYear > 9999) {
            throw new InvalidArgumentException('Tahun saldo tidak valid.');
        }

        $expectedYears = [$baselineYear - 2, $baselineYear - 1, $baselineYear];
        ksort($opening, SORT_NUMERIC);
        if (array_keys($opening) !== $expectedYears) {
            throw new InvalidArgumentException('Saldo awal harus memuat N-2, N-1, dan N.');
        }
        foreach ($opening as $year => $days) {
            $max = $year === $baselineYear ? 12 : 6;
            if (! is_int($days) || $days < 0 || $days > $max) {
                throw new InvalidArgumentException('Saldo awal melebihi hak yang diizinkan.');
            }
        }

        $ids = [];
        foreach ($requests as $request) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $request['date']);
            if ($date === false || $date->format('Y-m-d') !== $request['date']
                || ! is_int($request['id']) || $request['id'] < 1 || isset($ids[$request['id']])
                || ! is_int($request['days']) || $request['days'] < 1
                || (int) $date->format('Y') < $baselineYear || (int) $date->format('Y') > $throughYear) {
                throw new InvalidArgumentException('Data penggunaan cuti tahunan tidak valid.');
            }
            $ids[$request['id']] = true;
        }
        usort($requests, fn (array $a, array $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);

        $result = ['years' => [], 'allocations' => []];
        $cursor = 0;
        $allocator = new AnnualLeaveAllocator;
        for ($year = $baselineYear; $year <= $throughYear; $year++) {
            $remaining = $opening;
            $used = array_fill_keys(array_keys($opening), 0);
            while (isset($requests[$cursor]) && (int) substr($requests[$cursor]['date'], 0, 4) === $year) {
                $request = $requests[$cursor++];
                $allocation = $allocator->allocate($remaining, $request['days']);
                foreach ($allocation as $origin => $days) {
                    $remaining[$origin] -= $days;
                    $used[$origin] += $days;
                }
                $result['allocations'][$request['id']] = $allocation;
            }
            $result['years'][$year] = ['opening' => $opening, 'used' => $used, 'remaining' => $remaining];
            $opening = [
                $year - 1 => min(6, $remaining[$year - 1]),
                $year => min(6, $remaining[$year]),
                $year + 1 => 12,
            ];
        }

        return $result;
    }
}
