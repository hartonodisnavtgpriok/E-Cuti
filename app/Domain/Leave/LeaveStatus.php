<?php

namespace App\Domain\Leave;

final class LeaveStatus
{
    public static function label(string $status): string
    {
        return match ($status) {
            'pending_1' => 'Menunggu penyetuju 1',
            'pending_2' => 'Menunggu penyetuju 2',
            'deferred_1', 'deferred_2' => 'Ditangguhkan',
            'revision' => 'Perlu revisi',
            'numbering' => 'Menunggu nomor surat',
            'ready' => 'Menunggu rilis admin',
            'released' => 'Surat dirilis',
            default => 'Status tidak dikenal',
        };
    }
}
