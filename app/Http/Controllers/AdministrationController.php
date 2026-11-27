<?php

namespace App\Http\Controllers;

use App\Domain\Leave\AnnualBalanceService;
use App\Domain\Leave\ApprovalRouteDefaults;
use App\Domain\Leave\WorkflowLock;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class AdministrationController extends Controller
{
    private function authorizeAdmin(Request $request, bool $fresh = false): void
    {
        $user = $fresh ? $request->user()->fresh() : $request->user();
        abort_unless($user && $user->active && ($user->is_admin || $user->is_system_admin), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin', [
            'positions' => DB::table('positions')->orderBy('name')->get(),
            'units' => DB::table('units')->orderBy('name')->get(),
            'users' => User::when(! $request->user()->is_system_admin, fn ($q) => $q->where('is_system_admin', false))
                ->orderBy('name')->get(),
            'baselines' => DB::table('annual_balance_baselines')->get()->keyBy('user_id'),
            'routes' => DB::table('approval_routes')
                ->join('positions as applicant', 'applicant.id', '=', 'approval_routes.applicant_position_id')
                ->join('positions as first', 'first.id', '=', 'approval_routes.first_position_id')
                ->leftJoin('positions as second', 'second.id', '=', 'approval_routes.second_position_id')
                ->select('applicant.name as applicant', 'first.name as first', 'second.name as second')
                ->orderBy('applicant.name')->get(),
            'holidays' => DB::table('holidays')->orderByDesc('date')->get(),
            'socialLinks' => $request->user()->is_system_admin ? DB::table('social_links')->orderBy('id')->get() : collect(),
        ]);
    }

    public function position(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:positions,name'],
            'is_group' => ['nullable', 'boolean'],
        ]);
        $data['is_group'] = $request->boolean('is_group');
        DB::transaction(function () use ($request, $data) {
            WorkflowLock::acquire();
            $this->authorizeAdmin($request, true);
            DB::table('positions')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
            $this->audit($request->user()->id, null, 'jabatan_ditambahkan', null, $data);
        }, attempts: 3);

        return back()->with('success', 'Jabatan ditambahkan.');
    }

    public function unit(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:units,name']]);
        DB::transaction(function () use ($request, $data) {
            WorkflowLock::acquire();
            $this->authorizeAdmin($request, true);
            DB::table('units')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
            $this->audit($request->user()->id, null, 'unit_ditambahkan', null, $data);
        }, attempts: 3);

        return back()->with('success', 'Unit ditambahkan.');
    }

    public function socialLink(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_system_admin, 403);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
        ]);
        DB::transaction(function () use ($request, $data) {
            WorkflowLock::acquire();
            $actor = $request->user()->fresh();
            abort_unless($actor && $actor->active && $actor->is_system_admin, 403);
            DB::table('social_links')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
            $this->audit($actor->id, null, 'tautan_ditambahkan', null, $data);
        }, attempts: 3);

        return back()->with('success', 'Tautan ditambahkan.');
    }

    public function deleteSocialLink(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->is_system_admin, 403);
        DB::transaction(function () use ($request, $id) {
            WorkflowLock::acquire();
            $actor = $request->user()->fresh();
            abort_unless($actor && $actor->active && $actor->is_system_admin, 403);
            $before = DB::table('social_links')->where('id', $id)->first();
            abort_unless($before, 404);
            DB::table('social_links')->where('id', $id)->delete();
            $this->audit($actor->id, null, 'tautan_dihapus', (array) $before, null);
        }, attempts: 3);

        return back()->with('success', 'Tautan dihapus.');
    }

    public function route(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'applicant_position_id' => ['required', 'integer', 'exists:positions,id'],
            'first_position_id' => ['required', 'integer', 'exists:positions,id', 'different:applicant_position_id'],
            'second_position_id' => ['nullable', 'integer', 'exists:positions,id', 'different:first_position_id', 'different:applicant_position_id'],
        ]);
        if (DB::table('positions')->where('id', $data['applicant_position_id'])->value('name') === ApprovalRouteDefaults::HEAD) {
            throw ValidationException::withMessages(['applicant_position_id' => 'Cuti Kepala Distrik diproses khusus di luar sistem.']);
        }
        if (DB::table('positions')->whereIn('id', array_filter([$data['first_position_id'], $data['second_position_id'] ?? null]))->where('is_group', true)->exists()) {
            throw ValidationException::withMessages(['first_position_id' => 'Penyetuju harus berupa jabatan, bukan kelompok pemohon.']);
        }
        DB::transaction(function () use ($request, $data) {
            WorkflowLock::acquire();
            $this->authorizeAdmin($request, true);
            $before = DB::table('approval_routes')->where('applicant_position_id', $data['applicant_position_id'])->first();
            DB::table('approval_routes')->updateOrInsert(
                ['applicant_position_id' => $data['applicant_position_id']],
                [
                    'first_position_id' => $data['first_position_id'],
                    'second_position_id' => $data['second_position_id'] ?? null,
                    'updated_at' => now(),
                ],
            );
            $this->audit($request->user()->id, null, 'pemetaan_diubah', $before ? (array) $before : null, $data);
        }, attempts: 3);

        return back()->with('success', 'Pemetaan persetujuan disimpan.');
    }

    public function holiday(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($request, $data) {
            WorkflowLock::acquire();
            $this->authorizeAdmin($request, true);
            $before = DB::table('holidays')->where('date', $data['date'])->first();
            DB::table('holidays')->updateOrInsert(
                ['date' => $data['date']],
                ['description' => $data['description'], 'updated_at' => now()],
            );
            $this->audit($request->user()->id, null, 'hari_libur_diubah', $before ? (array) $before : null, $data);
        }, attempts: 3);

        return back()->with('success', 'Hari libur disimpan.');
    }

    public function deleteHoliday(Request $request, string $date): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $request->merge(['date' => $date])->validate(['date' => ['required', 'date_format:Y-m-d']]);
        DB::transaction(function () use ($request, $date) {
            WorkflowLock::acquire();
            $this->authorizeAdmin($request, true);
            $before = DB::table('holidays')->where('date', $date)->first();
            abort_unless($before, 404);
            DB::table('holidays')->where('date', $date)->delete();
            $this->audit($request->user()->id, null, 'hari_libur_dihapus', (array) $before, null);
        }, attempts: 3);

        return back()->with('success', 'Hari libur dihapus.');
    }

    public function balance(Request $request, User $employee, AnnualBalanceService $balances): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:3', 'max:'.now()->year],
            'n2' => ['required', 'integer', 'between:0,6'],
            'n1' => ['required', 'integer', 'between:0,6'],
            'n' => ['required', 'integer', 'between:0,12'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $employee, $data, $balances) {
            WorkflowLock::acquire();
            $this->authorizeAdmin($request, true);
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $before = DB::table('annual_balance_baselines')->where('user_id', $employee->id)->first();
            $values = [
                'year' => (int) $data['year'], 'n2' => (int) $data['n2'],
                'n1' => (int) $data['n1'], 'n' => (int) $data['n'],
            ];
            try {
                $projection = $balances->project($employee->id, (object) $values);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['balance' => 'Saldo tidak dapat disimpan: '.$exception->getMessage()]);
            }
            DB::table('annual_balance_baselines')->updateOrInsert(
                ['user_id' => $employee->id],
                $values + ['updated_at' => now()],
            );
            DB::table('annual_balance_baseline_events')->insert([
                'user_id' => $employee->id, 'actor_id' => $request->user()->id,
                'reason' => $data['reason'],
                'before' => $before ? json_encode([
                    'year' => $before->year, 'n2' => $before->n2, 'n1' => $before->n1, 'n' => $before->n,
                ], JSON_THROW_ON_ERROR) : null,
                'after' => json_encode($values, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $balances->persistAllocations($projection['allocations'], $request->user()->id);
        }, attempts: 3);

        return back()->with('success', 'Saldo awal disimpan dan riwayat koreksi dicatat.');
    }

    public function history(Request $request, User $employee): View
    {
        $this->authorizeAdmin($request);
        $balanceHistory = DB::table('annual_balance_baseline_events as events')
            ->join('users as actors', 'actors.id', '=', 'events.actor_id')
            ->where('events.user_id', $employee->id)
            ->select('events.*', 'actors.name as actor_name')->orderByDesc('events.id')->get();
        $employeeHistory = DB::table('administration_events as events')
            ->join('users as actors', 'actors.id', '=', 'events.actor_id')
            ->where('events.subject_id', $employee->id)
            ->select('events.*', 'actors.name as actor_name')->orderByDesc('events.id')->get();

        return view('admin-history', [
            'employee' => $employee, 'balanceHistory' => $balanceHistory, 'employeeHistory' => $employeeHistory,
        ]);
    }

    public function saveUser(Request $request, ?User $employee = null): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $employee = $employee?->exists ? $employee : new User;
        if ($employee->exists && ! $request->user()->is_system_admin) {
            abort_if($employee->is_admin || $employee->is_system_admin || $employee->is_correspondence, 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'digits:18', Rule::unique('users', 'nip')->ignore($employee->id)],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
            'active' => ['nullable', 'boolean'],
            'is_admin' => ['nullable', 'boolean'],
            'is_system_admin' => ['nullable', 'boolean'],
            'is_correspondence' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $data, $employee) {
            WorkflowLock::acquire();
            $this->authorizeAdmin($request, true);
            $actor = $request->user()->fresh();
            if ($employee->exists) {
                $employee = User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            }
            if (! $actor->is_system_admin) {
                abort_if($employee->exists && ($employee->is_admin || $employee->is_system_admin || $employee->is_correspondence), 403);
                abort_if($request->boolean('is_admin') || $request->boolean('is_system_admin') || $request->boolean('is_correspondence'), 403);
            }
            if ($employee->id === $actor->id && (! $request->boolean('active') || ! $request->boolean('is_system_admin'))) {
                throw ValidationException::withMessages(['employee' => 'Administrator sistem tidak dapat menonaktifkan atau mencabut aksesnya sendiri.']);
            }
            if ($employee->exists && $request->boolean('active') && $employee->password === null && empty($data['password'])) {
                throw ValidationException::withMessages(['password' => 'Tetapkan kata sandi sebelum mengaktifkan akun.']);
            }

            $keys = ['name', 'nip', 'position_id', 'unit_id', 'active', 'is_admin', 'is_system_admin', 'is_correspondence'];
            $isNew = ! $employee->exists;
            $before = $employee->exists ? $employee->only($keys) : null;
            if ($employee->exists && (! empty($data['password']) || ($employee->active && ! $request->boolean('active')))) {
                $employee->auth_version++;
            }
            $employee->name = $data['name'];
            $employee->nip = $data['nip'];
            $employee->position_id = $data['position_id'] ?? null;
            $employee->unit_id = $data['unit_id'] ?? null;
            $employee->active = $request->boolean('active');
            if (! empty($data['password'])) {
                $employee->password = $data['password'];
            } elseif (! $employee->exists) {
                $employee->password = $data['nip'];
            }
            if ($actor->is_system_admin) {
                $employee->is_admin = $request->boolean('is_admin');
                $employee->is_system_admin = $request->boolean('is_system_admin');
                $employee->is_correspondence = $request->boolean('is_correspondence');
            }
            $employee->save();
            $this->audit($actor->id, $employee->id, 'pegawai_disimpan', $before, $employee->only($keys) + ['password_changed' => $isNew || ! empty($data['password'])]);
        }, attempts: 3);

        return back()->with('success', 'Data pegawai disimpan.');
    }

    private function audit(int $actorId, ?int $subjectId, string $action, ?array $before, ?array $after): void
    {
        DB::table('administration_events')->insert([
            'actor_id' => $actorId, 'subject_id' => $subjectId, 'action' => $action,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
