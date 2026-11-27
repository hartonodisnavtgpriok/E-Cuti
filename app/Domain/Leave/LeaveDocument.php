<?php

namespace App\Domain\Leave;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use stdClass;

final class LeaveDocument
{
    public function render(stdClass $leave, ?array $annual): string
    {
        $lastSubmission = DB::table('leave_request_events')
            ->where('leave_request_id', $leave->id)
            ->whereIn('action', ['diajukan', 'dikirim_ulang'])->max('id');
        $decisions = DB::table('leave_request_events')
            ->where('leave_request_id', $leave->id)->where('id', '>', $lastSubmission)
            ->where('action', 'disetujui')->orderBy('id')->get()->map(function ($event) {
                return json_decode($event->snapshot, true, flags: JSON_THROW_ON_ERROR);
            })->keyBy('stage');
        if (! $decisions->has(1) || ($leave->second_position_id !== null && ! $decisions->has(2))) {
            throw new \RuntimeException('Riwayat persetujuan tidak lengkap; surat tidak dapat dibuat.');
        }
        $firstApprovalId = DB::table('leave_request_events')
            ->where('leave_request_id', $leave->id)->where('id', '>', $lastSubmission)
            ->where('action', 'disetujui')->min('id');
        $changesAfterApproval = DB::table('leave_request_events')
            ->where('leave_request_id', $leave->id)->where('id', '>', $firstApprovalId)
            ->where('action', 'diubah_admin')->orderBy('id')->get();

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('leave.document', [
            'leave' => $leave,
            'annual' => $annual,
            'decisions' => $decisions,
            'changesAfterApproval' => $changesAfterApproval,
            'issuedAt' => now(),
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
