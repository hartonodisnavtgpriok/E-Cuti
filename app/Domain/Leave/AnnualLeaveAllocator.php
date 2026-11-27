<?php

namespace App\Domain\Leave;

use InvalidArgumentException;

final class AnnualLeaveAllocator
{
    /**
     * Allocate from eligible balances in oldest-year order without mutating them.
     *
     * @param  array<int, int>  $balances  Eligible year => remaining days.
     * @return array<int, int> Year => days to deduct.
     */
    public function allocate(array $balances, int $days): array
    {
        if ($days <= 0) {
            throw new InvalidArgumentException('Jumlah hari cuti harus lebih dari nol.');
        }

        foreach ($balances as $year => $balance) {
            if (! is_int($year) || $year < 1 || ! is_int($balance) || $balance < 0) {
                throw new InvalidArgumentException('Data saldo cuti tidak valid.');
            }
        }

        if (array_sum($balances) < $days) {
            throw new InvalidArgumentException('Saldo cuti tahunan tidak mencukupi.');
        }

        ksort($balances, SORT_NUMERIC);
        $allocation = [];
        foreach ($balances as $year => $balance) {
            $deduction = min($balance, $days);
            if ($deduction > 0) {
                $allocation[$year] = $deduction;
                $days -= $deduction;
            }
            if ($days === 0) {
                break;
            }
        }

        return $allocation;
    }
}
