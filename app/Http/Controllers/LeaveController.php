<?php

namespace App\Http\Controllers;

use App\Domain\Leave\AnnualBalanceService;
use App\Domain\Leave\ApprovalRouteDefaults;
use App\Domain\Leave\LeaveDocument;
use App\Domain\Leave\LeaveTypes;
use App\Domain\Leave\WorkflowLock;
use App\Domain\Leave\WorkingDays;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $leaves = $this->leaveListQuery()
            ->where('leave_requests.user_id', $user->id)
            ->orderByDesc('leave_requests.id')->get();

        return view('leave.index', ['leaves' => $leaves, 'page' => 'pengajuan']);
    }

    public function approvals(Request $request): View
    {
        $user = $request->user();
        $uniquePosition = $user->position_id !== null
            && User::where('position_id', $user->position_id)->where('active', true)->count() === 1;
        if (! $uniquePosition) {
            return view('leave.index', ['leaves' => collect(), 'page' => 'persetujuan']);
        }
        $query = $this->leaveListQuery()->where('leave_requests.user_id', '!=', $user->id)
            ->where(function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('first_position_id', $user->position_id)->whereIn('status', ['pending_1', 'deferred_1']);
                })->orWhere(function ($query) use ($user) {
                    $query->where('second_position_id', $user->position_id)->whereIn('status', ['pending_2', 'deferred_2']);
                });
            });

        return view('leave.index', ['leaves' => $query->orderByDesc('leave_requests.id')->get(), 'page' => 'persetujuan']);
    }

    public function manage(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->is_admin || $user->is_system_admin || $user->is_correspondence, 403);

        $leaves = $this->leaveListQuery()->orderByDesc('leave_requests.id')->get();

        return view('leave.index', ['leaves' => $leaves, 'page' => 'kelola']);
    }

    private function leaveListQuery(): Builder
    {
        return DB::table('leave_requests')->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->select('leave_requests.*', 'users.name as employee_name');
    }

    public function create(Request $request): View
    {
        if ($this->outsideSystem($request->user())) {
            return view('leave.outside');
        }

        return view('leave.form', ['leave' => null, 'types' => LeaveTypes::ALL, 'adminEdit' => false]);
    }

    public function store(Request $request): RedirectResponse
    {
        $id = DB::transaction(function () use ($request) {
            WorkflowLock::acquire();
            $employee = User::findOrFail($request->user()->id);
            abort_unless($employee->active, 403);
            $data = $this->validatedData($request);
            $route = $this->routeFor($employee);
            $id = DB::table('leave_requests')->insertGetId($data + [
                'user_id' => $request->user()->id,
                'status' => 'pending_1',
                'first_position_id' => $route->first_position_id,
                'second_position_id' => $route->second_position_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($id, $request->user()->id, 'diajukan', null, $data);
            $this->storeAttachments($request, $id);

            return $id;
        }, attempts: 3);

        return redirect()->route('leave.show', $id)->with('success', 'Pengajuan berhasil dikirim.');
    }

    public function show(Request $request, int $id): View
    {
        $leave = $this->find($id);
        $this->authorizeView($request->user(), $leave);
        $canDecide = $this->isCurrentApprover($request->user(), $leave);
        $events = DB::table('leave_request_events')->join('users', 'users.id', '=', 'leave_request_events.actor_id')
            ->where('leave_request_id', $id)
            ->select('leave_request_events.*', 'users.name as actor')->orderBy('leave_request_events.id')->get();

        return view('leave.show', [
            'leave' => $leave,
            'events' => $events,
            'attachments' => DB::table('leave_attachments')->where('leave_request_id', $id)->orderBy('id')->get(),
            'canDecide' => $canDecide,
            'canPreviewDecision' => ! $canDecide && $request->user()->is_system_admin
                && $this->pendingPosition($leave) !== null,
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $leave = $this->find($id);
        abort_unless($leave->user_id === $request->user()->id && $leave->status === 'revision', 403);

        return view('leave.form', ['leave' => $leave, 'types' => LeaveTypes::ALL, 'adminEdit' => false]);
    }

    public function adminEdit(Request $request, int $id): View
    {
        abort_unless($request->user()->is_admin || $request->user()->is_system_admin, 403);
        $leave = $this->find($id);
        abort_if($leave->status === 'released', 409, 'Surat yang sudah dirilis tidak dapat diubah.');

        return view('leave.form', ['leave' => $leave, 'types' => LeaveTypes::ALL, 'adminEdit' => true]);
    }

    public function adminUpdate(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->is_admin || $request->user()->is_system_admin, 403);
        $note = $request->validate(['admin_note' => ['required', 'string', 'max:2000']])['admin_note'];
        DB::transaction(function () use ($request, $id, $note) {
            WorkflowLock::acquire();
            $actor = User::findOrFail($request->user()->id);
            abort_unless($actor->active && ($actor->is_admin || $actor->is_system_admin), 403);
            $leave = $this->find($id, true);
            abort_if($leave->status === 'released', 409, 'Surat yang sudah dirilis tidak dapat diubah.');
            $data = $this->validatedData($request);
            $before = [];
            foreach (array_keys($data) as $key) {
                $before[$key] = $leave->$key;
            }
            DB::table('leave_requests')->where('id', $id)->update($data + ['updated_at' => now()]);
            $this->event($id, $actor->id, 'diubah_admin', $note, [
                'before' => $before, 'after' => $data, 'actor_name' => $actor->name,
            ]);
        }, attempts: 3);

        return redirect()->route('leave.show', $id)->with('success', 'Perubahan admin disimpan; persetujuan dan nomor tetap berlaku.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        DB::transaction(function () use ($request, $id) {
            WorkflowLock::acquire();
            $employee = User::findOrFail($request->user()->id);
            abort_unless($employee->active, 403);
            $leave = $this->find($id, true);
            abort_unless($leave->user_id === $employee->id && $leave->status === 'revision', 403);
            $data = $this->validatedData($request);
            $route = $this->routeFor($employee);
            DB::table('leave_requests')->where('id', $id)->update($data + [
                'status' => 'pending_1',
                'first_position_id' => $route->first_position_id,
                'second_position_id' => $route->second_position_id,
                'updated_at' => now(),
            ]);
            $this->event($id, $request->user()->id, 'dikirim_ulang', null, $data);
            $this->storeAttachments($request, $id);
        }, attempts: 3);

        return redirect()->route('leave.show', $id)->with('success', 'Revisi dikirim kembali ke tahap pertama.');
    }

    public function decide(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['disetujui', 'perubahan', 'ditangguhkan', 'tidak_disetujui'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') !== 'disetujui')],
        ]);

        DB::transaction(function () use ($request, $id, $data) {
            WorkflowLock::acquire();
            $leave = $this->find($id, true);
            $approver = User::findOrFail($request->user()->id);
            abort_unless($approver->active && $this->isCurrentApprover($approver, $leave), 403);
            $stage = str_ends_with($leave->status, '_1') ? 1 : 2;
            $status = match ($data['decision']) {
                'disetujui' => $stage === 1 && $leave->second_position_id !== null ? 'pending_2' : 'numbering',
                'perubahan', 'tidak_disetujui' => 'revision',
                'ditangguhkan' => 'deferred_'.$stage,
            };
            DB::table('leave_requests')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
            $positionId = $stage === 1 ? $leave->first_position_id : $leave->second_position_id;
            $this->event($id, $request->user()->id, $data['decision'], $data['note'] ?? null, [
                'stage' => $stage,
                'name' => $approver->name,
                'nip' => $approver->nip,
                'position' => DB::table('positions')->where('id', $positionId)->value('name'),
            ]);
        }, attempts: 3);

        return redirect()->route('leave.approvals')->with('success', 'Keputusan tersimpan.');
    }

    public function number(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->is_correspondence, 403);
        $data = $request->validate([
            'letter_number' => ['required', 'string', 'max:255', 'unique:leave_requests,letter_number'],
        ]);
        DB::transaction(function () use ($request, $id, $data) {
            WorkflowLock::acquire();
            $actor = User::findOrFail($request->user()->id);
            abort_unless($actor->active && $actor->is_correspondence, 403);
            $leave = $this->find($id, true);
            abort_unless($leave->status === 'numbering', 409, 'Pengajuan belum siap diberi nomor.');
            DB::table('leave_requests')->where('id', $id)->update([
                'letter_number' => $data['letter_number'], 'status' => 'ready', 'updated_at' => now(),
            ]);
            $this->event($id, $request->user()->id, 'nomor_surat', $data['letter_number']);
        }, attempts: 3);

        return redirect()->route('leave.show', $id)->with('success', 'Nomor surat disimpan.');
    }

    public function release(Request $request, int $id, AnnualBalanceService $balances, LeaveDocument $documents): RedirectResponse
    {
        abort_unless($request->user()->is_admin || $request->user()->is_system_admin, 403);
        $userId = DB::table('leave_requests')->where('id', $id)->value('user_id') ?? abort(404);
        DB::transaction(function () use ($request, $id, $userId, $balances, $documents) {
            WorkflowLock::acquire();
            $actor = User::findOrFail($request->user()->id);
            abort_unless($actor->active && ($actor->is_admin || $actor->is_system_admin), 403);
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $leave = $this->find($id, true);
            abort_unless($leave->status === 'ready' && $leave->letter_number !== null, 409, 'Surat belum siap dirilis.');
            $holidays = DB::table('holidays')->whereBetween('date', [$leave->start_date, $leave->end_date])->pluck('date')->all();
            $currentDays = (new WorkingDays)->count(new DateTimeImmutable($leave->start_date), new DateTimeImmutable($leave->end_date), $holidays);
            if ($currentDays !== (int) $leave->working_days) {
                throw ValidationException::withMessages(['dates' => 'Hari libur berubah. Admin harus memeriksa dan menyimpan ulang permohonan sebelum rilis.']);
            }
            $baseline = DB::table('annual_balance_baselines')->where('user_id', $userId)->first();
            $projection = null;
            if ($leave->leave_type === LeaveTypes::ANNUAL && ! $baseline) {
                throw ValidationException::withMessages(['balance' => 'Saldo awal cuti tahunan belum diatur oleh admin.']);
            }
            if ($baseline) {
                try {
                    $projection = $balances->project($userId, $baseline, $leave->leave_type === LeaveTypes::ANNUAL ? $leave : null);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(['balance' => 'Rilis diblokir: '.$exception->getMessage()]);
                }
            }
            $year = (int) substr($leave->start_date, 0, 4);
            $annual = $projection['years'][$year] ?? null;
            $pdf = $documents->render($leave, $annual);
            if (! str_starts_with($pdf, '%PDF-')) {
                throw new \RuntimeException('Pembuatan dokumen PDF gagal.');
            }
            if ($projection && $leave->leave_type === LeaveTypes::ANNUAL) {
                $balances->persistAllocations($projection['allocations'], $request->user()->id);
            }
            DB::table('leave_documents')->insert([
                'leave_request_id' => $id,
                'pdf_base64' => base64_encode($pdf),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('leave_requests')->where('id', $id)->update(['status' => 'released', 'updated_at' => now()]);
            $this->event($id, $request->user()->id, 'dirilis');
        }, attempts: 3);

        return redirect()->route('leave.show', $id)->with('success', 'Surat dirilis.');
    }

    public function download(Request $request, int $id): Response
    {
        $leave = $this->find($id);
        $this->authorizeView($request->user(), $leave);
        abort_unless($leave->status === 'released', 404);
        $encoded = DB::table('leave_documents')->where('leave_request_id', $id)->value('pdf_base64');
        if ($encoded === null || ($pdf = base64_decode($encoded, true)) === false || ! str_starts_with($pdf, '%PDF-')) {
            throw new \RuntimeException('Dokumen surat yang dirilis tidak tersedia atau rusak.');
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="surat-cuti-'.$id.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function attachment(Request $request, int $id, int $attachmentId): StreamedResponse
    {
        $this->authorizeView($request->user(), $this->find($id));
        $file = DB::table('leave_attachments')->where('leave_request_id', $id)->where('id', $attachmentId)->first();
        abort_unless($file && Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function storeAttachments(Request $request, int $leaveId): void
    {
        foreach ($request->file('attachments', []) as $file) {
            DB::table('leave_attachments')->insert([
                'leave_request_id' => $leaveId,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path' => $file->store('leave-attachments'),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function validatedData(Request $request): array
    {
        if (! $request->routeIs('leave.admin-update')) {
            $request->validate([
                'attachments' => ['nullable', 'array', 'max:5'],
                'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ], [
                'attachments.*.mimes' => 'Lampiran harus berupa JPG, PNG, atau PDF.',
                'attachments.*.max' => 'Ukuran tiap lampiran maksimal 5 MB.',
            ]);
        }
        $data = $request->validate([
            'leave_type' => ['required', Rule::in(LeaveTypes::ALL)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'address' => ['required', 'string', 'max:2000'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-]{7,}$/'],
        ], [
            'phone.required' => 'Nomor WhatsApp wajib diisi untuk notifikasi.',
            'phone.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
        ]);
        $holidays = DB::table('holidays')->whereBetween('date', [$data['start_date'], $data['end_date']])->pluck('date')->all();
        $days = (new WorkingDays)->count(new DateTimeImmutable($data['start_date']), new DateTimeImmutable($data['end_date']), $holidays);
        if ($days < 1 || $days > 65535) {
            throw ValidationException::withMessages(['start_date' => 'Jumlah hari kerja harus antara 1 dan 65535.']);
        }
        $data['working_days'] = $days;

        return $data;
    }

    private function routeFor(User $user): stdClass
    {
        if ($this->outsideSystem($user)) {
            throw ValidationException::withMessages(['route' => 'Cuti Kepala Distrik diproses khusus di luar sistem.']);
        }
        $route = DB::table('approval_routes')->where('applicant_position_id', $user->position_id)->first();
        if (! $route) {
            throw ValidationException::withMessages(['route' => 'Pemetaan penyetuju belum tersedia. Hubungi admin.']);
        }
        foreach (array_filter([$route->first_position_id, $route->second_position_id]) as $positionId) {
            $approvers = User::where('position_id', $positionId)->where('active', true)->get();
            if ($approvers->count() !== 1 || $approvers->first()->id === $user->id) {
                throw ValidationException::withMessages(['route' => 'Jabatan penyetuju harus memiliki tepat satu pejabat aktif yang bukan pemohon. Hubungi admin.']);
            }
        }

        return $route;
    }

    private function outsideSystem(User $user): bool
    {
        return $user->position_id !== null
            && DB::table('positions')->where('id', $user->position_id)->value('name') === ApprovalRouteDefaults::HEAD;
    }

    private function find(int $id, bool $lock = false): stdClass
    {
        $query = DB::table('leave_requests')->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->leftJoin('positions', 'positions.id', '=', 'users.position_id')
            ->leftJoin('units', 'units.id', '=', 'users.unit_id')
            ->where('leave_requests.id', $id)
            ->select('leave_requests.*', 'users.name as employee_name', 'users.nip as employee_nip',
                'positions.name as employee_position', 'positions.is_group as position_is_group', 'units.name as employee_unit');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() ?? abort(404);
    }

    private function pendingPosition(stdClass $leave): ?int
    {
        return match ($leave->status) {
            'pending_1', 'deferred_1' => $leave->first_position_id,
            'pending_2', 'deferred_2' => $leave->second_position_id,
            default => null,
        };
    }

    private function isCurrentApprover(User $user, stdClass $leave): bool
    {
        $positionId = $this->pendingPosition($leave);

        return $positionId !== null && $user->position_id === $positionId && $user->id !== $leave->user_id
            && User::where('position_id', $positionId)->where('active', true)->count() === 1;
    }

    private function authorizeView(User $user, stdClass $leave): void
    {
        abort_unless(
            $leave->user_id === $user->id || $user->is_admin || $user->is_system_admin
            || $user->is_correspondence || $this->isCurrentApprover($user, $leave),
            403,
        );
    }

    private function event(int $id, int $actorId, string $action, ?string $note = null, ?array $snapshot = null): void
    {
        DB::table('leave_request_events')->insert([
            'leave_request_id' => $id,
            'actor_id' => $actorId,
            'action' => $action,
            'note' => $note,
            'snapshot' => $snapshot === null ? null : json_encode($snapshot, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
