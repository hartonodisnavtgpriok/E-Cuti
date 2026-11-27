<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_electronic_leave_branding(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('logo-e-cuti.jpeg')
            ->assertSee('logo-gabungan.png')
            ->assertSee('DISTRIK NAVIGASI')
            ->assertSee('Elektronik Cuti')
            ->assertDontSee('Dashboard Cuti');
    }

    public function test_workspace_pages_show_consistent_branding(): void
    {
        $user = User::factory()->create(['is_system_admin' => true]);

        foreach (['/', '/kata-sandi', '/cuti', '/cuti/baru', '/admin'] as $path) {
            $this->actingAs($user)->withSession(['auth_version' => $user->auth_version])->get($path)
                ->assertOk()
                ->assertSee('logo-e-cuti.jpeg')
                ->assertSee('Elektronik Cuti Distrik Navigasi Tipe B Tanjung Priok')
                ->assertDontSee('Dashboard Cuti DISNAV');
        }
    }

    public function test_dashboard_greeting_precedes_actions_without_development_notice(): void
    {
        $user = User::factory()->create(['name' => 'Tardi']);

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSeeTextInOrder(['Halo, Tardi', 'DASHBOARD PEGAWAI', 'Ubah kata sandi', 'Pengajuan cuti saya', 'Hak dan sisa cuti'])
            ->assertDontSee('Aplikasi masih dalam pengembangan.')
            ->assertSeeTextInOrder(['Hak dan sisa cuti', 'Aturan cuti', 'Dasar hukum', 'Cuti Tahunan', 'Cuti di Luar Tanggungan Negara (CLTN)']);
    }

    public function test_approval_button_is_only_shown_to_approver_accounts(): void
    {
        DB::table('positions')->insert([['name' => 'Pemohon'], ['name' => 'Penyetuju']]);
        $positions = DB::table('positions')->pluck('id', 'name');
        $employee = User::factory()->create(['position_id' => $positions['Pemohon']]);
        $approver = User::factory()->create(['position_id' => $positions['Penyetuju']]);
        DB::table('approval_routes')->insert([
            'applicant_position_id' => $positions['Pemohon'], 'first_position_id' => $positions['Penyetuju'],
        ]);

        $this->actingAs($employee)->get('/')->assertOk()
            ->assertSee('Pengajuan cuti saya')->assertDontSee('Persetujuan cuti');
        $this->actingAs($approver)->get('/')->assertOk()->assertSee('Persetujuan cuti');
    }

    public function test_employee_can_log_in_with_nip_and_log_out(): void
    {
        $user = User::factory()->create(['nip' => '123456789012345678']);

        $this->post('/login', ['nip' => $user->nip, 'password' => 'password'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['nip' => $user->nip, 'password' => 'wrong'])
            ->assertSessionHasErrors('nip');
        $this->assertGuest();
    }

    public function test_repeated_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['nip' => $user->nip, 'password' => 'wrong'])->assertSessionHasErrors('nip');
        }
        $this->post('/login', ['nip' => $user->nip, 'password' => 'password'])->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_invalid_nip_is_reported_in_indonesian(): void
    {
        $this->from('/login')->post('/login', ['nip' => '', 'password' => 'password'])
            ->assertSessionHasErrors(['nip' => 'NIP wajib diisi.']);
    }

    public function test_employee_can_change_password_and_the_old_password_stops_working(): void
    {
        $user = User::factory()->create(['nip' => '123456789012345678', 'password' => '123456789012345678']);
        $this->post('/login', ['nip' => $user->nip, 'password' => $user->nip])->assertRedirect('/');
        $this->get('/kata-sandi')->assertOk();
        $this->post('/kata-sandi', [
            'current_password' => $user->nip, 'password' => 'sandi-baru-aman', 'password_confirmation' => 'sandi-baru-aman',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->assertTrue(Hash::check('sandi-baru-aman', $user->fresh()->password));
        $this->get('/')->assertOk();
        $this->post('/logout');
        $this->post('/login', ['nip' => $user->nip, 'password' => $user->nip])->assertSessionHasErrors('nip');
        $this->post('/login', ['nip' => $user->nip, 'password' => 'sandi-baru-aman'])->assertRedirect('/');
        $this->actingAs($user->fresh())->withSession(['auth_version' => 0])->get('/')->assertRedirect('/login');
    }

    public function test_password_change_requires_current_password_and_a_new_confirmed_value(): void
    {
        $user = User::factory()->create(['nip' => '123456789012345678']);
        $this->actingAs($user)->post('/kata-sandi', [
            'current_password' => 'salah', 'password' => 'sandi-baru-aman', 'password_confirmation' => 'sandi-baru-aman',
        ])->assertSessionHasErrors('current_password');
        $this->actingAs($user)->post('/kata-sandi', [
            'current_password' => 'password', 'password' => $user->nip, 'password_confirmation' => $user->nip,
        ])->assertSessionHasErrors('password');
        $this->actingAs($user)->post('/kata-sandi', [
            'current_password' => 'password', 'password' => 'sandi-baru-aman', 'password_confirmation' => 'tidak-sama',
        ])->assertSessionHasErrors('password');
        $this->assertSame(0, $user->fresh()->auth_version);
    }

    public function test_dashboard_only_displays_the_logged_in_employees_balances(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26));
        $employee = User::factory()->create();
        $other = User::factory()->create();
        DB::table('annual_balance_baselines')->insert([
            'user_id' => $employee->id, 'year' => 2026, 'n2' => 2, 'n1' => 4, 'n' => 8,
        ]);
        DB::table('leave_balances')->insert([
            ['user_id' => $other->id, 'leave_type' => 'Cuti Milik Orang Lain', 'year' => 2026, 'initial_days' => 10, 'used_days' => 1],
            ['user_id' => $employee->id, 'leave_type' => 'Cuti Sakit', 'year' => 2026, 'initial_days' => null, 'used_days' => null],
        ]);

        $this->actingAs($employee)->get('/')
            ->assertOk()
            ->assertSee('Cuti Tahunan')
            ->assertSee('N-2')
            ->assertSee('N-1')
            ->assertSee('8 <small>hari</small>', false)
            ->assertSee('Cuti Sakit')
            ->assertSee('Tidak tersedia')
            ->assertDontSee('Cuti Milik Orang Lain');
    }

    public function test_invalid_non_annual_balance_is_reported_instead_of_showing_a_negative_remainder(): void
    {
        $employee = User::factory()->create();
        DB::table('leave_balances')->insert([
            'user_id' => $employee->id, 'leave_type' => 'Cuti Besar',
            'year' => 2026, 'initial_days' => 2, 'used_days' => 3,
        ]);

        $this->actingAs($employee)->get('/')->assertSee('Data saldo tidak valid. Hubungi admin.');
    }
}
