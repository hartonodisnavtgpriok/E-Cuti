<?php

namespace App\Domain\Leave;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;

final class NominativeSpreadsheet
{
    /**
     * @return array{employees: list<array>, vacancies: list<array>}
     */
    public function read(string $path): array
    {
        $reader = new Xlsx;
        $info = $reader->listWorksheetInfo($path);
        if (count($info) !== 1 || $info[0]['totalRows'] > 5000 || $info[0]['totalColumns'] > 100) {
            throw new RuntimeException('Excel harus memiliki satu lembar dengan maksimal 5000 baris dan 100 kolom.');
        }
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        try {
            $sheet = $book->getActiveSheet();
            foreach (['E' => 'NO', 'F' => 'NAMA', 'G' => 'NIP', 'H' => 'JABATAN'] as $column => $heading) {
                if (mb_strtoupper(trim((string) $sheet->getCell($column.'2')->getValue())) !== $heading) {
                    throw new RuntimeException('Header Excel tidak sesuai: baris 2 kolom E-H harus berisi NO, NAMA, NIP, JABATAN.');
                }
            }

            $result = ['employees' => [], 'vacancies' => []];
            $group = null;
            $seen = [];
            for ($row = 3; $row <= $sheet->getHighestDataRow(); $row++) {
                foreach (['E', 'F', 'G', 'H'] as $column) {
                    if ($sheet->getCell($column.$row)->getDataType() === DataType::TYPE_FORMULA) {
                        throw new RuntimeException("Rumus tidak diperbolehkan pada baris {$row}.");
                    }
                }
                $number = trim((string) $sheet->getCell('E'.$row)->getValue());
                $name = trim((string) $sheet->getCell('F'.$row)->getValue());
                $nipCell = $sheet->getCell('G'.$row);
                $rawNip = trim((string) $nipCell->getValue());
                $position = trim((string) $sheet->getCell('H'.$row)->getValue());
                if ($number === '' && $name === '' && $rawNip === '' && $position === '') {
                    continue;
                }
                if ($number === '' && $name !== '' && $rawNip === '' && $position === '') {
                    $group = $name;
                    if (mb_strlen('Pegawai - '.$group) > 255) {
                        throw new RuntimeException("Nama kelompok pada baris {$row} terlalu panjang.");
                    }

                    continue;
                }
                if ($number === '' || ! ctype_digit($number) || $group === null) {
                    throw new RuntimeException("Struktur kelompok atau nomor pada baris {$row} tidak valid.");
                }
                $unit = mb_strtoupper($group) === 'STRUKTURAL' ? null : $group;
                if ($rawNip === '') {
                    if (mb_strlen($position) > 255 || ($position === '' && $name !== '')) {
                        throw new RuntimeException("Posisi kosong pada baris {$row} tidak memiliki jabatan yang valid.");
                    }
                    $result['vacancies'][] = ['row' => $row, 'unit' => $unit, 'position' => $position !== '' ? $position : null];

                    continue;
                }
                if (! in_array($nipCell->getDataType(), [DataType::TYPE_STRING, DataType::TYPE_INLINE], true)) {
                    throw new RuntimeException("NIP pada baris {$row} harus disimpan sebagai teks Excel, bukan angka.");
                }
                $nip = preg_replace('/\s+/u', '', $rawNip);
                if (! preg_match('/^[0-9]{18}$/', $nip) || isset($seen[$nip]) || $name === ''
                    || mb_strlen($name) > 255 || mb_strlen($position) > 255) {
                    throw new RuntimeException("Nama, NIP, atau jabatan pada baris {$row} tidak valid atau NIP duplikat.");
                }
                $seen[$nip] = true;
                $result['employees'][] = [
                    'row' => $row, 'name' => $name, 'nip' => $nip, 'unit' => $unit,
                    'position' => $position !== '' ? $position : 'Pegawai - '.$group,
                    'is_group' => $position === '',
                ];
            }
            if ($result['employees'] === [] && $result['vacancies'] === []) {
                throw new RuntimeException('Excel tidak berisi data pegawai atau posisi kosong.');
            }

            return $result;
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
