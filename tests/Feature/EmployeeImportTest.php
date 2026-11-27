<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_confirm_import_employees_and_vacancies_without_overwriting_accounts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $existing = User::factory()->create(['nip' => '123456789012345679', 'name' => 'Nama Lama']);
        $book = $this->sample();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('E7', 3)->setCellValue('F7', 'Nama Baru');
        $sheet->setCellValueExplicit('G7', $existing->nip, DataType::TYPE_STRING);
        $path = $this->writeBook($book);

        try {
            $preview = $this->actingAs($admin)->post('/admin/impor/pratinjau', [
                'file' => new UploadedFile($path, 'nominatif.xlsx', null, null, true),
            ])->assertOk()->assertSee('Nama jabatan tidak tersedia');
            $data = $preview->viewData('data');
            $this->assertCount(2, $data['employees']);
            $this->assertCount(2, $data['vacancies']);
            $payload = $preview->viewData('payload');

            $this->actingAs($admin)->post('/admin/impor/simpan', ['payload' => $payload])->assertSessionHasNoErrors();
            $newEmployee = User::where('nip', '123456789012345678')->firstOrFail();
            $this->assertTrue($newEmployee->active);
            $this->assertTrue(Hash::check($newEmployee->nip, $newEmployee->password));
            $this->assertSame('Nama Lama', $existing->fresh()->name);
            $this->assertTrue(Hash::check('password', $existing->fresh()->password));
            $this->assertDatabaseHas('units', ['name' => 'UNIT UJI']);
            $this->assertDatabaseHas('positions', ['name' => 'Pegawai - UNIT UJI', 'is_group' => true]);
            $this->assertDatabaseHas('positions', ['name' => 'Kepala Unit Uji', 'is_group' => false]);
            $this->assertDatabaseCount('employee_imports', 1);
            $this->actingAs($admin)->post('/admin/impor/simpan', ['payload' => $payload])->assertSessionHasErrors('file');
        } finally {
            unlink($path);
        }
    }

    public function test_import_rejects_numeric_nip_and_restricts_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $employee = User::factory()->create();
        $book = $this->sample();
        $book->getActiveSheet()->setCellValueExplicit('G4', 123456789012345678, DataType::TYPE_NUMERIC);
        $path = $this->writeBook($book);
        try {
            $file = new UploadedFile($path, 'nominatif.xlsx', null, null, true);
            $this->actingAs($employee)->post('/admin/impor/pratinjau', ['file' => $file])->assertForbidden();
            $this->actingAs($admin)->post('/admin/impor/pratinjau', ['file' => $file])->assertSessionHasErrors('file');
            $this->assertDatabaseCount('employee_imports', 0);
        } finally {
            unlink($path);
        }
    }

    private function sample(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('E2', 'NO')->setCellValue('F2', 'NAMA')->setCellValue('G2', 'NIP')->setCellValue('H2', 'JABATAN');
        $sheet->setCellValue('F3', 'UNIT UJI');
        $sheet->setCellValue('E4', 1)->setCellValue('F4', 'Pegawai Uji');
        $sheet->setCellValueExplicit('G4', '12345678 901234 5 678', DataType::TYPE_STRING);
        $sheet->setCellValue('E5', 2)->setCellValue('F5', 'Kosong')->setCellValue('H5', 'Kepala Unit Uji');
        $sheet->setCellValue('E6', 3);

        return $book;
    }

    private function writeBook(Spreadsheet $book): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cuti-xlsx-');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
