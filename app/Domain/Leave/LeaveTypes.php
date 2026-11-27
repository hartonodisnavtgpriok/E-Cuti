<?php

namespace App\Domain\Leave;

final class LeaveTypes
{
    public const ANNUAL = 'Cuti Tahunan';

    public const ALL = [
        self::ANNUAL, 'Cuti Besar', 'Cuti Sakit', 'Cuti Melahirkan',
        'Cuti Karena Alasan Penting', 'Cuti di Luar Tanggungan Negara',
    ];
}
