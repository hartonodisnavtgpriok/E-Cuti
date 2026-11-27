<?php

namespace App\Domain\Leave;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

final class AnnualBalanceService
{
    /**
     * The caller must lock the employee row before using the result to write.
     */
    public function project(int $userId, stdClass $baseline, ?stdClass $candidate = null): array
    {
        $leaves = DB::table('leave_requests')
            ->where('user_id', $userId)
            ->where('leave_type', LeaveTypes::ANNUAL)
            ->where('status', 'released')
            ->get(['id', 'start_date', 'working_days']);
        if ($candidate !== null) {
            $leaves->push($candidate);
        }

        $requests = $leaves->map(fn ($leave) => [
            'id' => (int) $leave->id,
            'date' => $leave->start_date,
            'days' => (int) $leave->working_days,
        ])->all();
        $throughYear = max((int) $baseline->year, (int) now()->format('Y'));
        foreach ($requests as $request) {
            $throughYear = max($throughYear, (int) substr($request['date'], 0, 4));
        }
        $year = (int) $baseline->year;

        return (new AnnualBalanceProjection)->project($year, [
            $year - 2 => (int) $baseline->n2,
            $year - 1 => (int) $baseline->n1,
            $year => (int) $baseline->n,
        ], $requests, $throughYear);
    }

    public function persistAllocations(array $allocations, int $actorId): void
    {
        foreach ($allocations as $requestId => $allocation) {
            $previous = DB::table('annual_leave_allocations')->where('leave_request_id', $requestId)
                ->orderBy('origin_year')->pluck('days', 'origin_year')->map(fn ($days) => (int) $days)->all();
            if ($previous === $allocation) {
                continue;
            }
            DB::table('annual_leave_allocations')->where('leave_request_id', $requestId)->delete();
            foreach ($allocation as $year => $days) {
                DB::table('annual_leave_allocations')->insert([
                    'leave_request_id' => $requestId, 'origin_year' => $year, 'days' => $days,
                ]);
            }
            DB::table('leave_request_events')->insert([
                'leave_request_id' => $requestId,
                'actor_id' => $actorId,
                'action' => 'saldo_dihitung_ulang',
                'snapshot' => json_encode(['before' => $previous, 'after' => $allocation], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function requireBaseline(int $userId): stdClass
    {
        $baseline = DB::table('annual_balance_baselines')->where('user_id', $userId)->first();
        if (! $baseline) {
            throw new InvalidArgumentException('Saldo awal cuti tahunan belum diatur oleh admin.');
        }

        return $baseline;
    }
}
