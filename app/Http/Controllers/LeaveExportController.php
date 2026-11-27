<?php

namespace App\Http\Controllers;

use App\Domain\Leave\ExcelExport;
use App\Domain\Leave\LeaveStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LeaveExportController extends Controller
{
    public function __invoke(Request $request, ExcelExport $export): Response
    {
        abort_unless($request->user()->is_admin || $request->user()->is_system_admin, 403);
        $leaves = DB::table('leave_requests')->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->select('users.nip', 'users.name', 'leave_requests.leave_type', 'leave_requests.start_date',
                'leave_requests.end_date', 'leave_requests.working_days', 'leave_requests.status', 'leave_requests.letter_number')
            ->orderByDesc('leave_requests.id')->get();
        $rows = $leaves->map(fn ($leave) => [
            $leave->nip, $leave->name, $leave->leave_type, $leave->start_date, $leave->end_date,
            (int) $leave->working_days, LeaveStatus::label($leave->status), $leave->letter_number ?? '',
        ]);

        return $export->download('Riwayat cuti', 'riwayat-cuti.xlsx', [
            'NIP', 'Nama', 'Jenis cuti', 'Tanggal mulai', 'Tanggal akhir', 'Hari kerja', 'Status', 'Nomor surat',
        ], $rows);
    }
}
