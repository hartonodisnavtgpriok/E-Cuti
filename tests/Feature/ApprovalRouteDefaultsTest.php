<?php

namespace Tests\Feature;

use App\Domain\Leave\ApprovalRouteDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalRouteDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_routes_are_created_without_replacing_manual_routes(): void
    {
        $names = [
            'Kepala Distrik Navigasi Tipe B Tanjung Priok',
            'Kepala Bagian Keuangan dan Umum',
            'Kepala Seksi Layanan Armada',
            'Kepala Seksi Layanan Sarana Bantu Navigasi Pelayaran (SBNP)',
            'Kepala Seksi Layanan Telkompel',
            'Masinis I Kapal Kelas IV',
            'Manager VTS Merak',
            'Nakhoda Kapal KN Edam',
            'Pegawai - INSTALASI KAPAL NEGARA KN. EDAM',
            'Pegawai - INSTALASI BENGKEL',
            'Pegawai - INSTALASI SBNP MENARA SUAR',
        ];
        foreach ($names as $name) {
            DB::table('positions')->insert(['name' => $name, 'is_group' => str_starts_with($name, 'Pegawai - ')]);
        }
        $id = DB::table('positions')->pluck('id', 'name');
        DB::table('approval_routes')->insert([
            'applicant_position_id' => $id['Pegawai - INSTALASI BENGKEL'],
            'first_position_id' => $id['Kepala Seksi Layanan Armada'],
            'second_position_id' => null,
        ]);
        (new ApprovalRouteDefaults)->apply();

        $this->assertDatabaseHas('approval_routes', [
            'applicant_position_id' => $id['Pegawai - INSTALASI KAPAL NEGARA KN. EDAM'],
            'first_position_id' => $id['Kepala Seksi Layanan Armada'],
            'second_position_id' => $id['Kepala Bagian Keuangan dan Umum'],
        ]);
        $this->assertDatabaseHas('approval_routes', [
            'applicant_position_id' => $id['Pegawai - INSTALASI SBNP MENARA SUAR'],
            'first_position_id' => $id['Kepala Seksi Layanan Sarana Bantu Navigasi Pelayaran (SBNP)'],
            'second_position_id' => $id['Kepala Bagian Keuangan dan Umum'],
        ]);
        $this->assertDatabaseHas('approval_routes', [
            'applicant_position_id' => $id['Pegawai - INSTALASI BENGKEL'],
            'first_position_id' => $id['Kepala Seksi Layanan Armada'],
            'second_position_id' => null,
        ]);
        $this->assertDatabaseHas('approval_routes', [
            'applicant_position_id' => $id['Masinis I Kapal Kelas IV'],
            'first_position_id' => $id['Kepala Seksi Layanan Armada'],
            'second_position_id' => $id['Kepala Bagian Keuangan dan Umum'],
        ]);
        $this->assertDatabaseHas('approval_routes', [
            'applicant_position_id' => $id['Manager VTS Merak'],
            'first_position_id' => $id['Kepala Seksi Layanan Telkompel'],
            'second_position_id' => $id['Kepala Distrik Navigasi Tipe B Tanjung Priok'],
        ]);
        $this->assertDatabaseHas('approval_routes', [
            'applicant_position_id' => $id['Nakhoda Kapal KN Edam'],
            'first_position_id' => $id['Kepala Distrik Navigasi Tipe B Tanjung Priok'],
            'second_position_id' => null,
        ]);
    }
}
