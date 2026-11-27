<?php

namespace App\Http\Controllers;

use App\Domain\Leave\ApprovalRouteDefaults;
use App\Domain\Leave\NominativeSpreadsheet;
use App\Domain\Leave\WorkflowLock;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use RuntimeException;

class EmployeeImportController extends Controller
{
    private function authorizeAdmin(User $user): void
    {
        abort_unless($user->active && ($user->is_admin || $user->is_system_admin), 403);
    }

    public function preview(Request $request, NominativeSpreadsheet $spreadsheet): View
    {
        $this->authorizeAdmin($request->user());
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);
        try {
            $data = $spreadsheet->read($request->file('file')->getRealPath());
        } catch (SpreadsheetException $exception) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak dapat dibaca atau tidak valid.']);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
        $existing = User::whereIn('nip', array_column($data['employees'], 'nip'))->pluck('nip')->all();
        $payload = Crypt::encryptString(json_encode([
            'id' => (string) Str::uuid(), 'actor' => $request->user()->id,
            'expires' => now()->addMinutes(30)->timestamp, 'data' => $data,
        ], JSON_THROW_ON_ERROR));

        return view('admin-import', ['data' => $data, 'existing' => $existing, 'payload' => $payload]);
    }

    public function confirm(Request $request, ApprovalRouteDefaults $defaults): RedirectResponse
    {
        $this->authorizeAdmin($request->user());
        $data = $request->validate(['payload' => ['required', 'string']]);
        try {
            $payload = json_decode(Crypt::decryptString($data['payload']), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            throw ValidationException::withMessages(['file' => 'Pratinjau impor tidak valid. Unggah ulang file.']);
        }
        if ($payload['actor'] !== $request->user()->id || $payload['expires'] < now()->timestamp) {
            throw ValidationException::withMessages(['file' => 'Pratinjau kedaluwarsa atau milik akun lain. Unggah ulang file.']);
        }

        $counts = DB::transaction(function () use ($request, $payload, $defaults) {
            WorkflowLock::acquire();
            $actor = User::findOrFail($request->user()->id);
            $this->authorizeAdmin($actor);
            if (DB::table('employee_imports')->where('id', $payload['id'])->exists()) {
                throw ValidationException::withMessages(['file' => 'Pratinjau ini sudah diimpor.']);
            }
            $created = 0;
            $existing = 0;
            foreach (array_merge($payload['data']['employees'], $payload['data']['vacancies']) as $row) {
                if ($row['position'] === null) {
                    continue;
                }
                $unitId = null;
                if ($row['unit'] !== null) {
                    $unitId = DB::table('units')->where('name', $row['unit'])->value('id');
                    if ($unitId === null) {
                        $unitId = DB::table('units')->insertGetId(['name' => $row['unit'], 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
                $isGroup = $row['is_group'] ?? false;
                $position = DB::table('positions')->where('name', $row['position'])->first();
                if (! $position) {
                    $positionId = DB::table('positions')->insertGetId([
                        'name' => $row['position'], 'is_group' => $isGroup, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $position = DB::table('positions')->where('id', $positionId)->first();
                }
                if (! $position || (bool) $position->is_group !== $isGroup) {
                    throw ValidationException::withMessages(['file' => 'Nama jabatan bertabrakan dengan kelompok pemohon yang sudah ada. Periksa pemetaan.']);
                }
                if (! isset($row['nip'])) {
                    continue;
                }
                if (User::where('nip', $row['nip'])->exists()) {
                    $existing++;

                    continue;
                }
                User::create([
                    'name' => $row['name'], 'nip' => $row['nip'], 'unit_id' => $unitId,
                    'position_id' => $position->id, 'active' => true, 'password' => $row['nip'],
                ]);
                $created++;
            }
            $defaultRoutes = $defaults->apply();
            DB::table('employee_imports')->insert([
                'id' => $payload['id'], 'actor_id' => $actor->id, 'created_count' => $created,
                'existing_count' => $existing, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('administration_events')->insert([
                'actor_id' => $actor->id, 'action' => 'impor_nominatif',
                'after' => json_encode(['created' => $created, 'existing' => $existing, 'vacancies' => count($payload['data']['vacancies']), 'default_routes' => $defaultRoutes], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $unidentified = count(array_filter($payload['data']['vacancies'], fn ($row) => $row['position'] === null));

            return ['created' => $created, 'existing' => $existing, 'unidentified' => $unidentified, 'routes' => $defaultRoutes];
        }, attempts: 3);

        return redirect()->route('admin.index')->with('success', "Impor selesai: {$counts['created']} akun baru aktif dengan kata sandi awal NIP; {$counts['existing']} NIP yang sudah ada tidak diubah; {$counts['unidentified']} baris tanpa nama jabatan tidak dibuat; {$counts['routes']} pemetaan awal ditambahkan. Periksa pemetaan dan minta pegawai mengganti kata sandi.");
    }
}
