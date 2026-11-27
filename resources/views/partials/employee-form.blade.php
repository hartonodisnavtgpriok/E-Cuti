<form class="card" method="post" action="{{ $employee ? route('admin.user.update', $employee) : route('admin.user.create') }}">
    @csrf
    <label>Nama <input name="name" value="{{ $employee?->name }}" maxlength="255" required></label>
    <label>NIP (18 digit) <input name="nip" value="{{ $employee?->nip }}" inputmode="numeric" pattern="[0-9]{18}" maxlength="18" required></label>
    <label>Jabatan
        <select name="position_id">
            <option value="">-- Belum ditentukan --</option>
            <optgroup label="Pegawai / karyawan (jabatan terendah)">
                @foreach ($positions->where('is_group', 1) as $position)
                    <option value="{{ $position->id }}" @selected($employee?->position_id === $position->id)>{{ $position->name }}</option>
                @endforeach
            </optgroup>
            <optgroup label="Jabatan struktural / penyetuju">
                @foreach ($positions->where('is_group', 0) as $position)
                    <option value="{{ $position->id }}" @selected($employee?->position_id === $position->id)>{{ $position->name }}</option>
                @endforeach
            </optgroup>
        </select>
    </label>
    <label>Unit
        <select name="unit_id">
            <option value="">-- Belum ditentukan --</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected($employee?->unit_id === $unit->id)>{{ $unit->name }}</option>
            @endforeach
        </select>
    </label>
    <label>Kata sandi {{ $employee ? '(kosongkan jika tidak diubah)' : '(kosongkan untuk menggunakan NIP sebagai kata sandi awal)' }}
        <input name="password" type="password" minlength="12" autocomplete="new-password">
    </label>
    <label>Ulangi kata sandi
        <input name="password_confirmation" type="password" minlength="12" autocomplete="new-password">
    </label>
    <label class="check"><input name="active" type="checkbox" value="1" @checked(! $employee || $employee->active)> Akun aktif</label>
    @if (auth()->user()->is_system_admin)
        <label class="check"><input name="is_admin" type="checkbox" value="1" @checked($employee?->is_admin)> Admin</label>
        <label class="check"><input name="is_correspondence" type="checkbox" value="1" @checked($employee?->is_correspondence)> Persuratan</label>
        <label class="check"><input name="is_system_admin" type="checkbox" value="1" @checked($employee?->is_system_admin)> Admin sistem</label>
    @endif
    <button type="submit">Simpan pegawai</button>
</form>
