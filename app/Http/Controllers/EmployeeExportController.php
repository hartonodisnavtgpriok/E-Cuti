<?php

namespace App\Http\Controllers;

use App\Domain\Leave\ExcelExport;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeExportController extends Controller
{
    public function __invoke(Request $request, ExcelExport $export): Response
    {
        abort_unless($request->user()->is_admin || $request->user()->is_system_admin, 403);
        $employees = User::query()->leftJoin('positions', 'positions.id', '=', 'users.position_id')
            ->leftJoin('units', 'units.id', '=', 'users.unit_id')
            ->select('users.name', 'users.nip', 'users.active', 'positions.name as position_name', 'units.name as unit_name')
            ->orderBy('users.name')->get();
        $rows = $employees->map(fn ($employee) => [
            $employee->nip, $employee->name, $employee->position_name ?? '',
            $employee->unit_name ?? '', $employee->active ? 'Aktif' : 'Tidak aktif',
        ]);

        return $export->download('Nominatif', 'nominatif-pegawai.xlsx', ['NIP', 'Nama', 'Jabatan atau kelompok', 'Unit', 'Status'], $rows);
    }
}
