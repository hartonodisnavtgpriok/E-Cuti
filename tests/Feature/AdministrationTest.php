<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_account_is_hidden_from_regular_admin_employee_list(): void
    {
        User::factory()->create(['name' => 'Akun Sistem Tersembunyi', 'is_system_admin' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $system = User::factory()->create(['is_system_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('Akun Sistem Tersembunyi');
        $this->actingAs($system)->get('/admin')->assertOk()->assertSee('Akun Sistem Tersembunyi');
    }

    public function test_regular_employee_cannot_access_administration(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/admin')->assertForbidden();
        $this->actingAs($employee)->post('/admin/jabatan', ['name' => 'Penyetuju'])->assertForbidden();
    }

    public function test_admin_can_create_employee_but_cannot_assign_privileged_roles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $data = [
            'name' => 'Pegawai Uji',
            'nip' => '123456789012345678',
            'password' => 'kata-sandi-uji-123',
            'password_confirmation' => 'kata-sandi-uji-123',
            'active' => '1',
        ];

        $this->actingAs($admin)->post('/admin/pegawai', $data + ['is_system_admin' => '1'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['nip' => $data['nip']]);
        $this->actingAs($admin)->post('/admin/pegawai', $data)->assertRedirect();
        $created = User::where('nip', $data['nip'])->firstOrFail();
        $this->assertTrue(Hash::check($data['password'], $created->password));
        $this->assertFalse($created->is_system_admin);
    }

    public function test_admin_cannot_edit_privileged_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $systemAdmin = User::factory()->create(['is_system_admin' => true]);

        $this->actingAs($admin)->post("/admin/pegawai/{$systemAdmin->id}", [])->assertForbidden();
    }

    public function test_new_account_uses_nip_as_initial_password_when_not_supplied(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $nip = '123456789012345678';
        $this->actingAs($admin)->post('/admin/pegawai', [
            'name' => 'Pegawai Uji', 'nip' => $nip, 'active' => '1',
        ])->assertSessionHasNoErrors();
        $employee = User::where('nip', $nip)->firstOrFail();
        $this->assertTrue($employee->active);
        $this->assertTrue(Hash::check($nip, $employee->password));
    }

    public function test_system_admin_cannot_revoke_own_access(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);

        $this->actingAs($admin)->post("/admin/pegawai/{$admin->id}", [
            'name' => $admin->name, 'nip' => $admin->nip, 'active' => '1',
        ])->assertSessionHasErrors('employee');
        $this->assertTrue($admin->fresh()->is_system_admin);
    }

    public function test_inactive_employee_is_logged_out(): void
    {
        $employee = User::factory()->create(['active' => false]);

        $this->actingAs($employee)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_resetting_password_invalidates_existing_sessions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $employee = User::factory()->create();
        $this->actingAs($admin)->post("/admin/pegawai/{$employee->id}", [
            'name' => $employee->name, 'nip' => $employee->nip, 'active' => '1',
            'password' => 'kata-sandi-baru-123', 'password_confirmation' => 'kata-sandi-baru-123',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $employee->fresh()->auth_version);

        $this->actingAs($employee->fresh())->withSession(['auth_version' => 0])->get('/')
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_approval_route_can_be_configured_and_replaced(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        DB::table('positions')->insert([
            ['name' => 'Pemohon'], ['name' => 'Penyetuju pertama'], ['name' => 'Penyetuju kedua'],
        ]);
        $ids = DB::table('positions')->pluck('id', 'name');
        $route = [
            'applicant_position_id' => $ids['Pemohon'],
            'first_position_id' => $ids['Penyetuju pertama'],
            'second_position_id' => $ids['Penyetuju kedua'],
        ];

        $this->actingAs($admin)->post('/admin/pemetaan', $route)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/pemetaan', array_replace($route, ['second_position_id' => null]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('approval_routes', [
            'applicant_position_id' => $ids['Pemohon'], 'second_position_id' => null,
        ]);
        $this->assertSame(1, DB::table('approval_routes')->count());
    }

    public function test_excel_export_is_restricted_and_preserves_nip_as_text(): void
    {
        $employee = User::factory()->create(['name' => '=2+2', 'nip' => '123456789012345678']);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($employee)->get('/admin/nominatif.xlsx')->assertForbidden();
        $response = $this->actingAs($admin)->get('/admin/nominatif.xlsx')->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));

        $path = tempnam(sys_get_temp_dir(), 'cuti-test-');
        try {
            file_put_contents($path, $response->getContent());
            $book = IOFactory::createReader('Xlsx')->load($path);
            $sheet = $book->getActiveSheet();
            $this->assertSame('NIP', $sheet->getCell('A1')->getValue());
            $this->assertSame('123456789012345678', $sheet->getCell('A2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_only_system_admin_can_manage_safe_social_links(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $system = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($admin)->post('/admin/tautan', ['label' => 'Link', 'url' => 'https://example.com'])->assertForbidden();
        $this->actingAs($system)->post('/admin/tautan', ['label' => 'Link', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->actingAs($system)->post('/admin/tautan', ['label' => 'Link', 'url' => 'https://example.com'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('social_links', ['label' => 'Link', 'url' => 'https://example.com']);
        $this->actingAs($admin)->get('/')->assertSee('https://example.com');
    }

    public function test_balance_corrections_have_access_controlled_history(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26));
        $admin = User::factory()->create(['is_admin' => true]);
        $employee = User::factory()->create();
        $this->actingAs($admin)->post("/admin/pegawai/{$employee->id}/saldo", [
            'year' => 2026, 'n2' => 1, 'n1' => 3, 'n' => 12, 'reason' => 'Penetapan awal',
        ])->assertSessionHasNoErrors();
        $this->actingAs($employee)->get("/admin/pegawai/{$employee->id}/riwayat")->assertForbidden();
        $this->actingAs($admin)->get("/admin/pegawai/{$employee->id}/riwayat")->assertOk()->assertSee('Penetapan awal');
    }
}
