<?php

namespace App\Domain\Leave;

use Illuminate\Support\Facades\DB;

final class ApprovalRouteDefaults
{
    public const HEAD = 'Kepala Distrik Navigasi Tipe B Tanjung Priok';

    private const FINANCE = 'Kepala Bagian Keuangan dan Umum';

    private const ARMADA = 'Kepala Seksi Layanan Armada';

    private const SBNP = 'Kepala Seksi Layanan Sarana Bantu Navigasi Pelayaran (SBNP)';

    private const TELKOMPEL = 'Kepala Seksi Layanan Telkompel';

    /**
     * Add known routes only when the corresponding positions exist.
     * Existing routes are never replaced.
     */
    public function apply(): int
    {
        $positions = DB::table('positions')->get()->keyBy('name');
        $created = 0;
        foreach ($positions as $position) {
            $route = $this->routeFor($position->name, (bool) $position->is_group);
            if ($route === null) {
                continue;
            }
            [$first, $second] = $route;
            $firstPosition = $positions->get($first);
            $secondPosition = $second === null ? null : $positions->get($second);
            if (! $firstPosition || $firstPosition->is_group || ($second !== null && (! $secondPosition || $secondPosition->is_group))) {
                continue;
            }
            if (DB::table('approval_routes')->where('applicant_position_id', $position->id)->exists()) {
                continue;
            }
            DB::table('approval_routes')->insert([
                'applicant_position_id' => $position->id,
                'first_position_id' => $firstPosition->id,
                'second_position_id' => $secondPosition?->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $created++;
        }

        return $created;
    }

    /** @return array{string, ?string}|null */
    private function routeFor(string $name, bool $isGroup): ?array
    {
        if ($isGroup) {
            $unit = mb_strtoupper(substr($name, strlen('Pegawai - ')));
            if (! str_starts_with($name, 'Pegawai - ')) {
                return null;
            }
            $first = match (true) {
                $unit === 'SUBBAGIAN SDM DAN UMUM' => 'Kepala Sub Bagian Sumber Daya Manusia (SDM) dan Umum',
                $unit === 'SUB BAGIAN PERENCANAAN DAN KEUANGAN' => 'Kepala Sub Bagian Perencanaan dan Keuangan',
                $unit === 'SEKSI LAYANAN ALUR PELAYARAN' => 'Kepala Seksi Layanan Alur Pelayaran',
                $unit === 'SEKSI LAYANAN ARMADA', str_starts_with($unit, 'INSTALASI KAPAL NEGARA ') => self::ARMADA,
                $unit === 'SEKSI LAYANAN SBNP', $unit === 'INSTALASI BENGKEL', $unit === 'INSTALASI SBNP MENARA SUAR' => self::SBNP,
                $unit === 'SEKSI LAYANAN TELKOMPEL', str_starts_with($unit, 'INSTALASI TELKOMPEL ') => self::TELKOMPEL,
                default => null,
            };

            return $first === null ? null : [$first, self::FINANCE];
        }

        if (str_starts_with(mb_strtoupper($name), 'MANAGER VTS ') || str_starts_with(mb_strtoupper($name), 'MANAGER SROP ')) {
            return [self::TELKOMPEL, self::HEAD];
        }
        if (str_starts_with(mb_strtoupper($name), 'NAKHODA KAPAL ')
            || str_starts_with(mb_strtoupper($name), 'MANAGER BENGKEL')) {
            return [self::HEAD, null];
        }

        return match ($name) {
            'Kepala Sub Bagian Sumber Daya Manusia (SDM) dan Umum',
            'Kepala Sub Bagian Perencanaan dan Keuangan' => [self::FINANCE, self::HEAD],
            self::ARMADA, self::SBNP => ['Kepala Bidang Layanan SBNP dan Armada', self::HEAD],
            'Kepala Seksi Layanan Alur Pelayaran', self::TELKOMPEL => ['Kepala Bidang Layanan Alur Pelayaran dan Telkompel', self::HEAD],
            self::FINANCE, 'Kepala Bidang Layanan SBNP dan Armada',
            'Kepala Bidang Layanan Alur Pelayaran dan Telkompel',
            'Kepala Unit Pengembangan Usaha dan Kerja Sama (UPUKS)', 'Kepala SPI' => [self::HEAD, null],
            'Masinis I Kapal Kelas IV' => [self::ARMADA, self::FINANCE],
            default => null,
        };
    }
}
