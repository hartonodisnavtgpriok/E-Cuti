<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrasi - Elektronik Cuti Distrik Navigasi Tipe B Tanjung Priok</title>
    <link rel="stylesheet" href="{{ asset('dashboard.css') }}">
</head>
<body>
    <header class="topbar">
        @include('partials.topbar-brand', ['pageLabel' => 'Administrasi Cuti'])
        <a href="{{ route('dashboard') }}">Dashboard</a>
    </header>
    <main class="container">
        @if (session('success')) <div class="notice" role="status">{{ session('success') }}</div> @endif
        @if ($errors->any())
            <div class="notice error" role="alert">
                <ul>@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
            </div>
        @endif
        <h1>Pengelolaan data</h1>
        @if (auth()->user()->is_system_admin)
            <section class="card">
                <h2>Tautan media sosial</h2>
                <form method="post" action="{{ route('admin.social') }}">
                    @csrf
                    <label for="social-label">Nama</label><input id="social-label" name="label" maxlength="80" required>
                    <label for="social-url">Alamat HTTP atau HTTPS</label><input id="social-url" name="url" type="url" maxlength="2048" required>
                    <button type="submit">Tambah tautan</button>
                </form>
                @foreach ($socialLinks as $link)
                    <form method="post" action="{{ route('admin.social.delete', $link->id) }}">
                        @csrf {{ $link->label }} <button class="secondary" type="submit">Hapus</button>
                    </form>
                @endforeach
            </section>
        @endif
        <section class="card">
            <h2>Impor nominatif Excel</h2>
            <p>Gunakan format nominatif: kolom E-H pada baris 2 berisi NO, NAMA, NIP, JABATAN. Periksa pratinjau sebelum mengimpor.</p>
            <form method="post" action="{{ route('admin.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <label for="file">File XLSX (maksimal 5 MB)</label>
                <input id="file" name="file" type="file" accept=".xlsx" required>
                <button type="submit">Pratinjau</button>
            </form>
        </section>
        <div class="grid">
            <section class="card">
                <h2>Hari libur</h2>
                <form method="post" action="{{ route('admin.holiday') }}">
                    @csrf
                    <label for="holiday-date">Tanggal</label><input id="holiday-date" name="date" type="date" required>
                    <label for="holiday-description">Keterangan</label><input id="holiday-description" name="description" maxlength="255" required>
                    <button type="submit">Simpan</button>
                </form>
                <ul>@foreach ($holidays as $holiday) <li>
                    {{ $holiday->date }} - {{ $holiday->description }}
                    <form method="post" action="{{ route('admin.holiday.delete', $holiday->date) }}">@csrf <button class="secondary" type="submit">Hapus</button></form>
                </li> @endforeach</ul>
            </section>
        </div>
        <h2>Tambah pegawai</h2>
        @include('partials.employee-form', ['employee' => null])
        <h2>Nominatif pegawai</h2>
        <p><a class="button-link" href="{{ route('admin.export') }}">Ekspor nominatif ke Excel</a></p>
        <p>Status penyetuju mengikuti jabatan dan pemetaan persetujuan secara otomatis, jadi tidak perlu diatur per pegawai.</p>
        @php($positionNames = $positions->pluck('name', 'id'))
        @php($unitNames = $units->pluck('name', 'id'))
        <div class="table-search">
            <label for="employee-search">Cari pegawai</label>
            <input id="employee-search" type="search" placeholder="Ketik nama, NIP, unit kerja, atau jabatan" autocomplete="off">
            <p id="employee-search-status" role="status" aria-live="polite"></p>
        </div>
        <div class="table-scroll">
            <table class="employee-table">
                <thead><tr><th scope="col">No</th><th scope="col">Nama</th><th scope="col">Unit kerja</th><th scope="col">Jabatan</th></tr></thead>
                @foreach ($users as $employee)
                    <tbody data-search="{{ mb_strtolower($employee->name.' '.$employee->nip.' '.($unitNames[$employee->unit_id] ?? '').' '.($positionNames[$employee->position_id] ?? '')) }}">
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $employee->name }}<br><small>{{ $employee->nip }}</small></td>
                            <td>{{ $unitNames[$employee->unit_id] ?? '-' }}</td>
                            <td>{{ $positionNames[$employee->position_id] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td colspan="4">
                                <details class="employee">
                                    <summary>Ubah data dan saldo</summary>
                                    <p><a href="{{ route('admin.history', $employee) }}">Lihat riwayat perubahan</a></p>
                                    @if (auth()->user()->is_system_admin || (! $employee->is_admin && ! $employee->is_system_admin && ! $employee->is_correspondence))
                                        @include('partials.employee-form', ['employee' => $employee])
                                    @else
                                        <p>Akun berperan khusus hanya dapat diubah admin sistem.</p>
                                    @endif
                                    <form class="card" method="post" action="{{ route('admin.balance', $employee) }}">
                                        @csrf
                                        @php($baseline = $baselines->get($employee->id))
                                        <h3>Saldo awal cuti tahunan</h3>
                                        <p>Isi sisa N-2, N-1, dan N pada awal pencatatan, sebelum pengajuan di aplikasi. Penggunaan yang sudah dirilis akan dihitung ulang.</p>
                                        <label>Tahun awal pencatatan <input name="year" type="number" min="3" max="{{ now()->year }}" value="{{ $baseline->year ?? now()->year }}" required></label>
                                        <label>Sisa N-2 (maks. 6) <input name="n2" type="number" min="0" max="6" value="{{ $baseline->n2 ?? '' }}" required></label>
                                        <label>Sisa N-1 (maks. 6) <input name="n1" type="number" min="0" max="6" value="{{ $baseline->n1 ?? '' }}" required></label>
                                        <label>Sisa N (maks. 12) <input name="n" type="number" min="0" max="12" value="{{ $baseline->n ?? '' }}" required></label>
                                        <label>Alasan penetapan atau koreksi <textarea name="reason" maxlength="2000" required></textarea></label>
                                        <button type="submit">Simpan saldo awal</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            </table>
        </div>
    </main>
    <script>
        (function () {
            var input = document.getElementById('employee-search');
            var status = document.getElementById('employee-search-status');
            var rows = document.querySelectorAll('.employee-table tbody[data-search]');
            input.addEventListener('input', function () {
                var words = input.value.toLowerCase().split(/\s+/).filter(Boolean);
                var shown = 0;
                rows.forEach(function (row) {
                    var match = words.every(function (word) { return row.dataset.search.indexOf(word) !== -1; });
                    row.hidden = !match;
                    if (match) { shown++; }
                });
                status.textContent = words.length ? (shown ? shown + ' pegawai ditemukan.' : 'Tidak ada pegawai yang cocok.') : '';
            });
        })();
    </script>
</body>
</html>
