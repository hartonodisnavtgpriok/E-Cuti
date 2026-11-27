<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pratinjau Impor - DISNAV</title><link rel="stylesheet" href="{{ asset('dashboard.css') }}">
</head>
<body>
    <header class="topbar">@include('partials.topbar-brand', ['pageLabel' => 'Pratinjau nominatif'])<a href="{{ route('admin.index') }}">Batal</a></header>
    <main class="container">
        <h1>Periksa sebelum impor</h1>
        <p>{{ count($data['employees']) }} pegawai; {{ count($data['vacancies']) }} baris tanpa NIP. Akun baru langsung aktif dengan kata sandi awal sama dengan NIP. NIP yang sudah ada tidak diubah. Kata sandi awal mudah ditebak; minta pegawai menggantinya segera. Pemetaan awal yang dikenal akan ditambahkan tanpa mengganti pemetaan yang sudah ada; admin harus memeriksanya.</p>
        <h2>Pegawai</h2>
        @foreach ($data['employees'] as $row)
            <div class="card employee">
                Baris {{ $row['row'] }} &middot; {{ $row['name'] }} &middot; {{ $row['nip'] }}<br>
                Unit: {{ $row['unit'] ?? 'Tidak diketahui' }} &middot;
                {{ $row['is_group'] ? 'Kelompok pemohon' : 'Jabatan' }}: {{ $row['position'] }}
                @if (in_array($row['nip'], $existing, true)) <strong> &mdash; NIP sudah ada, tidak akan diubah</strong> @endif
            </div>
        @endforeach
        <h2>Baris tanpa NIP</h2>
        @forelse ($data['vacancies'] as $row)
            <div class="card employee">Baris {{ $row['row'] }} &middot; Unit: {{ $row['unit'] ?? 'Tidak diketahui' }} &middot;
                @if ($row['position'])
                    Posisi kosong: {{ $row['position'] }} (tanpa akun)
                @else
                    Nama jabatan tidak tersedia; tidak akan dibuat.
                @endif
            </div>
        @empty
            <p>Tidak ada.</p>
        @endforelse
        <form method="post" action="{{ route('admin.import.confirm') }}">
            @csrf
            <input type="hidden" name="payload" value="{{ $payload }}">
            <button type="submit">Konfirmasi impor</button>
        </form>
    </main>
</body>
</html>
