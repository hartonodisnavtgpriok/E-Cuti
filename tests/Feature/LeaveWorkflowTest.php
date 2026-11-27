<?php

namespace Tests\Feature;

use App\Domain\Leave\ApprovalRouteDefaults;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class LeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupRoute(bool $twoStages = true): array
    {
        DB::table('positions')->insert([['name' => 'Pemohon'], ['name' => 'Tahap 1'], ['name' => 'Tahap 2']]);
        $positions = DB::table('positions')->pluck('id', 'name');
        $employee = User::factory()->create(['position_id' => $positions['Pemohon']]);
        $first = User::factory()->create(['position_id' => $positions['Tahap 1']]);
        $second = User::factory()->create(['position_id' => $positions['Tahap 2']]);
        DB::table('approval_routes')->insert([
            'applicant_position_id' => $positions['Pemohon'],
            'first_position_id' => $positions['Tahap 1'],
            'second_position_id' => $twoStages ? $positions['Tahap 2'] : null,
        ]);

        return [$employee, $first, $second, $positions];
    }

    private function form(): array
    {
        return [
            'leave_type' => 'Cuti Tahunan', 'start_date' => '2026-10-12', 'end_date' => '2026-10-18',
            'reason' => 'Keperluan keluarga', 'address' => 'Alamat uji', 'phone' => '081234567890',
        ];
    }

    public function test_whatsapp_number_is_required_and_attachments_are_stored_privately(): void
    {
        [$employee, $first] = $this->setupRoute();
        $this->actingAs($employee)->post('/cuti', ['phone' => ''] + $this->form())->assertSessionHasErrors('phone');

        Storage::fake('local');
        $file = UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf');
        $this->actingAs($employee)->post('/cuti', $this->form() + ['attachments' => [$file]])->assertSessionHasNoErrors();
        $attachment = DB::table('leave_attachments')->first();
        $leaveId = $attachment->leave_request_id;
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($employee)->get("/cuti/$leaveId/lampiran/$attachment->id")->assertOk();
        $this->actingAs($first)->get("/cuti/$leaveId/lampiran/$attachment->id")->assertOk();

        $bad = UploadedFile::fake()->create('x.exe', 10);
        $this->actingAs($employee)->post('/cuti', $this->form() + ['attachments' => [$bad]])->assertSessionHasErrors('attachments.0');
    }

    public function test_personal_requests_and_current_approvals_are_separate(): void
    {
        [$employee, $first, $second] = $this->setupRoute();
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $otherId = DB::table('leave_requests')->value('id');
        $ownId = DB::table('leave_requests')->insertGetId([
            'user_id' => $first->id, 'leave_type' => 'Cuti Sakit', 'start_date' => '2026-10-12',
            'end_date' => '2026-10-12', 'working_days' => 1, 'reason' => 'Uji', 'address' => 'Uji',
            'status' => 'pending_1', 'first_position_id' => $first->position_id,
        ]);

        $this->actingAs($first)->get('/')->assertSee('Pengajuan cuti saya')->assertSee('Persetujuan cuti');
        $this->actingAs($first)->get('/cuti')->assertOk()
            ->assertSee("cuti/{$ownId}?from=pengajuan")->assertDontSee("cuti/{$otherId}?from=");
        $this->actingAs($first)->get('/cuti/persetujuan')->assertOk()
            ->assertSee("cuti/{$otherId}?from=persetujuan")->assertDontSee("cuti/{$ownId}?from=")
            ->assertDontSee('Ajukan cuti');
        $this->actingAs($second)->get('/cuti/persetujuan')->assertDontSee("cuti/{$otherId}?from=");
        $this->actingAs($first)->get("/cuti/{$otherId}?from=persetujuan")
            ->assertSee(route('leave.approvals'));

        $this->actingAs($first)->post("/cuti/{$otherId}/keputusan", ['decision' => 'disetujui'])
            ->assertRedirect(route('leave.approvals'));
        $this->actingAs($first)->get('/cuti/persetujuan')->assertDontSee("cuti/{$otherId}?from=");
        $this->actingAs($second)->get('/cuti/persetujuan')->assertSee("cuti/{$otherId}?from=persetujuan");

        User::factory()->create(['position_id' => $second->position_id]);
        $this->actingAs($second)->get('/cuti/persetujuan')->assertDontSee("cuti/{$otherId}?from=");
        $this->actingAs($second)->post("/cuti/{$otherId}/keputusan", ['decision' => 'disetujui'])->assertForbidden();
    }

    public function test_management_list_preserves_staff_access(): void
    {
        [$employee] = $this->setupRoute();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $correspondence = User::factory()->create(['is_correspondence' => true]);
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');

        $this->actingAs($employee)->get('/cuti/kelola')->assertForbidden();
        $this->actingAs($admin)->get('/cuti')->assertDontSee("cuti/{$id}?from=");
        $this->actingAs($admin)->get('/cuti/persetujuan')->assertDontSee("cuti/{$id}?from=");
        $this->actingAs($admin)->get('/cuti/kelola')->assertSee("cuti/{$id}?from=kelola")
            ->assertSee('Ekspor riwayat cuti ke Excel');
        $this->actingAs($correspondence)->get('/cuti/kelola')->assertSee("cuti/{$id}?from=kelola");
    }

    public function test_two_stage_rejection_restarts_after_revision_and_retains_history(): void
    {
        [$employee, $first, $second] = $this->setupRoute();
        DB::table('holidays')->insert(['date' => '2026-10-14', 'description' => 'Libur uji']);
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $leave = DB::table('leave_requests')->first();
        $this->assertSame(4, $leave->working_days);

        $this->actingAs($second)->post("/cuti/{$leave->id}/keputusan", ['decision' => 'disetujui'])->assertForbidden();
        $this->actingAs($first)->post("/cuti/{$leave->id}/keputusan", ['decision' => 'disetujui'])->assertRedirect();
        $this->actingAs($second)->post("/cuti/{$leave->id}/keputusan", [
            'decision' => 'tidak_disetujui', 'note' => 'Perbaiki tanggal',
        ])->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'revision']);

        $this->actingAs($employee)->post("/cuti/{$leave->id}/revisi", $this->form())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'pending_1']);
        $this->assertSame(4, DB::table('leave_request_events')->where('leave_request_id', $leave->id)->count());
    }

    public function test_pending_approval_follows_new_occupant_and_only_new_occupant_can_decide(): void
    {
        [$employee, $first, $second, $positions] = $this->setupRoute();
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $leave = DB::table('leave_requests')->first();
        $first->update(['position_id' => null]);
        $replacement = User::factory()->create(['position_id' => $positions['Tahap 1']]);

        $this->actingAs($first)->post("/cuti/{$leave->id}/keputusan", ['decision' => 'disetujui'])->assertForbidden();
        $this->actingAs($replacement)->get("/cuti/{$leave->id}")->assertOk();
        $this->actingAs($replacement)->post("/cuti/{$leave->id}/keputusan", ['decision' => 'disetujui'])->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'pending_2']);
    }

    public function test_single_approval_and_deferred_case(): void
    {
        [$employee, $first] = $this->setupRoute(false);
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');
        $this->actingAs($first)->post("/cuti/{$id}/keputusan", [
            'decision' => 'ditangguhkan', 'note' => 'Perlu pemeriksaan',
        ])->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'deferred_1']);
        $this->actingAs($first)->get('/cuti/persetujuan')->assertSee("cuti/{$id}?from=persetujuan");
        $this->actingAs($first)->post("/cuti/{$id}/keputusan", ['decision' => 'disetujui'])->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'numbering']);
    }

    public function test_missing_or_ambiguous_office_prevents_submission_and_access(): void
    {
        [$employee, $first, $second, $positions] = $this->setupRoute();
        $duplicate = User::factory()->create(['position_id' => $positions['Tahap 1']]);
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasErrors('route');
        $this->assertDatabaseCount('leave_requests', 0);

        $duplicate->update(['active' => false]);
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');
        $duplicate->update(['active' => true]);
        $this->actingAs($duplicate)->get("/cuti/{$id}")->assertForbidden();
        $this->actingAs($first)->post("/cuti/{$id}/keputusan", ['decision' => 'disetujui'])->assertForbidden();
    }

    public function test_unrelated_employee_cannot_view_or_change_another_request(): void
    {
        [$employee] = $this->setupRoute();
        $other = User::factory()->create();
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');

        $this->actingAs($other)->get("/cuti/{$id}")->assertForbidden();
        $this->actingAs($other)->post("/cuti/{$id}/revisi", $this->form())->assertForbidden();
    }

    public function test_system_admin_can_only_preview_another_approvers_decision(): void
    {
        [$employee, $first] = $this->setupRoute(false);
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');

        $this->actingAs($admin)->get("/cuti/{$id}")->assertOk()
            ->assertSeeText('Hanya pratinjau')->assertSee('<fieldset disabled>', false);
        $this->actingAs($admin)->post("/cuti/{$id}/keputusan", ['decision' => 'disetujui'])->assertForbidden();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'pending_1']);
        $this->actingAs($first)->post("/cuti/{$id}/keputusan", ['decision' => 'disetujui'])->assertRedirect();
    }

    public function test_only_correspondence_can_assign_a_number_after_approval(): void
    {
        [$employee, $first] = $this->setupRoute(false);
        $correspondence = User::factory()->create(['is_correspondence' => true]);
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');

        $this->actingAs($correspondence)->post("/cuti/{$id}/nomor", ['letter_number' => 'UJI-1'])->assertStatus(409);
        $this->actingAs($first)->post("/cuti/{$id}/keputusan", ['decision' => 'disetujui'])->assertRedirect();
        $this->actingAs($employee)->post("/cuti/{$id}/nomor", ['letter_number' => 'UJI-1'])->assertForbidden();
        $this->actingAs($correspondence)->post("/cuti/{$id}/nomor", ['letter_number' => 'UJI-1'])->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'ready', 'letter_number' => 'UJI-1']);
        $this->assertDatabaseHas('leave_request_events', ['leave_request_id' => $id, 'action' => 'nomor_surat', 'actor_id' => $correspondence->id]);
    }

    public function test_release_deducts_annual_balance_and_saves_private_pdf(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26));
        [$employee, $first] = $this->setupRoute(false);
        $correspondence = User::factory()->create(['is_correspondence' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $this->actingAs($admin)->post("/admin/pegawai/{$employee->id}/saldo", [
            'year' => 2026, 'n2' => 2, 'n1' => 4, 'n' => 12, 'reason' => 'Saldo awal uji',
        ])->assertSessionHasNoErrors();
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');
        $this->actingAs($first)->post("/cuti/{$id}/keputusan", ['decision' => 'disetujui'])->assertRedirect();
        $this->actingAs($correspondence)->post("/cuti/{$id}/nomor", ['letter_number' => 'UJI-2'])->assertRedirect();
        $this->actingAs($employee)->get("/cuti/{$id}/surat")->assertNotFound();
        $this->actingAs($employee)->post("/cuti/{$id}/rilis")->assertForbidden();
        $this->actingAs($admin)->post("/cuti/{$id}/rilis")->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'released']);
        $this->assertDatabaseHas('annual_leave_allocations', ['leave_request_id' => $id, 'origin_year' => 2024, 'days' => 2]);
        $this->assertDatabaseHas('annual_leave_allocations', ['leave_request_id' => $id, 'origin_year' => 2025, 'days' => 3]);
        $pdf = $this->actingAs($employee)->get("/cuti/{$id}/surat")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->actingAs($other)->get("/cuti/{$id}/surat")->assertForbidden();
        $this->actingAs($admin)->post("/cuti/{$id}/rilis")->assertStatus(409);
        $this->assertDatabaseCount('leave_documents', 1);
    }

    public function test_release_is_atomic_when_annual_balance_is_insufficient(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26));
        [$employee, $first] = $this->setupRoute(false);
        $correspondence = User::factory()->create(['is_correspondence' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post("/admin/pegawai/{$employee->id}/saldo", [
            'year' => 2026, 'n2' => 0, 'n1' => 0, 'n' => 1, 'reason' => 'Saldo uji',
        ])->assertSessionHasNoErrors();
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');
        $this->actingAs($first)->post("/cuti/{$id}/keputusan", ['decision' => 'disetujui']);
        $this->actingAs($correspondence)->post("/cuti/{$id}/nomor", ['letter_number' => 'UJI-3']);
        $this->actingAs($admin)->post("/cuti/{$id}/rilis")->assertSessionHasErrors('balance');
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'ready']);
        $this->assertDatabaseCount('annual_leave_allocations', 0);
        $this->assertDatabaseCount('leave_documents', 0);
    }

    public function test_non_annual_release_does_not_require_or_deduct_annual_balance(): void
    {
        [$employee, $first] = $this->setupRoute(false);
        $correspondence = User::factory()->create(['is_correspondence' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($employee)->post('/cuti', array_replace($this->form(), ['leave_type' => 'Cuti Sakit']))->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');
        $this->actingAs($first)->post("/cuti/{$id}/keputusan", ['decision' => 'disetujui']);
        $this->actingAs($correspondence)->post("/cuti/{$id}/nomor", ['letter_number' => 'UJI-4']);
        $this->actingAs($admin)->post("/cuti/{$id}/rilis")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'released']);
        $this->assertDatabaseCount('annual_leave_allocations', 0);
    }

    public function test_backdated_release_recalculates_future_allocations_and_records_the_change(): void
    {
        $this->travelTo(now()->setDate(2027, 6, 1));
        [$employee, $first] = $this->setupRoute(false);
        $correspondence = User::factory()->create(['is_correspondence' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post("/admin/pegawai/{$employee->id}/saldo", [
            'year' => 2026, 'n2' => 0, 'n1' => 6, 'n' => 12, 'reason' => 'Saldo uji',
        ])->assertSessionHasNoErrors();
        $later = $this->readyRequest($employee, $first, $correspondence, '2027-01-04', '2027-01-08', 'UJI-LATER');
        $this->actingAs($admin)->post("/cuti/{$later}/rilis")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('annual_leave_allocations', ['leave_request_id' => $later, 'origin_year' => 2025, 'days' => 5]);

        $earlier = $this->readyRequest($employee, $first, $correspondence, '2026-10-12', '2026-10-15', 'UJI-EARLIER');
        $this->actingAs($admin)->post("/cuti/{$earlier}/rilis")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('annual_leave_allocations', ['leave_request_id' => $later, 'origin_year' => 2025, 'days' => 2]);
        $this->assertDatabaseHas('annual_leave_allocations', ['leave_request_id' => $later, 'origin_year' => 2026, 'days' => 3]);
        $this->assertSame(2, DB::table('leave_request_events')->where('leave_request_id', $later)->where('action', 'saldo_dihitung_ulang')->count());
        $this->actingAs($employee)->get("/cuti/{$later}")->assertOk()
            ->assertSeeText('Tidak ada alokasi.')
            ->assertSee('Tahun 2025</dt><dd>5</dd>', false)
            ->assertSee('Tahun 2025</dt><dd>2</dd>', false)
            ->assertSee('Tahun 2026</dt><dd>3</dd>', false);
    }

    public function test_backdated_release_is_blocked_if_it_would_overdraw_an_already_released_year(): void
    {
        $this->travelTo(now()->setDate(2027, 6, 1));
        [$employee, $first] = $this->setupRoute(false);
        $correspondence = User::factory()->create(['is_correspondence' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post("/admin/pegawai/{$employee->id}/saldo", [
            'year' => 2026, 'n2' => 0, 'n1' => 6, 'n' => 12, 'reason' => 'Saldo uji',
        ])->assertSessionHasNoErrors();
        $later = $this->readyRequest($employee, $first, $correspondence, '2027-01-04', '2027-01-21', 'UJI-LATER');
        $this->actingAs($admin)->post("/cuti/{$later}/rilis")->assertSessionHasNoErrors();

        $earlier = $this->readyRequest($employee, $first, $correspondence, '2026-10-05', '2026-10-27', 'UJI-EARLIER');
        $this->actingAs($admin)->post("/cuti/{$earlier}/rilis")->assertSessionHasErrors('balance');
        $this->assertDatabaseHas('leave_requests', ['id' => $earlier, 'status' => 'ready']);
        $this->assertDatabaseHas('leave_requests', ['id' => $later, 'status' => 'released']);
        $this->assertDatabaseCount('leave_documents', 1);
    }

    public function test_admin_edit_preserves_approval_and_letter_number_and_records_changes(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26));
        [$employee, $first] = $this->setupRoute(false);
        $correspondence = User::factory()->create(['is_correspondence' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post("/admin/pegawai/{$employee->id}/saldo", [
            'year' => 2026, 'n2' => 0, 'n1' => 0, 'n' => 12, 'reason' => 'Saldo uji',
        ])->assertSessionHasNoErrors();
        $id = $this->readyRequest($employee, $first, $correspondence, '2026-10-12', '2026-10-16', 'UJI-EDIT');
        $changed = array_replace($this->form(), [
            'end_date' => '2026-10-15', 'reason' => 'Alasan baru', 'admin_note' => 'Koreksi tanggal',
        ]);
        $this->actingAs($employee)->post("/cuti/{$id}/ubah-admin", $changed)->assertForbidden();
        $this->actingAs($admin)->post("/cuti/{$id}/ubah-admin", $changed)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', [
            'id' => $id, 'status' => 'ready', 'letter_number' => 'UJI-EDIT',
            'reason' => 'Alasan baru', 'working_days' => 4,
        ]);
        $this->assertDatabaseHas('leave_request_events', ['leave_request_id' => $id, 'action' => 'diubah_admin', 'note' => 'Koreksi tanggal']);
        $this->actingAs($employee)->get("/cuti/{$id}")->assertOk()
            ->assertSeeInOrder(['Sebelum', 'Keperluan keluarga', 'Sesudah', 'Alasan baru']);
        $this->actingAs($admin)->post("/cuti/{$id}/rilis")->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/cuti/{$id}/ubah-admin", $changed)->assertStatus(409);
    }

    public function test_release_requires_recheck_if_holidays_change_after_submission(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 26));
        [$employee, $first] = $this->setupRoute(false);
        $correspondence = User::factory()->create(['is_correspondence' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post("/admin/pegawai/{$employee->id}/saldo", [
            'year' => 2026, 'n2' => 0, 'n1' => 0, 'n' => 12, 'reason' => 'Saldo uji',
        ])->assertSessionHasNoErrors();
        $id = $this->readyRequest($employee, $first, $correspondence, '2026-10-12', '2026-10-16', 'UJI-LIBUR');
        $this->actingAs($admin)->post('/admin/libur', ['date' => '2026-10-14', 'description' => 'Libur uji'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/cuti/{$id}/rilis")->assertSessionHasErrors('dates');
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'ready']);

        $this->actingAs($admin)->post("/cuti/{$id}/ubah-admin", array_replace($this->form(), [
            'end_date' => '2026-10-16', 'admin_note' => 'Perbarui perhitungan hari libur',
        ]))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/cuti/{$id}/rilis")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'working_days' => 4, 'status' => 'released']);
    }

    public function test_leave_history_export_is_only_available_to_admin(): void
    {
        [$employee] = $this->setupRoute();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($employee)->post('/cuti', $this->form())->assertSessionHasNoErrors();
        $this->actingAs($employee)->get('/admin/riwayat-cuti.xlsx')->assertForbidden();
        $response = $this->actingAs($admin)->get('/admin/riwayat-cuti.xlsx')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'cuti-history-');
        try {
            file_put_contents($path, $response->getContent());
            $book = IOFactory::createReader('Xlsx')->load($path);
            $sheet = $book->getActiveSheet();
            $this->assertSame('NIP', $sheet->getCell('A1')->getValue());
            $this->assertSame($employee->nip, $sheet->getCell('A2')->getValue());
            $this->assertSame('Menunggu penyetuju 1', $sheet->getCell('G2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
            $this->assertSame(5, $sheet->getCell('F2')->getValue());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('F2')->getDataType());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_kadisnav_leave_must_be_processed_outside_the_system(): void
    {
        $positionId = DB::table('positions')->insertGetId(['name' => ApprovalRouteDefaults::HEAD]);
        $head = User::factory()->create(['position_id' => $positionId]);
        $this->actingAs($head)->get('/cuti/baru')->assertOk()->assertSee('diproses secara khusus di luar sistem');
        $this->actingAs($head)->post('/cuti', $this->form())->assertSessionHasErrors('route');
        $this->assertDatabaseCount('leave_requests', 0);
    }

    private function readyRequest(User $employee, User $first, User $correspondence, string $start, string $end, string $number): int
    {
        $this->actingAs($employee)->post('/cuti', array_replace($this->form(), [
            'start_date' => $start, 'end_date' => $end,
        ]))->assertSessionHasNoErrors();
        $id = (int) DB::table('leave_requests')->max('id');
        $this->actingAs($first)->post("/cuti/{$id}/keputusan", ['decision' => 'disetujui'])->assertRedirect();
        $this->actingAs($correspondence)->post("/cuti/{$id}/nomor", ['letter_number' => $number])->assertRedirect();

        return $id;
    }
}
